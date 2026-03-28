<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orquestador del panel de administración.
 * Inicializa las clases de configuración y AJAX, y gestiona la carga de vistas.
 */
final class Woo_OTEC_Moodle_Admin {
    private static ?Woo_OTEC_Moodle_Admin $instance = null;

    public static function instance(): Woo_OTEC_Moodle_Admin {
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

        Woo_OTEC_Moodle_Settings::instance()->boot();
        Woo_OTEC_Moodle_Ajax_Handler::instance()->boot();

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'woo-otec-moodle') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('woo-otec-modern-ui', WOO_OTEC_MOODLE_URL . 'assets/admin/css/modern-ui.css', array(), WOO_OTEC_MOODLE_VERSION);
        wp_enqueue_script('woo-otec-modern-ui', WOO_OTEC_MOODLE_URL . 'assets/admin/js/modern-ui.js', array('jquery'), WOO_OTEC_MOODLE_VERSION, true);

        $local = array(
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('woo_otec_moodle_sync_stage'),
            'emailNonce'    => wp_create_nonce('woo_otec_moodle_email_tools'),
            'defaultTab'    => $this->get_default_tab(),
            'templateNonce' => wp_create_nonce('woo_otec_moodle_template_fields'),
        );

        wp_localize_script('woo-otec-modern-ui', 'wooOtecMoodleAdmin', $local);
    }

    // -------------------------------------------------------------------------
    // Renderizado de páginas
    // -------------------------------------------------------------------------

    public function render_settings_page(): void {
        $data = array(
            'core'             => Woo_OTEC_Moodle_Core::instance(),
            'last_sync'        => Woo_OTEC_Moodle_Core::instance()->get_option('last_sync', array()),
            'connection_ok'    => Woo_OTEC_Moodle_API::instance()->test_connection(),
            'sync_log'         => Woo_OTEC_Moodle_Logger::read_tail(Woo_OTEC_Moodle_Logger::SYNC_LOG),
            'error_log'        => Woo_OTEC_Moodle_Logger::read_tail(Woo_OTEC_Moodle_Logger::ERROR_LOG),
            'release'          => array(),
            'update_available' => false,
            'active_tab'       => $this->get_default_tab(),
            'status'           => sanitize_key((string) ($_GET['status'] ?? '')),
        );

        $this->render_view('settings-page.php', $data);
    }

    public function render_sync_page(): void {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'woo-otec-moodle',
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
                    'page' => 'woo-otec-moodle',
                    'tab'  => 'sync',
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    // -------------------------------------------------------------------------
    // Privados
    // -------------------------------------------------------------------------

    private function render_view(string $view, array $data = array()): void {
        $view_path = WOO_OTEC_MOODLE_PATH . 'admin/views/' . $view;
        if (!file_exists($view_path)) {
            return;
        }

        extract($data, EXTR_SKIP);
        include $view_path;
    }

    private function get_default_tab(): string {
        $allowed_tabs = array('general', 'sync', 'sso', 'templates', 'appearance', 'emails', 'logs');
        $tab          = sanitize_key((string) ($_GET['tab'] ?? 'general'));
        return in_array($tab, $allowed_tabs, true) ? $tab : 'general';
    }
}
