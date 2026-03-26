<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_WooOTEC_Pro_Asistente {
    private static ?PCC_WooOTEC_Pro_Asistente $instance = null;

    public static function instance(): PCC_WooOTEC_Pro_Asistente {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
    }

    public function boot(): void {
        add_action('admin_menu', array($this, 'add_wizard_page'));
        add_action('admin_post_pcc_woootec_asistente_save', array($this, 'handle_save'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function add_wizard_page(): void {
        add_submenu_page(
            null, // Oculta el menú lateral
            'Asistente Moodle',
            'Asistente Moodle',
            'manage_options',
            'pcc-woootec-asistente',
            array($this, 'render_wizard')
        );
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'pcc-woootec-asistente') === false) {
            return;
        }

        wp_enqueue_style('pcc-woootec-wizard-css', PCC_WOOOTEC_PRO_URL . 'admin/asistente/assets/css/asistente.css', array(), PCC_WOOOTEC_PRO_VERSION);
    }

    public function render_wizard(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes para acceder a esta página.');
        }

        $step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
        $step = max(1, min(5, $step));

        $view_path = PCC_WOOOTEC_PRO_PATH . 'admin/asistente/views/plantilla.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            echo '<div class="notice notice-error"><p>No se encontró la plantilla principal del asistente.</p></div>';
        }
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('pcc_woootec_asistente_save', 'wizard_nonce');

        $step = isset($_POST['step']) ? (int) $_POST['step'] : 1;

        if ($step === 1) {
            // Guardar URL & Token
            if (isset($_POST['moodle_url'])) {
                PCC_WooOTEC_Pro_Core::instance()->update_option('moodle_url', esc_url_raw($_POST['moodle_url']));
            }
            if (isset($_POST['moodle_token'])) {
                PCC_WooOTEC_Pro_Core::instance()->update_option('moodle_token', sanitize_text_field($_POST['moodle_token']));
            }
            $next_step = 2;
        } elseif ($step === 2) {
            // Validar conexión y avanzar
            $next_step = 3;
        } elseif ($step === 3) {
            // Guardar ID del Rol
            if (isset($_POST['student_role_id'])) {
                PCC_WooOTEC_Pro_Core::instance()->update_option('student_role_id', absint($_POST['student_role_id']));
            }
            $next_step = 4;
        } elseif ($step === 4) {
            // Guardar Parámetros Base
            if (isset($_POST['default_price'])) {
                PCC_WooOTEC_Pro_Core::instance()->update_option('default_price', sanitize_text_field($_POST['default_price']));
            }
            if (isset($_POST['default_instructor'])) {
                PCC_WooOTEC_Pro_Core::instance()->update_option('default_instructor', sanitize_text_field($_POST['default_instructor']));
            }
            $next_step = 5;
        } else {
            // Finalizar: Volver al Dashboard
            $redirect_url = admin_url('admin.php?page=pcc-woootec-chile');
            wp_safe_redirect($redirect_url);
            exit;
        }

        $redirect_url = admin_url('admin.php?page=pcc-woootec-asistente&step=' . $next_step);
        wp_safe_redirect($redirect_url);
        exit;
    }
}
