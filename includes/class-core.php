<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_WooOTEC_Pro_Core {

    private static ?PCC_WooOTEC_Pro_Core $instance = null;
    private array $defaults = array();

    public static function instance(): PCC_WooOTEC_Pro_Core {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $defaults = file_exists(PCC_WOOOTEC_PRO_PATH . 'config/defaults.php')
            ? require PCC_WOOOTEC_PRO_PATH . 'config/defaults.php'
            : array();

        $this->defaults = is_array($defaults) ? $defaults : array();
    }

    public function get_defaults(): array {
        return $this->defaults;
    }

    public function get_option(string $key, mixed $fallback = null): mixed {
        $option_name = 'pcc_woootec_pro_' . $key;
        $value = get_option($option_name, null);
        if ($value === null) {
            if (array_key_exists($key, $this->defaults)) {
                return $this->defaults[$key];
            }
            return $fallback;
        }

        return $value;
    }

    public function update_option(string $key, mixed $value): bool {
        $option_name = 'pcc_woootec_pro_' . $key;
        return update_option($option_name, $value);
    }

    public function boot(): void {
        $this->load_dependencies();
        $this->register_hooks();
    }

    public static function activate(): void {
        $core = self::instance();
        $core->load_dependencies();
        $core->register_default_options();

        if (class_exists('PCC_WooOTEC_Pro_Cron')) {
            PCC_WooOTEC_Pro_Cron::install();
        }
    }

    public static function deactivate(): void {
        if (file_exists(PCC_WOOOTEC_PRO_PATH . 'includes/class-cron.php')) {
            require_once PCC_WOOOTEC_PRO_PATH . 'includes/class-cron.php';
            if (class_exists('PCC_WooOTEC_Pro_Cron')) {
                PCC_WooOTEC_Pro_Cron::unschedule();
            }
        }
    }

    public function register_default_options(): void {
        foreach ($this->defaults as $key => $value) {
            $option_name = 'pcc_woootec_pro_' . $key;
            if (get_option($option_name, null) === null) {
                add_option($option_name, $value);
            }
        }
    }

    private function load_dependencies(): void {

        $files = array(
            'includes/class-logger.php',
            'includes/class-api.php',
            'includes/class-mapper.php',
            'includes/class-mailer.php',
            'includes/class-sso.php',
            'includes/class-sync.php',
            'includes/class-enroll.php',
            'includes/class-cron.php',
        );

        foreach ($files as $file) {
            $path = PCC_WOOOTEC_PRO_PATH . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    private function register_hooks(): void {

        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'register_runtime'));
        add_action('before_woocommerce_init', array($this, 'declare_woocommerce_compatibility'));
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('pcc-woootec-moodle', false, dirname(PCC_WOOOTEC_PRO_BASENAME) . '/languages');
    }

    public function register_runtime(): void {

        $this->register_default_options();

        // ADMIN
        if (is_admin()) {

            $admin_file = PCC_WOOOTEC_PRO_PATH . 'admin/class-admin.php';
            if (file_exists($admin_file)) {
                require_once $admin_file;
                if (class_exists('PCC_WooOTEC_Pro_Admin')) {
                    PCC_WooOTEC_Pro_Admin::instance()->boot();
                }
            }

        }

        // FRONTEND
        if (!is_admin()) {
            $frontend = PCC_WOOOTEC_PRO_PATH . 'public/class-frontend.php';
            if (file_exists($frontend)) {
                require_once $frontend;
                if (class_exists('PCC_WooOTEC_Pro_Frontend')) {
                    PCC_WooOTEC_Pro_Frontend::instance()->boot();
                }
            }
        }

        if (class_exists('PCC_WooOTEC_Pro_Mailer')) {
            PCC_WooOTEC_Pro_Mailer::instance();
        }

        if (class_exists('PCC_WooOTEC_Pro_Enroll')) {
            PCC_WooOTEC_Pro_Enroll::instance()->boot();
        }

        if (class_exists('PCC_WooOTEC_Pro_Cron')) {
            PCC_WooOTEC_Pro_Cron::boot();
        }

    }

    public function declare_woocommerce_compatibility(): void {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                PCC_WOOOTEC_PRO_FILE,
                true
            );
        }
    }
}
