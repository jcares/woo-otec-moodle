<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orquestador del panel de administración.
 * Inicializa las clases de configuración y AJAX, y gestiona la carga de vistas.
 */
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

        PCC_WooOTEC_Pro_Settings::instance()->boot();
        PCC_WooOTEC_Pro_Ajax_Handler::instance()->boot();

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'pcc-woootec-chile') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('pcc-woootec-modern-ui', PCC_WOOOTEC_PRO_URL . 'assets/admin/css/modern-ui.css', array(), PCC_WOOOTEC_PRO_VERSION);
        wp_enqueue_script('pcc-woootec-modern-ui', PCC_WOOOTEC_PRO_URL . 'assets/admin/js/modern-ui.js', array('jquery'), PCC_WOOOTEC_PRO_VERSION, true);

        $local = array(
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('pcc_woootec_sync_stage'),
            'emailNonce'    => wp_create_nonce('pcc_woootec_email_tools'),
            'defaultTab'    => $this->get_default_tab(),
            'templateNonce' => wp_create_nonce('pcc_woootec_template_fields'),
        );

        wp_localize_script('pcc-woootec-modern-ui', 'pccWoootecAdmin', $local);
    }

    // -------------------------------------------------------------------------
    // Renderizado de páginas
    // -------------------------------------------------------------------------

    public function render_settings_page(): void {
        $data = array(
            'core'             => PCC_WooOTEC_Pro_Core::instance(),
            'last_sync'        => PCC_WooOTEC_Pro_Core::instance()->get_option('last_sync', array()),
            'connection_ok'    => PCC_WooOTEC_Pro_API::instance()->test_connection(),
            'sync_log'         => PCC_WooOTEC_Pro_Logger::read_tail(PCC_WooOTEC_Pro_Logger::SYNC_LOG),
            'error_log'        => PCC_WooOTEC_Pro_Logger::read_tail(PCC_WooOTEC_Pro_Logger::ERROR_LOG),
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
                    'page' => 'pcc-woootec-chile',
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
                    'page' => 'pcc-woootec-chile',
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
        $view_path = PCC_WOOOTEC_PRO_PATH . 'admin/views/' . $view;
        if (!file_exists($view_path)) {
            return;
        }

        extract($data, EXTR_SKIP);
        include $view_path;
    }

    private function get_default_tab(): string {
        $allowed_tabs = array('general', 'sync', 'sso', 'templates', 'emails', 'logs');
        $tab          = sanitize_key((string) ($_GET['tab'] ?? 'general'));
        return in_array($tab, $allowed_tabs, true) ? $tab : 'general';
    }
}