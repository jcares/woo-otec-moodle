<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestiona todas las peticiones AJAX del panel de administración.
 * Extraído de Woo_OTEC_Moodle_Admin para separar responsabilidades.
 */
final class Woo_OTEC_Moodle_Ajax_Handler {
    private static ?Woo_OTEC_Moodle_Ajax_Handler $instance = null;

    public static function instance(): Woo_OTEC_Moodle_Ajax_Handler {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function boot(): void {
        add_action('admin_post_woo_otec_moodle_run_sync', array($this, 'handle_manual_sync'));
        add_action('wp_ajax_woo_otec_moodle_email_preview', array($this, 'handle_email_preview'));
        add_action('wp_ajax_woo_otec_moodle_send_test_email', array($this, 'handle_send_test_email'));
        add_action('wp_ajax_woo_otec_moodle_template_fields', array($this, 'handle_template_fields_ajax'));
        add_action('wp_ajax_woo_otec_moodle_save_template_config', array($this, 'handle_save_template_config'));
        add_action('wp_ajax_woo_otec_moodle_get_categories', array($this, 'handle_get_categories'));
        add_action('wp_ajax_woo_otec_moodle_get_teachers', array($this, 'handle_get_teachers'));
        add_action('wp_ajax_woo_otec_moodle_get_courses', array($this, 'handle_get_courses'));
        add_action('wp_ajax_woo_otec_moodle_execute_wizard_sync', array($this, 'handle_execute_wizard_sync'));
        add_action('wp_ajax_woo_otec_moodle_test_sso', array($this, 'handle_test_sso'));
        add_action('wp_ajax_woo_otec_moodle_export_logs', array($this, 'handle_export_logs'));
        add_action('wp_ajax_woo_otec_moodle_export_config', array($this, 'handle_export_config'));
        add_action('wp_ajax_woo_otec_moodle_import_config', array($this, 'handle_import_config'));
    }

    /**
     * Sincronización Manual
     * Intercepta la peticion admin_post directa para ejecutar el volcado hacia Moodle.
     */

    public function handle_manual_sync(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized request.', 'woo-otec-moodle'));
        }

        check_admin_referer('woo_otec_moodle_run_sync');

        $result   = Woo_OTEC_Moodle_Sync::instance()->run(true);
        $redirect = add_query_arg(
            array(
                'page'   => 'woo-otec-moodle',
                'tab'    => 'sync',
                'status' => $result['status'],
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Módulo de Correos
     * Funcionalidad AJAX vinculada al previsualizador base y herramientas de testeo.
     */

    public function handle_email_preview(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized request.', 'woo-otec-moodle')), 403);
        }

        check_ajax_referer('woo_otec_moodle_email_tools', 'nonce');

        $html = Woo_OTEC_Moodle_Enroll::instance()->render_email_preview();
        wp_send_json_success(array('html' => $html));
    }

    public function handle_send_test_email(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized request.', 'woo-otec-moodle')), 403);
        }

        check_ajax_referer('woo_otec_moodle_email_tools', 'nonce');

        $recipient = sanitize_email((string) wp_unslash($_POST['recipient'] ?? ''));
        $result    = Woo_OTEC_Moodle_Enroll::instance()->send_test_email($recipient);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success(array('message' => esc_html__('Test email sent successfully.', 'woo-otec-moodle')));
    }

    /**
     * Gestión de Plantillas de Campos
     * Administración de las preferencias visuales del frontend desde la vista de opciones.
     */

    public function handle_template_fields_ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized request.', 'woo-otec-moodle')), 403);
        }

        check_ajax_referer('woo_otec_moodle_template_fields', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if ($product_id <= 0) {
            wp_send_json_error(array('message' => esc_html__('Invalid product selected.', 'woo-otec-moodle')), 400);
        }

        $selected_fields = (array) Woo_OTEC_Moodle_Core::instance()->get_option('template_fields', array());
        $html            = Woo_OTEC_Moodle_Settings::instance()->render_template_fields_markup($product_id, $selected_fields, false);
        $allowed         = array(
            'label' => array('class' => true),
            'input' => array('type' => true, 'id' => true, 'name' => true, 'value' => true, 'checked' => true),
            'span'  => array(),
            'p'     => array('class' => true),
        );

        wp_send_json_success(array('html' => wp_kses($html, $allowed)));
    }

    public function handle_save_template_config(): void {
        $this->assert_ajax_permissions();
        check_ajax_referer('woo_otec_moodle_template_fields', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if ($product_id <= 0) {
            wp_send_json_error(array('message' => esc_html__('Invalid product selected.', 'woo-otec-moodle')), 400);
        }

        Woo_OTEC_Moodle_Core::instance()->update_option('template_reference', $product_id);

        $selected_fields = null;
        if (isset($_POST['selected_fields'])) {
            $incoming = (array) wp_unslash($_POST['selected_fields']);
            $selected_fields = array_values(array_filter(array_map('sanitize_text_field', $incoming)));
            Woo_OTEC_Moodle_Core::instance()->update_option('template_fields', $selected_fields);
        } else {
            $selected_fields = (array) Woo_OTEC_Moodle_Core::instance()->get_option('template_fields', array());
        }

        $fields_html = Woo_OTEC_Moodle_Settings::instance()->render_template_fields_markup($product_id, $selected_fields, false);
        $allowed = array(
            'div'   => array('class' => true),
            'label' => array('class' => true),
            'input' => array('type' => true, 'id' => true, 'name' => true, 'value' => true, 'checked' => true, 'data-meta-key' => true, 'data-meta-label' => true),
            'span'  => array(),
            'p'     => array('class' => true),
        );

        $mapping_values = $this->get_mapping_example_values($product_id);
        $preview_html = $this->build_template_preview_html($product_id, $selected_fields);

        wp_send_json_success(array(
            'message'        => esc_html__('Configuration updated.', 'woo-otec-moodle'),
            'fields_html'    => wp_kses($fields_html, $allowed),
            'mapping_values' => $mapping_values,
            'preview_html'   => $preview_html,
        ));
    }

    private function get_mapping_example_values(int $product_id): array {
        $product = wc_get_product($product_id);
        if (!$product instanceof WC_Product) {
            return array();
        }

        $mapper = Woo_OTEC_Moodle_Mapper::instance();
        $mappings = $mapper->get_mappings();
        $result = array();

        foreach ($mappings as $moodle_key => $config) {
            $target = (string) ($config['target'] ?? '');
            $value = '';
            if ($target === 'post_title') {
                $value = (string) $product->get_name();
            } elseif ($target === 'post_content') {
                $value = wp_trim_words((string) $product->get_description(), 10);
            } else {
                $value = (string) $product->get_meta($target);
                if ($value === '') {
                    $value = (string) $product->get_meta(ltrim($target, '_'));
                }
            }

            $normalized_moodle = sanitize_key((string) $moodle_key);
            $normalized_target = sanitize_key((string) $target);
            $final_value = $value !== '' ? $value : esc_html__('Empty', 'woo-otec-moodle');

            if ($normalized_moodle !== '') {
                $result[$normalized_moodle] = $final_value;
            }
            if ($normalized_target !== '') {
                $result[$normalized_target] = $final_value;
            }
        }

        return $result;
    }

    private function build_template_preview_html(int $product_id, array $selected_fields): string {
        $product = wc_get_product($product_id);
        if (!$product instanceof WC_Product) {
            return '<p class="description">' . esc_html__('The selected course could not be loaded.', 'woo-otec-moodle') . '</p>';
        }

        $labels = Woo_OTEC_Moodle_Settings::instance()->get_meta_labels_map();
        if (empty($selected_fields)) {
            return '<p class="description">' . esc_html__('Select at least one field to view the preview.', 'woo-otec-moodle') . '</p>';
        }

        $html = '<div class="pcc-template-live-list">';
        foreach ($selected_fields as $meta_key) {
            $meta_key = sanitize_text_field((string) $meta_key);
            if ($meta_key === '') {
                continue;
            }

            $label = $labels[$meta_key] ?? ucfirst(str_replace(array('_', '-'), ' ', ltrim($meta_key, '_')));
            $value = (string) $product->get_meta($meta_key);
            if ($value === '' && str_starts_with($meta_key, '_')) {
                $value = (string) $product->get_meta(ltrim($meta_key, '_'));
            }
            $image_url = $this->resolve_template_preview_image($product, $meta_key, $value);

            if ($image_url !== '') {
                $html .= '<div class="pcc-template-live-item is-image">';
                $html .= '<div class="pcc-template-live-image-wrap">';
                $html .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($label) . '">';
                $html .= '</div>';
                $html .= '</div>';
                continue;
            }

            if ($value === '') {
                $value = esc_html__('No value is available for this course.', 'woo-otec-moodle');
            } else {
                $value = $this->format_template_preview_value($value, $meta_key);
            }

            $html .= '<div class="pcc-template-live-item">';
            $html .= '<span>' . esc_html($label) . '</span>';
            $html .= '<strong>' . esc_html($value) . '</strong>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return wp_kses($html, array(
            'div'    => array('class' => true),
            'span'   => array(),
            'strong' => array(),
            'small'  => array(),
            'img'    => array(
                'src' => true,
                'alt' => true,
            ),
            'p'      => array('class' => true),
        ));
    }

    private function resolve_template_preview_image(WC_Product $product, string $meta_key, string $value): string {
        $normalized_key = sanitize_key(ltrim($meta_key, '_'));
        $raw_value = trim($value);

        if ($normalized_key === 'thumbnail_id') {
            $thumb_id = (int) $raw_value;
            return $thumb_id > 0 ? (string) wp_get_attachment_image_url($thumb_id, 'medium') : '';
        }

        if (!in_array($normalized_key, array('pcc_moodle_image_source', 'image', 'image_url', 'courseimage'), true)) {
            return '';
        }

        if ($raw_value === '') {
            $thumb_id = (int) $product->get_image_id();
            return $thumb_id > 0 ? (string) wp_get_attachment_image_url($thumb_id, 'medium') : '';
        }

        if (str_starts_with($raw_value, 'manual:') || str_starts_with($raw_value, 'default:')) {
            $attachment_id = (int) substr($raw_value, strpos($raw_value, ':') + 1);
            return $attachment_id > 0 ? (string) wp_get_attachment_image_url($attachment_id, 'medium') : '';
        }

        if (filter_var($raw_value, FILTER_VALIDATE_URL)) {
            return $raw_value;
        }

        return '';
    }

    private function format_template_preview_value(string $value, string $meta_key = ''): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $date_like_keys = array('_start_date', '_end_date', 'startdate', 'enddate');
        if (in_array($meta_key, $date_like_keys, true) && ctype_digit($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 1000000000 && $timestamp < 4102444800) {
                return wp_date(get_option('date_format'), $timestamp);
            }
        }

        if (ctype_digit($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 1000000000 && $timestamp < 4102444800) {
                return wp_date(get_option('date_format'), $timestamp);
            }
        }

        if (in_array($meta_key, array('_certificate_available', 'certificate_available'), true)) {
            return $value === 'yes' ? 'Si' : 'No';
        }

        if (in_array($meta_key, array('_course_visibility', 'course_visibility'), true)) {
            return $value === 'visible' ? 'Visible' : 'Oculto';
        }

        return $value;
    }

    /**
     * Asistente de Sincronización Interactiva
     * Métodos expuestos para la interfaz paso a paso del wizard.
     */

    public function handle_get_categories(): void {
        $this->assert_ajax_permissions();
        check_ajax_referer('woo_otec_moodle_sync_stage', 'nonce');

        $categories = Woo_OTEC_Moodle_API::instance()->get_categories();
        if (is_wp_error($categories)) {
            wp_send_json_error($categories->get_error_message());
        }

        $formatted = array();
        foreach ($categories as $cat) {
            $cat_name = is_object($cat) ? ($cat->name ?? '') : ($cat['name'] ?? '');
            $cat_id   = is_object($cat) ? ($cat->id ?? 0) : ($cat['id'] ?? 0);

            if (empty($cat_name) || empty($cat_id)) continue;

            $term        = get_term_by('name', $cat_name, 'product_cat');
            $formatted[] = array(
                'id'     => $cat_id,
                'name'   => $cat_name,
                'exists' => $term !== false,
            );
        }

        wp_send_json_success($formatted);
    }

    public function handle_get_teachers(): void {
        $this->assert_ajax_permissions();
        check_ajax_referer('woo_otec_moodle_sync_stage', 'nonce');

        $category_ids = isset($_POST['categories']) ? array_map('absint', (array) $_POST['categories']) : array();
        if (empty($category_ids)) {
            wp_send_json_error(esc_html__('No categories selected.', 'woo-otec-moodle'));
        }

        $teachers          = array();
        $api               = Woo_OTEC_Moodle_API::instance();
        $courses_processed = 0;
        $max_courses       = 50;

        foreach ($category_ids as $cat_id) {
            if ($courses_processed >= $max_courses) break;

            $courses = $api->get_courses_by_category($cat_id);
            if (!is_wp_error($courses)) {
                foreach ($courses as $course) {
                    if ($courses_processed >= $max_courses) break;

                    $course_id = is_object($course) ? ($course->id ?? 0) : ($course['id'] ?? 0);
                    if (!$course_id) continue;

                    $course_teachers = $api->get_course_teachers((int) $course_id);
                    foreach ($course_teachers as $teacher_name) {
                        if (!in_array($teacher_name, $teachers, true)) {
                            $teachers[] = $teacher_name;
                        }
                    }
                    $courses_processed++;
                }
            }
        }

        if ($courses_processed >= $max_courses) {
            $teachers[] = esc_html__('(Detection limited by number of courses)', 'woo-otec-moodle');
        }

        sort($teachers);
        wp_send_json_success($teachers);
    }

    public function handle_get_courses(): void {
        $this->assert_ajax_permissions();
        check_ajax_referer('woo_otec_moodle_sync_stage', 'nonce');
        
        if (function_exists('set_time_limit')) {
            set_time_limit(120);
        }

        $category_ids = isset($_POST['categories']) ? array_map('absint', (array) $_POST['categories']) : array();
        if (empty($category_ids)) {
            wp_send_json_error(esc_html__('No categories selected.', 'woo-otec-moodle'));
        }

        $courses                  = array();
        $api                      = Woo_OTEC_Moodle_API::instance();
        $total_courses_count      = 0;
        $max_courses_for_teachers = 40;

        try {
            foreach ($category_ids as $cat_id) {
                $cat_courses = $api->get_courses_by_category($cat_id);
                if (is_wp_error($cat_courses)) {
                    continue;
                }

                foreach ($cat_courses as $course) {
                    $c         = (array) $course;
                    $course_id = (int) ($c['id'] ?? 0);

                    if ($course_id <= 1) continue;

                    $total_courses_count++;

                    $c['modality']      = 'online';
                    $c['startdate_iso'] = !empty($c['startdate']) ? gmdate('Y-m-d', (int) $c['startdate']) : gmdate('Y-m-d');
                    $c['enddate_iso']   = !empty($c['enddate']) ? gmdate('Y-m-d', (int) $c['enddate']) : gmdate('Y-m-d', strtotime('+30 days'));

                    if ($total_courses_count <= $max_courses_for_teachers) {
                        $course_teachers = $api->get_course_teachers($course_id);
                        $c['teacher']    = !empty($course_teachers) ? implode(', ', $course_teachers) : esc_html__('Not assigned', 'woo-otec-moodle');
                    } else {
                        $c['teacher'] = esc_html__('Sync to assign', 'woo-otec-moodle');
                    }

                    $existing_product_id = Woo_OTEC_Moodle_Sync::instance()->find_product_id($course_id);
                    if ($existing_product_id > 0) {
                        $thumb_id      = get_post_thumbnail_id($existing_product_id);
                        $c['image_id'] = $thumb_id > 0 ? $thumb_id : 0;
                        $c['image_url'] = $thumb_id > 0 ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';
                        $c['certificate_enabled'] = get_post_meta($existing_product_id, '_certificate_available', true) === 'yes' ? 'yes' : 'no';
                    } else {
                        $c['image_id']  = 0;
                        $c['image_url'] = Woo_OTEC_Moodle_Sync::instance()->find_moodle_image_url((object)$c);
                        $c['certificate_enabled'] = 'no';
                    }

                    $courses[] = $c;
                }
            }
            wp_send_json_success($courses);
        } catch (Throwable $e) {
            wp_send_json_error(esc_html__('Fatal error getting courses:', 'woo-otec-moodle') . ' ' . $e->getMessage());
        }
    }

    public function handle_execute_wizard_sync(): void {
        $this->assert_ajax_permissions();
        check_ajax_referer('woo_otec_moodle_sync_stage', 'nonce');

        if (!class_exists('WooCommerce')) {
            wp_send_json_error(esc_html__('WooCommerce is not active.', 'woo-otec-moodle'));
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $categories = isset($_POST['categories']) ? array_map('absint', (array) $_POST['categories']) : array();
        $courses    = isset($_POST['courses']) ? (array) $_POST['courses'] : array();

        if (empty($categories) && empty($courses)) {
            wp_send_json_error(esc_html__('No data to synchronize.', 'woo-otec-moodle'));
        }

        $sync    = Woo_OTEC_Moodle_Sync::instance();
        $results = array('created' => 0, 'updated' => 0, 'errors' => 0);

        Woo_OTEC_Moodle_Logger::log('Iniciando sincronización desde asistente...', Woo_OTEC_Moodle_Logger::SYNC_LOG);

        try {
            if (!empty($categories)) {
                $moodle_cats = Woo_OTEC_Moodle_API::instance()->get_categories();
                if (!is_wp_error($moodle_cats)) {
                    $to_sync = array();
                    foreach ($moodle_cats as $cat) {
                        $cat_id = is_object($cat) ? ($cat->id ?? 0) : ($cat['id'] ?? 0);
                        if (in_array((int) $cat_id, $categories, true)) {
                            $to_sync[] = is_array($cat) ? (object) $cat : $cat;
                        }
                    }
                    $sync->sync_categories($to_sync);
                }
            }

            foreach ($courses as $course_data) {
                $res = $sync->sync_single_course($course_data);
                if ($res['status'] === 'created') $results['created']++;
                elseif ($res['status'] === 'updated') $results['updated']++;
                else $results['errors']++;
            }

            $msg = sprintf(
                /* translators: 1: created products, 2: updated products, 3: errors */
                esc_html__('Synchronization completed: %1$d created, %2$d updated, %3$d errors.', 'woo-otec-moodle'),
                $results['created'],
                $results['updated'],
                $results['errors']
            );

            $sync->update_last_sync(array(
                'status'           => $results['errors'] > 0 ? 'warning' : 'success',
                'message'          => $msg,
                'products_created' => $results['created'],
                'products_updated' => $results['updated'],
                'timestamp'        => current_time('mysql'),
            ));

            Woo_OTEC_Moodle_Logger::log($msg, Woo_OTEC_Moodle_Logger::SYNC_LOG);
            wp_send_json_success($msg);
        } catch (Throwable $e) {
            Woo_OTEC_Moodle_Logger::log('Error fatal en sincronización asistente: ' . $e->getMessage(), Woo_OTEC_Moodle_Logger::ERROR_LOG);
            wp_send_json_error(esc_html__('Server error:', 'woo-otec-moodle') . ' ' . $e->getMessage());
        }
    }

    /**
     * Single Sign-On (SSO)
     * Verificador de disponibilidad del extremo de Moodle para autenticación conectada.
     */

    public function handle_test_sso(): void {
        $this->assert_ajax_permissions();
        check_ajax_referer('woo_otec_moodle_sync_stage', 'nonce');

        $url = esc_url_raw($_POST['url'] ?? '');
        if (empty($url)) {
            wp_send_json_error(esc_html__('Invalid URL.', 'woo-otec-moodle'));
        }

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 400) {
            wp_send_json_success(esc_html__('Connection successful.', 'woo-otec-moodle'));
        } else {
            wp_send_json_error(
                sprintf(
                    /* translators: %d: HTTP response code */
                    esc_html__('The URL responded with code: %d', 'woo-otec-moodle'),
                    $code
                )
            );
        }
    }

    /**
     * Exportación de Registros y Configuración (Dump)
     * Facilitadores de descarga de archivos para diagnóstico y copias de seguridad de los ajustes.
     */

    public function handle_export_logs(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized request.', 'woo-otec-moodle'));
        }

        check_admin_referer('woo_otec_moodle_export_logs', 'nonce');

        $log_content = Woo_OTEC_Moodle_Logger::read_full(Woo_OTEC_Moodle_Logger::ERROR_LOG);

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="woo-otec-logs-' . gmdate('Y-m-d-His') . '.txt"');
        echo $log_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function handle_export_config(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized request.', 'woo-otec-moodle'));
        }

        check_ajax_referer('woo_otec_moodle_export_config', 'nonce');

        $core = Woo_OTEC_Moodle_Core::instance();
        $defaults = $core->get_defaults();
        $settings = array();

        foreach (array_keys($defaults) as $key) {
            $settings[$key] = $core->get_option($key, $defaults[$key]);
        }

        $settings['template_fields'] = get_option('woo_otec_moodle_template_fields', $settings['template_fields'] ?? array());
        $settings['mappings'] = get_option('woo_otec_moodle_mappings', Woo_OTEC_Moodle_Mapper::instance()->get_default_mappings());

        $payload = array(
            'plugin'      => 'Woo OTEC Moodle',
            'version'     => defined('WOO_OTEC_MOODLE_VERSION') ? WOO_OTEC_MOODLE_VERSION : '',
            'exported_at' => current_time('mysql'),
            'site_url'    => home_url('/'),
            'settings'    => $settings,
        );

        $filename = 'woo-otec-moodle-config-' . gmdate('Ymd-His') . '.json';
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($json)) {
            wp_die(esc_html__('The configuration file could not be generated.', 'woo-otec-moodle'));
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function handle_import_config(): void {
        $this->assert_ajax_permissions();
        check_ajax_referer('woo_otec_moodle_import_config', 'nonce');

        if (empty($_FILES['config_file']['tmp_name']) || !is_uploaded_file($_FILES['config_file']['tmp_name'])) {
            wp_send_json_error(array('message' => esc_html__('You must select a valid JSON file.', 'woo-otec-moodle')), 400);
        }

        $json = file_get_contents($_FILES['config_file']['tmp_name']);
        if (!is_string($json) || trim($json) === '') {
            wp_send_json_error(array('message' => esc_html__('The configuration file could not be read.', 'woo-otec-moodle')), 400);
        }

        $json = $this->normalize_import_json($json);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            wp_send_json_error(array('message' => esc_html__('The file does not contain valid JSON.', 'woo-otec-moodle')), 400);
        }

        $settings = $this->extract_import_settings($decoded);
        if (!is_array($settings) || empty($settings)) {
            wp_send_json_error(array('message' => esc_html__('The file format is not supported or does not contain importable settings.', 'woo-otec-moodle')), 400);
        }

        $applied = $this->apply_imported_settings($settings);
        Woo_OTEC_Moodle_Logger::info('Configuración importada desde JSON', array('updated_keys' => $applied));

        wp_send_json_success(array(
            'message' => esc_html__('Configuration imported successfully.', 'woo-otec-moodle'),
            'updated_keys' => $applied,
        ));
    }

    private function apply_imported_settings(array $settings): array {
        $core = Woo_OTEC_Moodle_Core::instance();
        $defaults = $core->get_defaults();
        $sanitizer = Woo_OTEC_Moodle_Settings::instance();
        $updated = array();

        $checkbox_fields = array('sso_enabled', 'auto_update', 'redirect_after_purchase', 'debug_enabled', 'email_enabled', 'email_builder_enabled');
        $int_fields = array('student_role_id', 'default_image_id', 'email_logo_id', 'retry_limit', 'template_reference');
        $url_fields = array('moodle_url', 'sso_base_url', 'github_release_url');
        $email_fields = array('email_from_address', 'email_test_recipient');
        $hex_fields = array('email_color_primary', 'email_color_accent', 'email_color_bg', 'pcc_color_primary', 'pcc_color_secondary', 'pcc_color_text', 'pcc_color_accent');
        $textarea_fields = array('fallback_description', 'email_builder_intro', 'email_builder_footer');
        $html_fields = array('email_template');

        foreach (array_keys($defaults) as $key) {
            if (!array_key_exists($key, $settings) || $key === 'last_sync') {
                continue;
            }

            $value = $settings[$key];

            if (in_array($key, $checkbox_fields, true)) {
                $value = $sanitizer->sanitize_checkbox($value);
            } elseif (in_array($key, $int_fields, true)) {
                $value = absint($value);
            } elseif (in_array($key, $url_fields, true)) {
                $value = esc_url_raw((string) $value);
            } elseif (in_array($key, $email_fields, true)) {
                $value = sanitize_email((string) $value);
            } elseif (in_array($key, $hex_fields, true)) {
                $value = sanitize_hex_color((string) $value);
            } elseif (in_array($key, $textarea_fields, true)) {
                $value = sanitize_textarea_field((string) $value);
            } elseif (in_array($key, $html_fields, true)) {
                $value = $sanitizer->sanitize_email_template($value);
            } else {
                $value = is_scalar($value) ? sanitize_text_field((string) $value) : $defaults[$key];
            }

            $core->update_option($key, $value);
            $updated[] = $key;
        }

        if (isset($settings['template_fields'])) {
            update_option(
                'woo_otec_moodle_template_fields',
                $sanitizer->sanitize_array($settings['template_fields'])
            );
            $updated[] = 'template_fields';
        }

        if (isset($settings['mappings'])) {
            update_option(
                'woo_otec_moodle_mappings',
                $sanitizer->sanitize_mappings($settings['mappings'])
            );
            $updated[] = 'mappings';
        }

        return array_values(array_unique($updated));
    }

    private function normalize_import_json(string $json): string {
        $json = trim($json);

        if (str_starts_with($json, "\xEF\xBB\xBF")) {
            $json = substr($json, 3);
        }

        return trim($json);
    }

    private function extract_import_settings(array $decoded): array {
        if (isset($decoded['settings']) && is_array($decoded['settings'])) {
            return $decoded['settings'];
        }

        if (isset($decoded['woo_otec_moodle_settings']) && is_array($decoded['woo_otec_moodle_settings'])) {
            return $decoded['woo_otec_moodle_settings'];
        }

        $core = Woo_OTEC_Moodle_Core::instance();
        $defaults = $core->get_defaults();
        $known_keys = array_merge(array_keys($defaults), array('template_fields', 'mappings'));
        $matched = array_intersect($known_keys, array_keys($decoded));

        if (!empty($matched)) {
            return $decoded;
        }

        return array();
    }

    private function assert_ajax_permissions(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized request.', 'woo-otec-moodle')), 403);
        }
    }
}

