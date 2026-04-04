<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_Sync {
    private static ?Woo_OTEC_Moodle_Sync $instance = null;

    public static function instance(): Woo_OTEC_Moodle_Sync {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function run(bool $verbose = false): array {
        $result = array(
            'status'             => 'error',
            'message'            => '',
            'categories_created' => 0,
            'categories_updated' => 0,
            'products_created'   => 0,
            'products_updated'   => 0,
        );

        if (!class_exists('WooCommerce')) {
            $result['message'] = __('WooCommerce is not active.', 'woo-otec-moodle');
            $this->update_last_sync($result);
            return $result;
        }

        try {
            $categories      = Woo_OTEC_Moodle_API::instance()->get_categories();
            $category_result = $this->sync_categories(is_wp_error($categories) ? [] : $categories);

            $courses = Woo_OTEC_Moodle_API::instance()->get_courses();
            if (is_wp_error($courses) || empty($courses)) {
                $result['message']            = is_wp_error($courses) ? $courses->get_error_message() : __('Courses could not be retrieved from Moodle.', 'woo-otec-moodle');
                $result['categories_created'] = $category_result['created'];
                $result['categories_updated'] = $category_result['updated'];
                $this->update_last_sync($result);
                return $result;
            }

            $course_result = $this->sync_courses($courses);

            $result['status']             = 'success';
            $result['message']            = __('Synchronization completed.', 'woo-otec-moodle');
            $result['categories_created'] = $category_result['created'];
            $result['categories_updated'] = $category_result['updated'];
            $result['products_created']   = $course_result['created'];
            $result['products_updated']   = $course_result['updated'];

            $this->update_last_sync($result);
            Woo_OTEC_Moodle_Logger::info('Sincronización Moodle -> WooCommerce completada', $result);

            if ($verbose) {
                foreach ($course_result['messages'] as $message) {
                    Woo_OTEC_Moodle_Logger::info($message);
                }
            }
        } catch (PCC_Moodle_Exception $e) {
            $result['message'] = __('Moodle connection error:', 'woo-otec-moodle') . ' ' . $e->getMessage();
            Woo_OTEC_Moodle_Logger::error('Fallo global de sincronización', [
                'error_code' => $e->get_error_code(),
                'function'   => $e->get_moodle_function(),
                'message'    => $e->getMessage(),
            ]);
            $this->update_last_sync($result);
        }

        return $result;
    }


    /**
     * Sincroniza las categorías de Moodle hacia WooCommerce respetando la jerarquía.
     * Implementa un algoritmo de múltiples pasadas para asegurar que los padres existan antes que los hijos.
     *
     * @param array $categories Lista de objetos de categoría de Moodle.
     * @return array Resumen de sincronización (creados, actualizados).
     */
    public function sync_categories(array $categories): array {
        $result = array('created' => 0, 'updated' => 0);
        if (!taxonomy_exists('product_cat')) {
            return $result;
        }

        // 1. Indexar categorías por ID para un acceso rápido
        $pending = array();
        foreach ($categories as $category) {
            if (!is_object($category) || empty($category->id) || empty($category->name)) {
                continue;
            }

            $pending[(int) $category->id] = $category;
        }

        // 2. Procesamiento jerárquico (Máximo 20 niveles de profundidad)
        // Intentamos procesar hijos solo si su padre ya existe en WordPress.
        $attempt = 0;
        while (!empty($pending) && $attempt < 20) {
            $processed = 0;

            foreach ($pending as $moodle_id => $category) {
                $parent_moodle_id = isset($category->parent) ? (int) $category->parent : 0;
                $parent_term_id = 0;

                // Si tiene padre, verificamos si ya está sincronizado
                if ($parent_moodle_id > 0) {
                    $parent_term = $this->get_category_by_moodle_id($parent_moodle_id);
                    if (!$parent_term) {
                        // El padre aún no existe, lo dejamos para la siguiente pasada
                        continue;
                    }
                    $parent_term_id = (int) $parent_term->term_id;
                }

                $created = $this->upsert_category($category, $parent_term_id);
                if ($created === null) {
                    continue;
                }

                if ($created) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }

                unset($pending[$moodle_id]);
                $processed++;
            }

            // Si no se procesó nada en esta pasada (posible orfandad), 
            // forzamos la creación de los restantes en la raíz.
            if ($processed === 0) {
                foreach ($pending as $moodle_id => $category) {
                    $created = $this->upsert_category($category, 0);
                    if ($created !== null) {
                        $created ? $result['created']++ : $result['updated']++;
                    }
                    unset($pending[$moodle_id]);
                }
                break;
            }

            $attempt++;
        }

        return $result;
    }

    public function sync_courses(array $courses): array {
        $result = array(
            'created'  => 0,
            'updated'  => 0,
            'messages' => array(),
        );

        foreach ($courses as $course) {
            $sync_result = $this->sync_single_course($course);
            if ($sync_result['status'] === 'created') {
                $result['created']++;
            } elseif ($sync_result['status'] === 'updated') {
                $result['updated']++;
            }
            if (!empty($sync_result['message'])) {
                $result['messages'][] = $sync_result['message'];
            }
        }

        return $result;
    }

    public function sync_single_course(object|array $course): array {
        $course_obj = is_array($course) ? (object) $course : $course;
        $moodle_id = (int) ($course_obj->id ?? 0);

        // Omitir el curso con ID 1 (Site Home / Front Page en Moodle)
        if ($moodle_id <= 1) {
            return array(
                'status' => 'ignored',
                'message' => sprintf(
                    /* translators: %d: Moodle course ID */
                    __('Course ID %d ignored (system or site home).', 'woo-otec-moodle'),
                    $moodle_id
                ),
            );
        }

        // Asegurar que image_id esté disponible en el objeto
        if (is_array($course) && isset($course['image_id'])) {
            $course_obj->image_id = $course['image_id'];
        }

        $product_id = $this->find_product_id($moodle_id);

        if ($product_id > 0) {
            $course_data = $this->build_course_sync_data($course_obj, $product_id);
            $this->update_product($product_id, $course_obj, $course_data);
            return array(
                'status' => 'updated',
                'message' => sprintf(
                    /* translators: %d: Moodle course ID */
                    __('Updated course with Moodle ID %d.', 'woo-otec-moodle'),
                    $moodle_id
                ),
            );
        }

        Woo_OTEC_Moodle_Logger::info("Iniciando sincronización de curso", array('moodle_id' => $moodle_id, 'name' => $course_obj->fullname));
        $course_data = $this->build_course_sync_data($course_obj, 0);
        $product_id = $this->create_product($course_obj, $course_data);
        if ($product_id > 0) {
            return array(
                'status' => 'created',
                'message' => sprintf(
                    /* translators: %d: Moodle course ID */
                    __('Created course with Moodle ID %d.', 'woo-otec-moodle'),
                    $moodle_id
                ),
            );
        }

        if ($product_id < 0) {
            return array(
                'status' => 'updated',
                'message' => sprintf(
                    /* translators: %d: Moodle course ID */
                    __('Updated course with Moodle ID %d.', 'woo-otec-moodle'),
                    $moodle_id
                ),
            );
        }

        return array(
            'status' => 'error',
            'message' => sprintf(
                /* translators: %d: Moodle course ID */
                __('The course with Moodle ID %d could not be synchronized.', 'woo-otec-moodle'),
                $moodle_id
            ),
        );
    }

    public function find_product_id(int $moodle_course_id): int {
        if ($moodle_course_id <= 0) {
            return 0;
        }

        foreach (array('_moodle_id', 'moodle_course_id') as $meta_key) {
            $query = new WP_Query(
                array(
                    'post_type'      => 'product',
                    'post_status'    => array('publish', 'draft', 'private'),
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => $meta_key,
                            'value'   => $moodle_course_id,
                            'compare' => '=',
                        ),
                    ),
                )
            );

            if ($query->have_posts()) {
                if ($query->found_posts > 1) {
                    Woo_OTEC_Moodle_Logger::error("Conflicto de integridad: Múltiples productos encontrados para el mismo Moodle ID", array('moodle_id' => $moodle_course_id, 'matches' => $query->posts));
                }
                return (int) $query->posts[0];
            }
        }

        $sku_product_id = $this->find_product_id_by_new_sku_suffix($moodle_course_id);
        if ($sku_product_id > 0) {
            return $sku_product_id;
        }

        // Compatibilidad hacia atrás: formato histórico.
        return $this->find_product_id_by_sku('MOODLE-' . $moodle_course_id);
    }

    private function upsert_category(object $category, int $parent_term_id): ?bool {
        $moodle_id = (int) $category->id;
        $name = sanitize_text_field((string) $category->name);
        if ($moodle_id <= 0 || $name === '') {
            return null;
        }

        $existing = $this->get_category_by_moodle_id($moodle_id);
        $slug_seed = !empty($category->idnumber) ? (string) $category->idnumber : 'moodle-cat-' . $moodle_id . '-' . $name;

        if ($existing) {
            $updated = wp_update_term(
                (int) $existing->term_id,
                'product_cat',
                array(
                    'name'   => $name,
                    'slug'   => sanitize_title($slug_seed),
                    'parent' => $parent_term_id,
                )
            );

            if (!is_wp_error($updated)) {
                Woo_OTEC_Moodle_Logger::info("Categoría actualizada", array('moodle_id' => $moodle_id, 'name' => $name));
            }

            if (is_wp_error($updated)) {
                Woo_OTEC_Moodle_Logger::error('No se pudo actualizar categoría', array('moodle_id' => $moodle_id, 'error' => $updated->get_error_message()));
                return null;
            }

            $term_id = (int) $existing->term_id;
            $created = false;
        } else {
            $inserted = wp_insert_term(
                $name,
                'product_cat',
                array(
                    'slug'   => sanitize_title($slug_seed),
                    'parent' => $parent_term_id,
                )
            );

            if (is_wp_error($inserted) || empty($inserted['term_id'])) {
                Woo_OTEC_Moodle_Logger::error('No se pudo crear categoría', array('moodle_id' => $moodle_id));
                return null;
            }

            $term_id = (int) $inserted['term_id'];
            $created = true;
        }

        update_term_meta($term_id, 'moodle_id', $moodle_id);
        update_term_meta($term_id, 'moodle_parent_id', isset($category->parent) ? (int) $category->parent : 0);

        return $created;
    }

    private function get_category_by_moodle_id(int $moodle_id): WP_Term|false {
        $terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'number'     => 1,
                'meta_query' => array(
                    array(
                        'key'     => 'moodle_id',
                        'value'   => $moodle_id,
                        'compare' => '=',
                    ),
                ),
            )
        );

        return (!is_wp_error($terms) && !empty($terms[0])) ? $terms[0] : false;
    }

    private function create_product(object $course, array $course_data): int {
        $product = new WC_Product_Simple();
        $this->hydrate_product($product, $course, $course_data);

        try {
            $product_id = $product->save();
        } catch (Throwable $error) {
            $existing_id = $this->find_existing_product_for_course($course);
            if ($existing_id > 0) {
                $this->update_product($existing_id, $course, $course_data);
                Woo_OTEC_Moodle_Logger::info('Producto existente recuperado durante creación', array(
                    'moodle_id'  => (int) ($course->id ?? 0),
                    'product_id' => $existing_id,
                    'error'      => $error->getMessage(),
                ));
                return -$existing_id;
            }

            Woo_OTEC_Moodle_Logger::error('No se pudo crear producto para curso Moodle', array(
                'moodle_id' => (int) ($course->id ?? 0),
                'error'     => $error->getMessage(),
            ));
            return 0;
        }

        if ($product_id > 0) {
            // Asegurar publicación forzada
            wp_update_post(array('ID' => $product_id, 'post_status' => 'publish'));
            
            $this->update_product_meta($product_id, $course, $course_data);
            $this->assign_product_image($product_id, $course);
        }

        return (int) $product_id;
    }

    private function update_product(int $product_id, object $course, array $course_data): void {
        $product = wc_get_product($product_id);
        if (!$product instanceof WC_Product) {
            return;
        }

        $this->hydrate_product($product, $course, $course_data);
        $product->save();
        
        // Asegurar publicación forzada tras actualización
        wp_update_post(array('ID' => $product_id, 'post_status' => 'publish'));

        $this->update_product_meta($product_id, $course, $course_data);
        $this->assign_product_image($product_id, $course);
    }

    private function hydrate_product(WC_Product $product, object $course, array $course_data): void {
        $existing_name = $product->get_name();
        $new_name = trim((string) ($course_data['post_title'] ?? $course->fullname ?? ''));
        $product->set_name($new_name !== '' ? $new_name : ($existing_name !== '' ? $existing_name : __('Moodle course', 'woo-otec-moodle')));

        $product->set_slug('moodle-course-' . (int) $course->id);

        $existing_description = (string) $product->get_description();
        $new_description = trim((string) ($course_data['post_content'] ?? $course->summary ?? ''));
        $description_to_set = $new_description !== '' ? $new_description : $existing_description;
        $product->set_description($description_to_set);

        $short_desc = wp_trim_words(wp_strip_all_tags($description_to_set), 30);
        if ($short_desc === '') {
            $short_desc = (string) $product->get_short_description();
        }
        $product->set_short_description($short_desc);
        
        // Forzar publicación del producto
        $product->set_status('publish');
        
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_catalog_visibility('visible');
        if (trim((string) $product->get_sku()) === '') {
            $product->set_sku($this->build_course_sku($course));
        }

        if ($product->get_id() <= 0 || trim((string) $product->get_regular_price()) === '') {
            $product->set_regular_price((string) Woo_OTEC_Moodle_Core::instance()->get_option('default_price', '49000'));
        }

        $product->set_attributes($this->build_product_attributes($product, $course_data));

        $category_ids = $this->resolve_category_ids((int) ($course->categoryid ?? 0));
        if (!empty($category_ids)) {
            $product->set_category_ids($category_ids);
        }
    }

    private function get_course_description(object $course): string {
        $description = isset($course->summary) ? wp_kses_post((string) $course->summary) : '';
        if ($description !== '') {
            return $description;
        }

        return (string) Woo_OTEC_Moodle_Core::instance()->get_option('fallback_description', __('Course synchronized automatically from Moodle.', 'woo-otec-moodle'));
    }

    private function resolve_category_ids(int $moodle_category_id): array {
        if ($moodle_category_id <= 0) {
            return array();
        }

        $term = $this->get_category_by_moodle_id($moodle_category_id);
        return $term ? array((int) $term->term_id) : array();
    }

    private function update_product_meta(int $product_id, object $course, array $course_data): void {
        update_post_meta($product_id, '_moodle_id', (int) $course->id);
        update_post_meta($product_id, 'moodle_course_id', (int) $course->id);
        update_post_meta($product_id, '_pcc_synced', 1);
        update_post_meta($product_id, '_moodle_category_id', isset($course->categoryid) ? (int) $course->categoryid : 0);
        if (!empty($course->shortname)) {
            update_post_meta($product_id, '_course_shortname', sanitize_text_field((string) $course->shortname));
        }
        if (!empty($course->idnumber)) {
            update_post_meta($product_id, '_course_code', sanitize_text_field((string) $course->idnumber));
        }
        if (!empty($course->lang)) {
            update_post_meta($product_id, '_course_language', sanitize_text_field((string) $course->lang));
        }
        if (isset($course->visible)) {
            update_post_meta($product_id, '_course_visibility', ((int) $course->visible) === 1 ? 'visible' : 'hidden');
        }
        if (!empty($course->categoryname)) {
            update_post_meta($product_id, '_course_category_name', sanitize_text_field((string) $course->categoryname));
        }

        // Metadatos base requeridos en la vista single-product.
        $start = (string) ($course_data['_start_date'] ?? $course_data['startdate'] ?? '');
        $end = (string) ($course_data['_end_date'] ?? $course_data['enddate'] ?? '');
        $teacher = (string) ($course_data['_instructor'] ?? $course_data['teacher'] ?? '');
        $modality = (string) ($course_data['_modality'] ?? $course_data['modality'] ?? '');
        $format = (string) ($course_data['_course_format'] ?? $course_data['format'] ?? '');
        $sections = (string) ($course_data['_sections_count'] ?? $course_data['sections_count'] ?? '');
        $certificate = (string) ($course_data['_certificate_available'] ?? $course_data['certificate_available'] ?? '');

        if ($start !== '') {
            update_post_meta($product_id, '_start_date', $start);
        }
        if ($end !== '') {
            update_post_meta($product_id, '_end_date', $end);
        }
        if ($teacher !== '') {
            update_post_meta($product_id, '_instructor', $teacher);
        }
        if ($modality !== '') {
            update_post_meta($product_id, '_modality', $modality);
        }
        if ($format !== '') {
            update_post_meta($product_id, '_course_format', $format);
        }
        if ($sections !== '') {
            update_post_meta($product_id, '_sections_count', $sections);
        }
        if ($certificate !== '') {
            update_post_meta($product_id, '_certificate_available', $certificate === 'yes' ? 'yes' : 'no');
        }

        foreach ($course_data as $key => $value) {
            if (!str_starts_with($key, 'post_')) {
                if ($value === '' || $value === null) {
                    continue;
                }
                update_post_meta($product_id, $key, $value);
            }
        }
    }

    private function build_course_sync_data(object $course, int $existing_product_id = 0): array {
        $description = $this->get_course_description($course);
        if ($description === '' && $existing_product_id > 0) {
            $existing_product = wc_get_product($existing_product_id);
            if ($existing_product instanceof WC_Product) {
                $description = (string) $existing_product->get_description();
            }
        }
        
        // Preferir el profesor del objeto (wizard) si está presente
        if (!empty($course->teacher) && $course->teacher !== 'No asignado' && !str_contains($course->teacher, 'muchos cursos')) {
            $teacher = $course->teacher;
        } else {
            $teacher_names = Woo_OTEC_Moodle_API::instance()->get_course_teachers((int) $course->id);
            $teacher = !empty($teacher_names)
                ? implode(', ', $teacher_names)
                : (string) Woo_OTEC_Moodle_Core::instance()->get_option('default_instructor', __('Not assigned', 'woo-otec-moodle'));
        }

        $start_timestamp = isset($course->startdate) ? (is_numeric($course->startdate) ? (int) $course->startdate : strtotime($course->startdate)) : 0;
        $end_timestamp = isset($course->enddate) ? (is_numeric($course->enddate) ? (int) $course->enddate : strtotime($course->enddate)) : 0;

        $format_raw = (string) ($course->format ?? 'topics');
        $format_map = array('topics' => 'Por temas', 'weeks' => 'Semanal', 'singleactivity' => 'Actividad única', 'social' => 'Social');
        $format_nice = $format_map[$format_raw] ?? ucfirst($format_raw);

        $sections_count = 0;
        if (isset($course->numsections)) {
            $sections_count = (int) $course->numsections;
        } elseif (isset($course->sections)) {
            $sections_count = max(0, (int) $course->sections);
        }

        $certificate_enabled = isset($course->certificate_enabled) ? (string) $course->certificate_enabled : '';

        $data = array(
            'fullname'  => (string) ($course->fullname ?? ''),
            'summary'   => $description,
            'startdate' => $start_timestamp > 0 ? $start_timestamp : '',
            'enddate'   => $end_timestamp > 0 ? $end_timestamp : '',
            'teacher'   => sanitize_text_field($teacher),
            'duration'  => (string) ($course->duration ?? ''),
            'modality'  => ucfirst((string) ($course->modality ?? 'online')),
            'format'    => $format_nice,
            'sections_count' => $sections_count > 0 ? (string) $sections_count : '',
            'certificate_available' => $certificate_enabled === 'yes' ? 'yes' : 'no',
            'course_shortname' => (string) ($course->shortname ?? ''),
            'course_code' => (string) ($course->idnumber ?? ''),
            'course_language' => (string) ($course->lang ?? ''),
            'course_visibility' => isset($course->visible) ? (((int) $course->visible) === 1 ? 'visible' : 'hidden') : '',
            'course_category_name' => (string) ($course->categoryname ?? ''),
            'sence_code'=> '',
            'total_hours'=> '',
        );

        $mapped = Woo_OTEC_Moodle_Mapper::instance()->map_course_data($data);

        if ($existing_product_id > 0) {
            $existing_product = wc_get_product($existing_product_id);
            foreach ($mapped as $key => $value) {
                if ($value !== '' && $value !== null) {
                    continue;
                }

                if ($key === 'post_title' && $existing_product instanceof WC_Product) {
                    $mapped[$key] = (string) $existing_product->get_name();
                    continue;
                }

                if ($key === 'post_content' && $existing_product instanceof WC_Product) {
                    $mapped[$key] = (string) $existing_product->get_description();
                    continue;
                }

                if (!str_starts_with($key, 'post_')) {
                    $stored = get_post_meta($existing_product_id, $key, true);
                    if (is_scalar($stored) && (string) $stored !== '') {
                        $mapped[$key] = (string) $stored;
                    }
                }
            }
        }

        return $mapped;
    }

    private function build_product_attributes(WC_Product $product, array $course_data): array {
        $mappings = Woo_OTEC_Moodle_Mapper::instance()->get_mappings();
        $managed_targets = array_column($mappings, 'target');

        $attributes = array();
        foreach ($product->get_attributes() as $key => $attribute) {
            if (!$attribute instanceof WC_Product_Attribute) {
                continue;
            }

            $target = $attribute->get_name();
            if (in_array($target, $managed_targets, true)) {
                continue;
            }

            $attributes[$key] = $attribute;
        }

        $position = count($attributes);
        foreach ($mappings as $moodle_key => $config) {
            $target = $config['target'];
            $label = $config['label'];
            $value = $course_data[$target] ?? '';

            if ($value === '' || str_starts_with($target, 'post_')) {
                continue;
            }

            $attribute = new WC_Product_Attribute();
            $attribute->set_id(0);
            $attribute->set_name($label);
            $attribute->set_options(array($value));
            $attribute->set_visible(true);
            $attribute->set_variation(false);
            $attribute->set_position($position++);

            $attributes[sanitize_title($label)] = $attribute;
        }

        return $attributes;
    }

    /**
     * Asigna la imagen de portada al producto basándose en el asistente o Moodle.
     * Sigue estándares expertos de WordPress para descarga y registro de medios.
     *
     * @param int    $product_id ID del producto WooCommerce.
     * @param object $course     Datos del curso provenientes de Moodle.
     */
    private function assign_product_image(int $product_id, object $course): void {
        $attachment_id = 0;
        $existing_thumbnail_id = (int) get_post_thumbnail_id($product_id);
        
        // 1. Prioridad: Selección manual en el asistente (Gallery ID)
        // Si el usuario eligió una imagen específica, la respetamos sobre el scraping.
        $selected_image_id = (int) ($course->image_id ?? 0);
        if ($selected_image_id > 0) {
            $this->set_product_image($product_id, $selected_image_id);
            update_post_meta($product_id, '_pcc_moodle_image_source', 'manual:' . $selected_image_id);
            Woo_OTEC_Moodle_Logger::info('Imagen asignada manualmente vía asistente', array('product_id' => $product_id, 'image_id' => $selected_image_id));
            return;
        }

        // 2. Extracción y descarga desde Moodle
        // Buscamos la URL de la imagen en los campos y el resumen (summary) del curso.
        $image_url = $this->find_moodle_image_url($course);
        
        if ($image_url !== '') {
            $normalized_url = 'moodle:' . $this->normalize_moodle_url($image_url);
            
            // OPTIMIZACIÓN: Si el producto ya tiene esta misma imagen de Moodle, no descargamos de nuevo.
            $current_source_url = (string) get_post_meta($product_id, '_pcc_moodle_image_source', true);
            if ($existing_thumbnail_id > 0 && $current_source_url === $normalized_url) {
                return;
            }

            // Descarga segura de la imagen remota a la biblioteca de medios de WordPress.
            $attachment_id = $this->download_remote_image($image_url, $product_id);
            
            if ($attachment_id > 0) {
                // LIMPIEZA: Si reemplazamos una imagen anterior descargada por nosotros, la borramos para no llenar el disco.
                if ($existing_thumbnail_id > 0 && $existing_thumbnail_id !== $attachment_id) {
                    $this->cleanup_old_attachment($existing_thumbnail_id);
                }

                $this->set_product_image($product_id, $attachment_id);
                update_post_meta($product_id, '_pcc_moodle_image_source', $normalized_url);
                Woo_OTEC_Moodle_Logger::info('Imagen de Moodle vinculada correctamente', array('product_id' => $product_id, 'source' => $normalized_url));
                return;
            }
        }

        // 3. Fallback: Imagen por defecto configurada en el plugin
        // Si no se encontró imagen en Moodle, usamos la que el usuario definió como "Fallback".
        $default_image_id = (int) Woo_OTEC_Moodle_Core::instance()->get_option('default_image_id', 0);
        
        if ($default_image_id > 0) {
            // Solo actualizamos si la imagen actual es diferente a la de defecto.
            if ($existing_thumbnail_id !== $default_image_id) {
                if ($existing_thumbnail_id > 0) {
                    $this->cleanup_old_attachment($existing_thumbnail_id);
                }
                
                $this->set_product_image($product_id, $default_image_id);
                update_post_meta($product_id, '_pcc_moodle_image_source', 'default:' . $default_image_id);
            }
            return;
        }

        // 4. Si el producto ya tiene algo (manual en WP), lo mantenemos ante fallos de scraping.
        if ($existing_thumbnail_id > 0) {
            Woo_OTEC_Moodle_Logger::info('No se halló nueva imagen en Moodle, manteniendo la actual del producto', array('product_id' => $product_id));
        }
    }
    private function set_product_image(int $product_id, int $attachment_id): void {
        if ($attachment_id <= 0) {
            return;
        }

        set_post_thumbnail($product_id, $attachment_id);

        $product = wc_get_product($product_id);
        if ($product instanceof WC_Product) {
            $product->set_image_id($attachment_id);
            $product->save();
        }
    }

    private function cleanup_old_attachment(int $attachment_id): void {
        if ($attachment_id <= 0) {
            return;
        }

        // NO eliminar si es la imagen por defecto global
        $default_image_id = (int) Woo_OTEC_Moodle_Core::instance()->get_option('default_image_id', 0);
        if ($attachment_id === $default_image_id) {
            return;
        }

        // Verificar si la imagen tiene nuestro meta de origen (fue descargada por nosotros)
        $source_url = get_post_meta($attachment_id, '_pcc_moodle_source_url', true);
        if (!$source_url) {
            return; // No la eliminamos si no estamos seguros de que es nuestra
        }

        // Verificar si otros productos la usan antes de borrarla
        $query = new WP_Query(array(
            'post_type'  => 'product',
            'meta_query' => array(
                array(
                    'key'   => '_thumbnail_id',
                    'value' => $attachment_id,
                )
            ),
            'posts_per_page' => 2, // Basta con saber si hay más de 1
            'fields'         => 'ids',
        ));

        if ($query->found_posts > 1) {
            return; // Otros cursos la están usando
        }

        wp_delete_attachment($attachment_id, true);
    }

    public function find_moodle_image_url(object $course): string {
        $token = Woo_OTEC_Moodle_API::instance()->get_token();
        $course_name = $course->fullname ?? 'Unknown';

        foreach (array('courseimage', 'imageurl', 'image') as $field) {
            if (!empty($course->{$field}) && is_string($course->{$field})) {
                $url = trim((string) $course->{$field});
                if ($url !== '') {
                    if (!str_contains($url, 'token=')) {
                        $url = add_query_arg('token', $token, $url);
                    }
                    
                    if ($this->is_valid_course_image($url)) {
                        Woo_OTEC_Moodle_Logger::info("Imagen hallada en campo {$field}", array('course' => $course_name, 'url' => $url));
                        return $url;
                    } else {
                        Woo_OTEC_Moodle_Logger::info("Imagen descartada en campo {$field} (logo o marcador de posición)", array('course' => $course_name, 'url' => $url));
                    }
                }
            }
        }

        if (!empty($course->overviewfiles) && is_array($course->overviewfiles)) {
            foreach ($course->overviewfiles as $file) {
                $file_obj = is_array($file) ? (object) $file : $file;
                if (!is_object($file_obj) || empty($file_obj->fileurl)) {
                    continue;
                }

                $mimetype = !empty($file_obj->mimetype) ? (string) $file_obj->mimetype : '';
                if ($mimetype === '' || str_starts_with($mimetype, 'image/')) {
                    $url = (string) $file_obj->fileurl;
                    if (!str_contains($url, 'token=')) {
                        $url = add_query_arg('token', $token, $url);
                    }
                    if ($this->is_valid_course_image($url)) {
                        Woo_OTEC_Moodle_Logger::info("Imagen hallada en overviewfiles", array('course' => $course_name, 'url' => $url));
                        return $url;
                    } else {
                        Woo_OTEC_Moodle_Logger::info("Imagen descartada en overviewfiles (logo o marcador de posición)", array('course' => $course_name, 'url' => $url));
                    }
                }
            }
        }
    
        if (!empty($course->summary)) {
            // Busqueda de URLs de pluginfile (estandar o webservice) que apunten a imagenes de curso
            // Detectamos tanto /pluginfile.php/ como /webservice/pluginfile.php/
            $pattern = '/(https?:\/\/[^\s"\'<>]+(?:webservice\/)?pluginfile\.php\/[0-9]+\/course\/(?:overviewfiles|summary)\/[^\s"\'<>]+)/i';
            
            if (preg_match_all($pattern, (string) $course->summary, $matches)) {
                foreach ($matches[1] as $url) {
                    $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
                    // Limpiar posibles residuos de etiquetas HTML o comillas que el regex pueda haber arrastrado
                    $url = strtok($url, '"');
                    $url = strtok($url, "'");
                    
                    if (!str_contains($url, 'token=')) {
                        $url = add_query_arg('token', $token, $url);
                    }
                    
                    if ($this->is_valid_course_image($url)) {
                        Woo_OTEC_Moodle_Logger::info("Imagen detectada mediante patrón real en summary", array('course' => $course_name, 'url' => $url));
                        return $url;
                    }
                }
            }
            // Fallback genérico para imágenes en el resumen
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $course->summary, $matches)) {
                $url = html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
                if (str_contains($url, 'pluginfile.php') && !str_contains($url, 'token=')) {
                    $url = add_query_arg('token', $token, $url);
                }
                if ($this->is_valid_course_image($url)) {
                    Woo_OTEC_Moodle_Logger::info("Imagen hallada en el resumen (summary)", array('course' => $course_name, 'url' => $url));
                    return $url;
                } else {
                    Woo_OTEC_Moodle_Logger::info("Imagen descartada en summary (logo o marcador de posición)", array('course' => $course_name, 'url' => $url));
                }
            }
        }

        Woo_OTEC_Moodle_Logger::info("Buscando imagen en la página del curso (fallback)", array('course' => $course_name));
        $page_image = $this->fetch_course_page_image_url($course);
        if ($page_image !== '') {
            return $page_image;
        }

        return '';
    }
    private function fetch_course_page_image_url(object $course): string {
        $course_id = (int) ($course->id ?? 0);
        if ($course_id <= 0) {
            return '';
        }

        $base = Woo_OTEC_Moodle_API::instance()->get_moodle_url();
        if ($base === '') {
            return '';
        }

        $url = rtrim($base, '/') . '/course/view.php?id=' . $course_id;
        $parsed_base = wp_parse_url($base);
        $parsed_url = wp_parse_url($url);

        if (!is_array($parsed_base) || !is_array($parsed_url)) {
            return '';
        }

        $base_host = (string) ($parsed_base['host'] ?? '');
        $url_host = (string) ($parsed_url['host'] ?? '');
        if ($base_host === '' || $url_host === '' || $base_host !== $url_host) {
            return '';
        }

        $response = wp_remote_get($url, array('timeout' => 20));
        if (is_wp_error($response)) {
            return '';
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ($body === '') {
            return '';
        }

        $patterns = array(
            '/<div[^>]*class=["\'][^"\']*available-img[^"\']*["\'][^>]*>.*?<img[^>]+src=["\']([^"\']+)["\']/is',
            '/<img[^>]+class=["\'][^"\']*courseimage[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/is',
            '/style=["\'][^"\']*background-image\s*:\s*url\s*\(\s*["\']?([^"\'\)\s]+)["\']?\s*\)[^"\']*["\']/is',
            '/<img[^>]+src=["\']([^"\']+pluginfile\\.php[^"\']+)["\']/is',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $img = html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
                if ($img === '') {
                    continue;
                }

                if (str_starts_with($img, '//')) {
                    $scheme = (string) ($parsed_base['scheme'] ?? 'https');
                    $img = $scheme . ':' . $img;
                } elseif (str_starts_with($img, '/')) {
                    $img = rtrim($base, '/') . $img;
                }

                if (str_contains($img, 'pluginfile.php') && !str_contains($img, 'token=')) {
                    $img = add_query_arg('token', Woo_OTEC_Moodle_API::instance()->get_token(), $img);
                }

                if ($this->is_valid_course_image($img)) {
                    return $img;
                }
            }
        }

        return '';
    }

    /**
     * Descarga de forma segura una imagen externa y la registra en la biblioteca de medios.
     * Implementa cacheo por URL para evitar duplicados en el servidor.
     *
     * @param string $url        URL absoluta de la imagen en Moodle.
     * @param int    $product_id ID del producto para asociación de contexto.
     * @return int ID del attachment creado o existente. 0 en caso de fallo.
     */
    private function download_remote_image(string $url, int $product_id): int {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // 1. Verificar si ya hemos descargado esta URL previamente (Optimización)
        $existing_attachment = $this->find_attachment_id_by_source_url($url);
        if ($existing_attachment > 0) {
            return $existing_attachment;
        }

        // 2. Descarga segura mediante el estándar de WordPress (download_url)
        // Maneja automáticamente redirecciones, certificados SSL y archivos temporales.
        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) {
            Woo_OTEC_Moodle_Logger::error('Fallo en descarga segura (download_url)', array('url' => $url, 'error' => $tmp->get_error_message()));
            return $this->download_remote_image_fallback($url, $product_id);
        }

        // 3. Preparación del archivo y validación de extensión
        $filename = wp_basename((string) wp_parse_url($url, PHP_URL_PATH));
        $file_info = wp_check_filetype($filename);
        
        if (empty($file_info['ext'])) {
            $mime_to_ext = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp');
            $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : '';
            $ext = $mime_to_ext[$mime] ?? 'jpg';
            $filename = pathinfo($filename, PATHINFO_FILENAME) . '.' . $ext;
        }

        // 4. Registro en la Biblioteca de Medios (Attachment)
        // media_handle_sideload inserta el attachment y genera metadatos/miniaturas.
        add_filter('user_has_cap', function($caps) { $caps['upload_files'] = true; return $caps; });
        
        $attachment_id = media_handle_sideload(
            array('name' => $filename, 'tmp_name' => $tmp),
            $product_id
        );

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            Woo_OTEC_Moodle_Logger::error('Fallo al registrar imagen en WordPress', array('error' => $attachment_id->get_error_message()));
            return $this->download_remote_image_fallback($url, $product_id);
        }

        // 5. Cachear el origen para futuras sincronizaciones
        update_post_meta((int) $attachment_id, '_pcc_moodle_source_url', $this->normalize_moodle_url($url));

        return (int) $attachment_id;
    }

    private function download_remote_image_fallback(string $url, int $product_id): int {
        $existing_attachment = $this->find_attachment_id_by_source_url($url);
        if ($existing_attachment > 0) {
            return $existing_attachment;
        }

        $response = wp_remote_get($url, array('timeout' => 30, 'redirection' => 5));
        if (is_wp_error($response)) {
            Woo_OTEC_Moodle_Logger::error('No se pudo descargar imagen remota (fallback)', array('url' => $url, 'error' => $response->get_error_message()));
            return 0;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            Woo_OTEC_Moodle_Logger::error('Respuesta HTTP inválida al descargar imagen', array('url' => $url, 'status' => $code));
            return 0;
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ($body === '') {
            Woo_OTEC_Moodle_Logger::error('Respuesta vacía al descargar imagen', array('url' => $url));
            return 0;
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (is_string($content_type) && $content_type !== '' && !str_starts_with(strtolower($content_type), 'image/')) {
            Woo_OTEC_Moodle_Logger::error('Contenido no es imagen', array('url' => $url, 'content_type' => $content_type));
            return 0;
        }

        $filename = wp_basename((string) wp_parse_url($url, PHP_URL_PATH));
        if ($filename === '') {
            $filename = 'moodle-image-' . time() . '.jpg';
        }

        $upload = wp_upload_bits($filename, null, $body);
        if (!empty($upload['error'])) {
            Woo_OTEC_Moodle_Logger::error('No se pudo guardar imagen en uploads', array('url' => $url, 'error' => $upload['error']));
            return 0;
        }

        $filetype = wp_check_filetype($upload['file']);
        $attachment = array(
            'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
            'post_title'     => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $upload['file'], $product_id);
        if (is_wp_error($attach_id)) {
            Woo_OTEC_Moodle_Logger::error('No se pudo crear adjunto de imagen', array('url' => $url, 'error' => $attach_id->get_error_message()));
            return 0;
        }

        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
        update_post_meta((int) $attach_id, '_pcc_moodle_source_url', $this->normalize_moodle_url($url));

        return (int) $attach_id;
    }

    private function find_attachment_id_by_source_url(string $url): int {
        $url = $this->normalize_moodle_url($url);
        if ($url === '') {
            return 0;
        }

        $query = new WP_Query(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => '_pcc_moodle_source_url',
                        'value'   => $url,
                        'compare' => '=',
                    ),
                ),
            )
        );

        return $query->have_posts() ? (int) $query->posts[0] : 0;
    }

    private function normalize_moodle_url(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // Remover token de Moodle
        $url = remove_query_arg('token', $url);

        // Remover revisiones si existen (pueden cambiar con cada update de Moodle)
        $url = remove_query_arg('rev', $url);
        $url = remove_query_arg('revision', $url);

        return esc_url_raw($url);
    }

    private function is_valid_course_image(string $url): bool {
        $url = strtolower($url);
        
        // Excepciones de sistema obligatorias (bloqueo real de logos y UI)
        $strict_blacklist = array(
            'favicon',
            'moodle-logo',
            'moodlelogo',
            'compact_logo',
            'white_logo',
            'core_admin/logo',
            'user/picture',
            'pix/i/',       // Iconos internos
            'pix/t/',       // Iconos de temas
            'pix/f/',       // Iconos de archivos
            'theme/image.php/boost/core', // Logo por defecto de Boost
            'course/generated/', // Marcadores de posición de Moodle (SVG birrete)
        );

        foreach ($strict_blacklist as $term) {
            if (str_contains($url, $term)) {
                return false;
            }
        }

        // Si es un SVG genérico de Moodle llamado "course.svg" o similar al final
        if (str_ends_with($url, '/course.svg') || str_contains($url, '/generated/course.svg')) {
            return false;
        }

        return true;
    }

    private function find_existing_product_for_course(object $course): int {
        $moodle_id = (int) ($course->id ?? 0);
        if ($moodle_id > 0) {
            $product_id = $this->find_product_id($moodle_id);
            if ($product_id > 0) {
                return $product_id;
            }
        }

        $new_sku_product = $this->find_product_id_by_new_sku_suffix($moodle_id);
        if ($new_sku_product > 0) {
            return $new_sku_product;
        }

        // Compatibilidad hacia atras: formato historico.
        return $this->find_product_id_by_sku('MOODLE-' . $moodle_id);
    }

    private function find_product_id_by_sku(string $sku): int {
        $sku = trim($sku);
        if ($sku === '' || !function_exists('wc_get_product_id_by_sku')) {
            return 0;
        }

        return (int) wc_get_product_id_by_sku($sku);
    }

    private function find_product_id_by_new_sku_suffix(int $course_id): int {
        if ($course_id <= 0) {
            return 0;
        }

        $query = new WP_Query(
            array(
                'post_type'      => 'product',
                'post_status'    => array('publish', 'draft', 'private'),
                'posts_per_page' => 50,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => '_sku',
                        'value'   => 'OTEC-%' . $course_id,
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );

        if (!$query->have_posts()) {
            return 0;
        }

        $pattern = '/^OTEC-\d{4}' . preg_quote((string) $course_id, '/') . '$/';
        foreach ($query->posts as $candidate_id) {
            $sku = (string) get_post_meta((int) $candidate_id, '_sku', true);
            if ($sku !== '' && preg_match($pattern, $sku)) {
                return (int) $candidate_id;
            }
        }

        return 0;
    }

    private function build_course_sku(object $course): string {
        $course_id = (int) ($course->id ?? 0);
        $timestamp = current_time('timestamp');
        return 'OTEC-' . wp_date('ym', $timestamp) . $course_id;
    }

    public function update_last_sync(array $result): void {
        $payload = array(
            'timestamp'            => current_time('mysql'),
            'status'               => sanitize_key((string) ($result['status'] ?? 'error')),
            'message'              => sanitize_text_field((string) ($result['message'] ?? '')),
            'categories_created'   => (int) ($result['categories_created'] ?? 0),
            'categories_updated'   => (int) ($result['categories_updated'] ?? 0),
            'products_created'     => (int) ($result['products_created'] ?? 0),
            'products_updated'     => (int) ($result['products_updated'] ?? 0),
        );

        Woo_OTEC_Moodle_Core::instance()->update_option('last_sync', $payload);
    }

    /**
     * Función reutilizable y pública para asignar una imagen destacada desde una URL externa.
     * Cumple con la especificación experta de OTEC.
     *
     * @param int    $product_id ID del producto WooCommerce.
     * @param string $image_url  URL absoluta de la imagen.
     * @return int ID del attachment creado o 0 en caso de error.
     */
    public function asignar_imagen_destacada_desde_url(int $product_id, string $image_url): int {
        if ($product_id <= 0 || empty($image_url)) {
            return 0;
        }

        $attachment_id = $this->download_remote_image($image_url, $product_id);
        if ($attachment_id > 0) {
            $this->set_product_image($product_id, $attachment_id);
            update_post_meta($product_id, '_pcc_moodle_image_source', 'moodle:' . $this->normalize_moodle_url($image_url));
        }

        return $attachment_id;
    }
}



