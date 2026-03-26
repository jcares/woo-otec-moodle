<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestiona todas las peticiones AJAX del panel de administración.
 * Extraído de PCC_WooOTEC_Pro_Admin para separar responsabilidades.
 */
final class PCC_WooOTEC_Pro_Ajax_Handler {
    private static ?PCC_WooOTEC_Pro_Ajax_Handler $instance = null;

    public static function instance(): PCC_WooOTEC_Pro_Ajax_Handler {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function boot(): void {
        add_action('admin_post_pcc_woootec_run_sync', array($this, 'handle_manual_sync'));
        add_action('wp_ajax_pcc_woootec_email_preview', array($this, 'handle_email_preview'));
        add_action('wp_ajax_pcc_woootec_send_test_email', array($this, 'handle_send_test_email'));
        add_action('wp_ajax_pcc_woootec_template_fields', array($this, 'handle_template_fields_ajax'));
        add_action('wp_ajax_pcc_woootec_get_categories', array($this, 'handle_get_categories'));
        add_action('wp_ajax_pcc_woootec_get_teachers', array($this, 'handle_get_teachers'));
        add_action('wp_ajax_pcc_woootec_get_courses', array($this, 'handle_get_courses'));
        add_action('wp_ajax_pcc_woootec_execute_wizard_sync', array($this, 'handle_execute_wizard_sync'));
        add_action('wp_ajax_pcc_woootec_test_sso', array($this, 'handle_test_sso'));
        add_action('wp_ajax_pcc_woootec_export_logs', array($this, 'handle_export_logs'));
        add_action('wp_ajax_pcc_woootec_generate_zip', array($this, 'handle_generate_zip'));
    }

    // -------------------------------------------------------------------------
    // Sincronización manual (admin_post)
    // -------------------------------------------------------------------------

    public function handle_manual_sync(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('pcc_woootec_run_sync');

        $result   = PCC_WooOTEC_Pro_Sync::instance()->run(true);
        $redirect = add_query_arg(
            array(
                'page'   => 'pcc-woootec-chile',
                'tab'    => 'sync',
                'status' => $result['status'],
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    // -------------------------------------------------------------------------
    // Email
    // -------------------------------------------------------------------------

    public function handle_email_preview(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado.'), 403);
        }

        check_ajax_referer('pcc_woootec_email_tools', 'nonce');

        $html = PCC_WooOTEC_Pro_Enroll::instance()->render_email_preview();
        wp_send_json_success(array('html' => $html));
    }

    public function handle_send_test_email(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado.'), 403);
        }

        check_ajax_referer('pcc_woootec_email_tools', 'nonce');

        $recipient = sanitize_email((string) wp_unslash($_POST['recipient'] ?? ''));
        $result    = PCC_WooOTEC_Pro_Enroll::instance()->send_test_email($recipient);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success(array('message' => 'Correo de prueba enviado.'));
    }

    // -------------------------------------------------------------------------
    // Template fields
    // -------------------------------------------------------------------------

    public function handle_template_fields_ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado.'), 403);
        }

        check_ajax_referer('pcc_woootec_template_fields', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if ($product_id <= 0) {
            wp_send_json_error(array('message' => 'Producto no valido.'), 400);
        }

        $selected_fields = (array) PCC_WooOTEC_Pro_Core::instance()->get_option('template_fields', array());
        $html            = PCC_WooOTEC_Pro_Settings::instance()->render_template_fields_markup($product_id, $selected_fields, false);
        $allowed         = array(
            'label' => array('class' => true),
            'input' => array('type' => true, 'id' => true, 'name' => true, 'value' => true, 'checked' => true),
            'span'  => array(),
            'p'     => array('class' => true),
        );

        wp_send_json_success(array('html' => wp_kses($html, $allowed)));
    }

    // -------------------------------------------------------------------------
    // Asistente de sincronización
    // -------------------------------------------------------------------------

    public function handle_get_categories(): void {
        check_ajax_referer('pcc_woootec_sync_stage', 'nonce');

        $categories = PCC_WooOTEC_Pro_API::instance()->get_categories();
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
        check_ajax_referer('pcc_woootec_sync_stage', 'nonce');

        $category_ids = isset($_POST['categories']) ? array_map('absint', (array) $_POST['categories']) : array();
        if (empty($category_ids)) {
            wp_send_json_error('No se seleccionaron categorías.');
        }

        $teachers          = array();
        $api               = PCC_WooOTEC_Pro_API::instance();
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
            $teachers[] = '(Detección limitada por cantidad de cursos)';
        }

        sort($teachers);
        wp_send_json_success($teachers);
    }

    public function handle_get_courses(): void {
        check_ajax_referer('pcc_woootec_sync_stage', 'nonce');
        set_time_limit(120);

        $category_ids = isset($_POST['categories']) ? array_map('absint', (array) $_POST['categories']) : array();
        if (empty($category_ids)) {
            wp_send_json_error('No se seleccionaron categorías.');
        }

        $courses                  = array();
        $api                      = PCC_WooOTEC_Pro_API::instance();
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
                    $c['startdate_iso'] = !empty($c['startdate']) ? date('Y-m-d', (int) $c['startdate']) : date('Y-m-d');
                    $c['enddate_iso']   = !empty($c['enddate']) ? date('Y-m-d', (int) $c['enddate']) : date('Y-m-d', strtotime('+30 days'));

                    if ($total_courses_count <= $max_courses_for_teachers) {
                        $course_teachers = $api->get_course_teachers($course_id);
                        $c['teacher']    = !empty($course_teachers) ? implode(', ', $course_teachers) : 'No asignado';
                    } else {
                        $c['teacher'] = 'Sincronizar para asignar';
                    }

                    $existing_product_id = PCC_WooOTEC_Pro_Sync::instance()->find_product_id($course_id);
                    if ($existing_product_id > 0) {
                        $thumb_id      = get_post_thumbnail_id($existing_product_id);
                        $c['image_id'] = $thumb_id > 0 ? $thumb_id : 0;
                        $c['image_url'] = $thumb_id > 0 ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';
                    } else {
                        $c['image_id']  = 0;
                        $c['image_url'] = '';
                    }

                    $courses[] = $c;
                }
            }
            wp_send_json_success($courses);
        } catch (Throwable $e) {
            wp_send_json_error('Error fatal obteniendo cursos: ' . $e->getMessage());
        }
    }

    public function handle_execute_wizard_sync(): void {
        check_ajax_referer('pcc_woootec_sync_stage', 'nonce');

        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce no está activo.');
        }

        set_time_limit(300);

        $categories = isset($_POST['categories']) ? array_map('absint', (array) $_POST['categories']) : array();
        $courses    = isset($_POST['courses']) ? (array) $_POST['courses'] : array();

        if (empty($categories) && empty($courses)) {
            wp_send_json_error('No hay datos para sincronizar.');
        }

        $sync    = PCC_WooOTEC_Pro_Sync::instance();
        $results = array('created' => 0, 'updated' => 0, 'errors' => 0);

        PCC_WooOTEC_Pro_Logger::log('Iniciando sincronización desde asistente...', PCC_WooOTEC_Pro_Logger::SYNC_LOG);

        try {
            if (!empty($categories)) {
                $moodle_cats = PCC_WooOTEC_Pro_API::instance()->get_categories();
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

            $msg = sprintf('Sincronización completada: %d creados, %d actualizados, %d errores.', $results['created'], $results['updated'], $results['errors']);

            $sync->update_last_sync(array(
                'status'           => $results['errors'] > 0 ? 'warning' : 'success',
                'message'          => $msg,
                'products_created' => $results['created'],
                'products_updated' => $results['updated'],
                'timestamp'        => current_time('mysql'),
            ));

            PCC_WooOTEC_Pro_Logger::log($msg, PCC_WooOTEC_Pro_Logger::SYNC_LOG);
            wp_send_json_success($msg);
        } catch (Throwable $e) {
            PCC_WooOTEC_Pro_Logger::log('Error fatal en sincronización asistente: ' . $e->getMessage(), PCC_WooOTEC_Pro_Logger::ERROR_LOG);
            wp_send_json_error('Error en el servidor: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // SSO
    // -------------------------------------------------------------------------

    public function handle_test_sso(): void {
        check_ajax_referer('pcc_woootec_sync_stage', 'nonce');

        $url = esc_url_raw($_POST['url'] ?? '');
        if (empty($url)) {
            wp_send_json_error('URL no válida.');
        }

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 400) {
            wp_send_json_success('Conexión exitosa.');
        } else {
            wp_send_json_error('La URL respondió con código: ' . $code);
        }
    }

    // -------------------------------------------------------------------------
    // Logs y ZIP
    // -------------------------------------------------------------------------

    public function handle_export_logs(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('pcc_woootec_export_logs', 'nonce');

        $log_content = PCC_WooOTEC_Pro_Logger::read_full(PCC_WooOTEC_Pro_Logger::ERROR_LOG);

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="pcc-woootec-logs-' . date('Y-m-d-His') . '.txt"');
        echo $log_content;
        exit;
    }

    public function handle_generate_zip(): void {
        check_ajax_referer('pcc_woootec_sync_stage', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('No autorizado.');
        }

        $plugin_dir = PCC_WOOOTEC_PRO_PATH;
        $zip_name   = 'pcc-woootec-chile.zip';
        $zip_path   = $plugin_dir . $zip_name;

        if (file_exists($zip_path)) {
            $timestamp = date('Ymd-His');
            $new_name  = 'pcc-woootec-chile-' . $timestamp . '.zip';
            rename($zip_path, $plugin_dir . $new_name);
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            wp_send_json_error('No se pudo crear el archivo ZIP.');
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($plugin_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path     = $file->getRealPath();
                $relative_path = 'pcc-woootec-chile/' . substr($file_path, strlen($plugin_dir));

                if (strpos($file_path, '.git') !== false ||
                    strpos($file_path, 'node_modules') !== false ||
                    strpos($file_path, 'logs') !== false ||
                    basename($file_path) === $zip_name ||
                    strpos(basename($file_path), 'pcc-woootec-chile-20') === 0) {
                    continue;
                }

                $zip->addFile($file_path, $relative_path);
            }
        }

        $zip->close();

        wp_send_json_success(array(
            'message' => 'Archivo ZIP generado correctamente.',
            'url'     => PCC_WOOOTEC_PRO_URL . $zip_name,
        ));
    }
}
