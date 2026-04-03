<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_Asistente {
    private static ?Woo_OTEC_Moodle_Asistente $instance = null;

    public static function instance(): Woo_OTEC_Moodle_Asistente {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
    }

    public function boot(): void {
        add_action('admin_menu', array($this, 'add_wizard_page'));
        add_action('admin_post_woo_otec_moodle_asistente_save', array($this, 'handle_save'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function add_wizard_page(): void {
        add_submenu_page(
            null,
            esc_html__('Moodle wizard', 'woo-otec-moodle'),
            esc_html__('Moodle wizard', 'woo-otec-moodle'),
            'manage_options',
            'woo-otec-asistente',
            array($this, 'render_wizard')
        );
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'woo-otec-asistente') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('woo-otec-wizard-css', WOO_OTEC_MOODLE_URL . 'admin/asistente/assets/css/asistente.css', array(), WOO_OTEC_MOODLE_VERSION);
    }

    public function render_wizard(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'woo-otec-moodle'));
        }

        $step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
        $step = max(1, min(5, $step));

        $view_path = WOO_OTEC_MOODLE_PATH . 'admin/asistente/views/plantilla.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('The main wizard template could not be found.', 'woo-otec-moodle') . '</p></div>';
        }
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized request.', 'woo-otec-moodle'));
        }

        check_admin_referer('woo_otec_moodle_asistente_save', 'wizard_nonce');

        $step = isset($_POST['step']) ? (int) $_POST['step'] : 1;

        if ($step === 1) {
            // Guardar URL & Token
            if (isset($_POST['moodle_url'])) {
                Woo_OTEC_Moodle_Core::instance()->update_option('moodle_url', esc_url_raw($_POST['moodle_url']));
            }
            if (isset($_POST['moodle_token'])) {
                Woo_OTEC_Moodle_Core::instance()->update_option('moodle_token', sanitize_text_field($_POST['moodle_token']));
            }
            $next_step = 2;
        } elseif ($step === 2) {
            // Validar conexion y avanzar
            $next_step = 3;
        } elseif ($step === 3) {
            // Guardar ID del Rol
            if (isset($_POST['student_role_id'])) {
                Woo_OTEC_Moodle_Core::instance()->update_option('student_role_id', absint($_POST['student_role_id']));
            }
            $next_step = 4;
        } elseif ($step === 4) {
            // Save base settings
            if (isset($_POST['default_price'])) {
                Woo_OTEC_Moodle_Core::instance()->update_option('default_price', sanitize_text_field($_POST['default_price']));
            }
            if (isset($_POST['default_instructor'])) {
                Woo_OTEC_Moodle_Core::instance()->update_option('default_instructor', sanitize_text_field($_POST['default_instructor']));
            }
            if (isset($_POST['default_image_id'])) {
                Woo_OTEC_Moodle_Core::instance()->update_option('default_image_id', absint($_POST['default_image_id']));
            }
            $next_step = 5;
        } else {
            // Finalizar: Volver al Dashboard
            $redirect_url = admin_url('admin.php?page=woo-otec-moodle');
            wp_safe_redirect($redirect_url);
            exit;
        }

        $redirect_url = admin_url('admin.php?page=woo-otec-asistente&step=' . $next_step);
        wp_safe_redirect($redirect_url);
        exit;
    }
}
