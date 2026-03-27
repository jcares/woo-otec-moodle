<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_WooOTEC_Pro_Admin {
    private static ?PCC_WooOTEC_Pro_Admin $instance = null;

    public static function instance(): PCC_WooOTEC_Pro_Admin {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function boot(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
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

    public function register_menu(): void {
        add_menu_page(
            'PCC WooOTEC Moodle',
            'PCC WooOTEC Moodle',
            'manage_options',
            'pcc-woootec-moodle',
            array($this, 'render_settings_page'),
            'dashicons-welcome-learn-more',
            25
        );

        add_submenu_page('pcc-woootec-moodle', 'Configuracion', 'Configuracion', 'manage_options', 'pcc-woootec-moodle', array($this, 'render_settings_page'));
        add_submenu_page('pcc-woootec-moodle', 'Sincronizacion', 'Sincronizacion', 'manage_options', 'pcc-woootec-moodle-sync', array($this, 'render_sync_page'));
        add_submenu_page('pcc-woootec-moodle', 'Logs', 'Logs', 'manage_options', 'pcc-woootec-moodle-logs', array($this, 'render_logs_page'));
    }

    public function register_settings(): void {
        $fields = array(
            'moodle_url'           => 'esc_url_raw',
            'moodle_token'         => 'sanitize_text_field',
            'student_role_id'      => 'absint',
            'default_price'        => 'sanitize_text_field',
            'default_instructor'   => 'sanitize_text_field',
            'fallback_description' => 'sanitize_textarea_field',
            'default_image_id'     => 'absint',
            'sso_base_url'         => 'esc_url_raw',
            'github_repo'          => 'sanitize_text_field',
            'github_release_url'   => 'esc_url_raw',
            'email_from_address'   => 'sanitize_email',
            'email_from_name'      => 'sanitize_text_field',
            'email_subject'        => 'sanitize_text_field',
            'email_template'       => array($this, 'sanitize_email_template'),
            'email_test_recipient' => 'sanitize_email',
            'retry_limit'          => 'absint',
            'template_style'       => 'sanitize_text_field',
            'template_reference'   => 'absint',
        );

        register_setting(
            'pcc_woootec_pro_settings',
            'pcc_woootec_pro_template_fields',
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_array'),
                'default'           => PCC_WooOTEC_Pro_Core::instance()->get_defaults()['template_fields'] ?? array(),
            )
        );

        register_setting(
            'pcc_woootec_pro_settings',
            'pcc_woootec_pro_mappings',
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_mappings'),
                'default'           => PCC_WooOTEC_Pro_Mapper::instance()->get_default_mappings(),
            )
        );

        foreach ($fields as $field => $sanitize_callback) {
            register_setting(
                'pcc_woootec_pro_settings',
                'pcc_woootec_pro_' . $field,
                array(
                    'type'              => in_array($sanitize_callback, array('absint'), true) ? 'integer' : 'string',
                    'sanitize_callback' => $sanitize_callback,
                    'default'           => PCC_WooOTEC_Pro_Core::instance()->get_defaults()[$field] ?? '',
                )
            );
        }

        foreach (array('sso_enabled', 'auto_update', 'redirect_after_purchase', 'debug_enabled', 'email_enabled') as $field) {
            register_setting(
                'pcc_woootec_pro_settings',
                'pcc_woootec_pro_' . $field,
                array(
                    'type'              => 'string',
                    'sanitize_callback' => array($this, 'sanitize_checkbox'),
                    'default'           => PCC_WooOTEC_Pro_Core::instance()->get_defaults()[$field] ?? 'no',
                )
            );
        }
    }

    public function sanitize_array(mixed $value): array {
        return is_array($value) ? array_map('sanitize_text_field', $value) : array();
    }

    public function sanitize_mappings(mixed $value): array {
        if (!is_array($value)) {
            return array();
        }

        $sanitized = array();
        foreach ($value as $key => $config) {
            $sanitized[sanitize_key($key)] = array(
                'target'  => sanitize_text_field($config['target'] ?? ''),
                'label'   => sanitize_text_field($config['label'] ?? ''),
                'enabled' => !empty($config['enabled']) ? 'yes' : 'no',
            );
        }

        return $sanitized;
    }

    public function sanitize_checkbox(mixed $value): string {
        return !empty($value) && $value !== 'no' ? 'yes' : 'no';
    }

    public function sanitize_email_template(mixed $value): string {
        if (!is_string($value)) {
            return '';
        }

        return wp_kses($value, PCC_WooOTEC_Pro_Mailer::instance()->get_email_allowed_html());
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'pcc-woootec-moodle') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('pcc-woootec-modern-ui', PCC_WOOOTEC_PRO_URL . 'assets/admin/css/modern-ui.css', array(), PCC_WOOOTEC_PRO_VERSION);
        wp_enqueue_script('pcc-woootec-modern-ui', PCC_WOOOTEC_PRO_URL . 'assets/admin/js/modern-ui.js', array('jquery'), PCC_WOOOTEC_PRO_VERSION, true);

        // Localización base
        $local = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pcc_woootec_sync_stage'),
            'emailNonce' => wp_create_nonce('pcc_woootec_email_tools'),
            'defaultTab' => $this->get_default_tab(),
            'templateNonce' => wp_create_nonce('pcc_woootec_template_fields'),
        );

        wp_localize_script('pcc-woootec-modern-ui', 'pccWoootecAdmin', $local);
    }

    public function handle_manual_sync(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('pcc_woootec_run_sync');

        $result = PCC_WooOTEC_Pro_Sync::instance()->run(true);
        $redirect = add_query_arg(
            array(
                'page'   => 'pcc-woootec-moodle',
                'tab'    => 'sync',
                'status' => $result['status'],
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function render_settings_page(): void {
        $data = array(
            'core'            => PCC_WooOTEC_Pro_Core::instance(),
            'last_sync'       => PCC_WooOTEC_Pro_Core::instance()->get_option('last_sync', array()),
            'connection_ok'   => PCC_WooOTEC_Pro_API::instance()->test_connection(),
            'sync_log'        => PCC_WooOTEC_Pro_Logger::read_tail(PCC_WooOTEC_Pro_Logger::SYNC_LOG),
            'error_log'       => PCC_WooOTEC_Pro_Logger::read_tail(PCC_WooOTEC_Pro_Logger::ERROR_LOG),
            'release'         => array(),
            'update_available'=> false,
            'active_tab'      => $this->get_default_tab(),
            'status'          => sanitize_key((string) ($_GET['status'] ?? '')),
        );

        $this->render_view('settings-page.php', $data);
    }

    public function render_sync_page(): void {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'pcc-woootec-moodle',
                    'tab'  => 'sync',
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function render_logs_page(): void {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'pcc-woootec-moodle',
                    'tab'  => 'sync',
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    private function render_view(string $view, array $data = array()): void {
        $view_path = PCC_WOOOTEC_PRO_PATH . 'admin/views/' . $view;
        if (!file_exists($view_path)) {
            return;
        }

        extract($data, EXTR_SKIP);
        include $view_path;
    }

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
        $result = PCC_WooOTEC_Pro_Enroll::instance()->send_test_email($recipient);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success(array('message' => 'Correo de prueba enviado.'));
    }

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
        $html = $this->render_template_fields_markup($product_id, $selected_fields, false);
        $allowed = array(
            'label' => array('class' => true),
            'input' => array('type' => true, 'id' => true, 'name' => true, 'value' => true, 'checked' => true),
            'span'  => array(),
            'p'     => array('class' => true),
        );

        wp_send_json_success(array('html' => wp_kses($html, $allowed)));
    }

    private function get_default_tab(): string {
        $allowed_tabs = array('general', 'sync', 'sso', 'templates', 'emails', 'logs');
        $tab = sanitize_key((string) ($_GET['tab'] ?? 'general'));
        return in_array($tab, $allowed_tabs, true) ? $tab : 'general';
    }

    public function handle_get_categories(): void {
        check_ajax_referer('pcc_woootec_sync_stage', 'nonce');

        $categories = PCC_WooOTEC_Pro_API::instance()->get_categories();
        if (is_wp_error($categories)) {
            wp_send_json_error($categories->get_error_message());
        }

        $formatted = array();
        foreach ($categories as $cat) {
            $cat_name = is_object($cat) ? ($cat->name ?? '') : ($cat['name'] ?? '');
            $cat_id = is_object($cat) ? ($cat->id ?? 0) : ($cat['id'] ?? 0);
            
            if (empty($cat_name) || empty($cat_id)) continue;

            $term = get_term_by('name', $cat_name, 'product_cat');
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

        $teachers = array();
        $api = PCC_WooOTEC_Pro_API::instance();
        $courses_processed = 0;
        $max_courses = 50; // Limitar a 50 cursos para evitar timeout en detección de profesores

        foreach ($category_ids as $cat_id) {
            if ($courses_processed >= $max_courses) break;

            $courses = $api->get_courses_by_category($cat_id);
            if (!is_wp_error($courses)) {
                foreach ($courses as $course) {
                    if ($courses_processed >= $max_courses) break;
                    
                    $course_id = is_object($course) ? ($course->id ?? 0) : ($course['id'] ?? 0);
                    if (!$course_id) continue;

                    $course_teachers = $api->get_course_teachers((int)$course_id);
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
        set_time_limit(120); // Extender tiempo de ejecución a 2 minutos

        $category_ids = isset($_POST['categories']) ? array_map('absint', (array) $_POST['categories']) : array();
        if (empty($category_ids)) {
            wp_send_json_error('No se seleccionaron categorías.');
        }

        $courses = array();
        $api = PCC_WooOTEC_Pro_API::instance();
        $total_courses_count = 0;
        $max_courses_for_teachers = 40; // Límite total de cursos para buscar profesores en tiempo real
        
        try {
            foreach ($category_ids as $cat_id) {
                $cat_courses = $api->get_courses_by_category($cat_id);
                if (is_wp_error($cat_courses)) {
                    continue;
                }

                foreach ($cat_courses as $course) {
                    $c = (array) $course;
                    $course_id = (int) ($c['id'] ?? 0);
                    
                    // Omitir Site Home (ID 1)
                    if ($course_id <= 1) continue;

                    $total_courses_count++;
                    if ($course_id < 1) continue;

                    // Datos base
                    $c['modality'] = 'online';
                    $c['startdate_iso'] = !empty($c['startdate']) ? date('Y-m-d', (int)$c['startdate']) : date('Y-m-d');
                    $c['enddate_iso'] = !empty($c['enddate']) ? date('Y-m-d', (int)$c['enddate']) : date('Y-m-d', strtotime('+30 days'));
                    
                    // Profesor (Solo si el total acumulado es razonable)
                    if ($total_courses_count <= $max_courses_for_teachers) {
                        $course_teachers = $api->get_course_teachers($course_id);
                        $c['teacher'] = !empty($course_teachers) ? implode(', ', $course_teachers) : 'No asignado';
                    } else {
                        $c['teacher'] = 'Sincronizar para asignar';
                    }
                    
                    // Imagen actual en WooCommerce
                    $existing_product_id = PCC_WooOTEC_Pro_Sync::instance()->find_product_id($course_id);
                    if ($existing_product_id > 0) {
                        $thumb_id = get_post_thumbnail_id($existing_product_id);
                        $c['image_id'] = $thumb_id > 0 ? $thumb_id : 0;
                        $c['image_url'] = $thumb_id > 0 ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';
                    } else {
                        $c['image_id'] = 0;
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

        set_time_limit(300); // 5 minutos para sincronizar productos e imágenes

        $categories = isset($_POST['categories']) ? array_map('absint', (array) $_POST['categories']) : array();
        $courses = isset($_POST['courses']) ? (array) $_POST['courses'] : array();

        if (empty($categories) && empty($courses)) {
            wp_send_json_error('No hay datos para sincronizar.');
        }

        // We extend the sync logic here
        $sync = PCC_WooOTEC_Pro_Sync::instance();
        $results = array('created' => 0, 'updated' => 0, 'errors' => 0);
        
        PCC_WooOTEC_Pro_Logger::log('Iniciando sincronización desde asistente...', PCC_WooOTEC_Pro_Logger::SYNC_LOG);

        try {
            // Process categories first if any
            if (!empty($categories)) {
                $moodle_cats = PCC_WooOTEC_Pro_API::instance()->get_categories();
                if (!is_wp_error($moodle_cats)) {
                    $to_sync = array();
                    foreach ($moodle_cats as $cat) {
                        $cat_id = is_object($cat) ? ($cat->id ?? 0) : ($cat['id'] ?? 0);
                        if (in_array((int)$cat_id, $categories, true)) {
                            $to_sync[] = is_array($cat) ? (object)$cat : $cat;
                        }
                    }
                    $sync->sync_categories($to_sync);
                }
            }

            // Process courses with edited data
            foreach ($courses as $course_data) {
                $res = $sync->sync_single_course($course_data);
                if ($res['status'] === 'created') $results['created']++;
                elseif ($res['status'] === 'updated') $results['updated']++;
                else $results['errors']++;
            }

            $msg = sprintf('Sincronización completada: %d creados, %d actualizados, %d errores.', $results['created'], $results['updated'], $results['errors']);
            
            // Update last sync status
            $sync->update_last_sync(array(
                'status' => $results['errors'] > 0 ? 'warning' : 'success',
                'message' => $msg,
                'products_created' => $results['created'],
                'products_updated' => $results['updated'],
                'timestamp' => current_time('mysql'),
            ));

            PCC_WooOTEC_Pro_Logger::log($msg, PCC_WooOTEC_Pro_Logger::SYNC_LOG);
            wp_send_json_success($msg);
        } catch (Throwable $e) {
            PCC_WooOTEC_Pro_Logger::log('Error fatal en sincronización asistente: ' . $e->getMessage(), PCC_WooOTEC_Pro_Logger::ERROR_LOG);
            wp_send_json_error('Error en el servidor: ' . $e->getMessage());
        }
    }

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
        $zip_name = 'pcc-woootec-moodle.zip';
        $zip_path = $plugin_dir . $zip_name;

        if (file_exists($zip_path)) {
            $timestamp = date('Ymd-His');
            $new_name = 'pcc-woootec-moodle-' . $timestamp . '.zip';
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
                $file_path = $file->getRealPath();
                $relative_path = 'pcc-woootec-moodle/' . substr($file_path, strlen($plugin_dir));

                // Excluir archivos innecesarios
                if (strpos($file_path, '.git') !== false || 
                    strpos($file_path, 'node_modules') !== false || 
                    strpos($file_path, 'logs') !== false ||
                    basename($file_path) === $zip_name ||
                    strpos(basename($file_path), 'pcc-woootec-moodle-20') === 0) {
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

    public function get_template_reference_products(): array {
        if (!function_exists('wc_get_products')) {
            return array();
        }

        $products = wc_get_products(array(
            'status' => array('publish', 'draft', 'private'),
            'limit'  => 200,
            'orderby' => 'date',
            'order'   => 'DESC',
            'meta_key' => '_moodle_id',
            'meta_compare' => 'EXISTS',
        ));

        return is_array($products) ? $products : array();
    }

    public function render_template_fields_markup(int $product_id, array $selected_fields, bool $echo = true): string {
    $meta_keys = $this->get_product_meta_keys($product_id);

    ob_start();
    $labels = $this->get_meta_labels();
    ?>

    <style>
        .pcc-template-fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .pcc-meta-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border: 1px solid #e2e4e7;
            border-radius: 6px;
            background: #fff;
            margin: 0;
            cursor: pointer;
        }

        .pcc-meta-checkbox input {
            margin: 0;
        }

        .pcc-meta-checkbox span {
            font-size: 13px;
            line-height: 1.3;
        }

        .pcc-meta-checkbox:hover {
            border-color: #2271b1;
            background: #f6f7f7;
        }
    </style>

    <?php

    if (empty($meta_keys)) {

        echo '<p class="description">No se encontraron metadatos en el producto seleccionado.</p>';

    } else {

        echo '<div class="pcc-template-fields-grid">';

        foreach ($meta_keys as $meta_key) {

            $checked = in_array($meta_key, $selected_fields, true);
            $field_id = 'pcc-template-field-' . sanitize_key($meta_key);
            $label = $labels[$meta_key] ?? $this->format_meta_label($meta_key);

            echo '<label class="pcc-meta-checkbox">';
            echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="pcc_woootec_pro_template_fields[]" value="' . esc_attr($meta_key) . '" ' . checked($checked, true, false) . '>';
            echo '<span>' . esc_html($label) . '</span>';
            echo '</label>';
        }

        echo '</div>';
    }

    $html = (string) ob_get_clean();

    if ($echo) {
        echo wp_kses($html, array(
            'div'   => array('class' => true),
            'label' => array('class' => true),
            'input' => array(
                'type'    => true,
                'id'      => true,
                'name'    => true,
                'value'   => true,
                'checked' => true,
            ),
            'span'  => array(),
            'p'     => array('class' => true),
            'style' => array(),
        ));
    }

    return $html;
}

    private function get_product_meta_keys(int $product_id): array {
    $meta = get_post_meta($product_id);

    if (!is_array($meta)) {
        return array();
    }

    $keys = array_keys($meta);

    // 🔥 excluir basura técnica
    $exclude_patterns = array(
        '_edit_',
        '_wp_',
        '_elementor',
        '_wc_',
        'elementor',
        'eael_',
        'ekit_',
        'eb_',
    );

    $exclude_exact = array(
        '_thumbnail_id',
        '_product_version',
        '_downloadable',
        '_download_limit',
        '_download_expiry',
        '_backorders',
        '_manage_stock',
    );

    $keys = array_filter($keys, function ($key) use ($exclude_patterns, $exclude_exact) {

        if (!is_string($key)) {
            return false;
        }

        // excluir exactos
        if (in_array($key, $exclude_exact, true)) {
            return false;
        }

        // excluir por patrón
        foreach ($exclude_patterns as $pattern) {
            if (stripos($key, $pattern) !== false) {
                return false;
            }
        }

        return true;
    });

    $keys = array_values(array_unique($keys));

    // 🔥 orden UX
    $priority = array(
        '_start_date',
        '_end_date',
        '_instructor',
        '_price',
        '_regular_price',
        '_stock',
        '_stock_status',
        '_moodle_course_details_plain',
        '_moodle_course_details',
    );

    usort($keys, function ($a, $b) use ($priority) {

        $posA = array_search($a, $priority, true);
        $posB = array_search($b, $priority, true);

        $posA = $posA === false ? 999 : $posA;
        $posB = $posB === false ? 999 : $posB;

        return $posA <=> $posB;
    });

    return $keys;
}

private function get_meta_labels(): array {
    return array(
        '_end_date'                   => 'Fecha en que finaliza el curso',
        '_start_date'                 => 'Fecha en que comienza el curso',
        '_instructor'                 => 'Nombre del profesor o relator',
        '_moodle_id'                  => 'Código interno del curso',
        '_moodle_category_id'         => 'Categoría del curso',
        '_moodle_course_details'      => 'Descripción completa del curso',
        '_moodle_course_details_plain'=> 'Resumen del curso',
        '_price'                      => 'Precio actual',
        '_regular_price'              => 'Precio normal (sin descuento)',
        '_sku'                        => 'Código del producto',
        '_thumbnail_id'               => 'Imagen principal',
        '_virtual'                    => 'Curso en modalidad online',
        '_sold_individually'          => 'Compra limitada a una unidad por cliente',
        '_stock_status'               => 'Disponibilidad',
        '_stock'                      => 'Cupos disponibles',
        'total_sales'                 => 'Cantidad de compras realizadas',
        'moodle_course_id'            => 'Código alternativo del curso',
        '_pcc_synced'                 => 'Sincronizado con la plataforma',
        '_product_attributes'         => 'Características del curso',
        '_tax_status'                 => 'Aplicación de impuestos',
        '_tax_class'                  => 'Tipo de impuesto',
    );
}

private function format_meta_label(string $meta_key): string {
    $key = trim($meta_key);
    $key = ltrim($key, '_');
    $key = str_replace(array('_', '-'), ' ', $key);
    $key = preg_replace('/\s+/', ' ', $key);
    $key = strtolower($key);

    return ucfirst($key);
}
}
