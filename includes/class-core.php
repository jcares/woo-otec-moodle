<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_Core {

    private static ?Woo_OTEC_Moodle_Core $instance = null;
    private array $defaults  = array();

    /** @var array<string, callable> Registro de servicios (Tarea #4) */
    private array $services  = array();

    /** @var array<string, mixed> Instancias resueltas */
    private array $resolved  = array();

    public static function instance(): Woo_OTEC_Moodle_Core {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $defaults = file_exists(WOO_OTEC_MOODLE_PATH . 'config/defaults.php')
            ? require WOO_OTEC_MOODLE_PATH . 'config/defaults.php'
            : array();

        $this->defaults = is_array($defaults) ? $defaults : array();
    }

    public function get_defaults(): array {
        return $this->defaults;
    }

    public function get_option(string $key, mixed $fallback = null): mixed {
        $option_name = 'woo_otec_moodle_' . $key;
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
        $option_name = 'woo_otec_moodle_' . $key;
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

        if (class_exists('Woo_OTEC_Moodle_Cron')) {
            Woo_OTEC_Moodle_Cron::install();
        }
    }

    public static function deactivate(): void {
        if (file_exists(WOO_OTEC_MOODLE_PATH . 'includes/class-cron.php')) {
            require_once WOO_OTEC_MOODLE_PATH . 'includes/class-cron.php';
            if (class_exists('Woo_OTEC_Moodle_Cron')) {
                Woo_OTEC_Moodle_Cron::unschedule();
            }
        }
    }

    public function register_default_options(): void {
        foreach ($this->defaults as $key => $value) {
            $option_name = 'woo_otec_moodle_' . $key;
            if (get_option($option_name, null) === null) {
                add_option($option_name, $value);
            }
        }
    }

    private function load_dependencies(): void {

        // La excepción debe cargarse ANTES que class-api.php (Tarea #5)
        $files = array(
            'includes/class-moodle-exception.php',
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
            $path = WOO_OTEC_MOODLE_PATH . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }

        // Registrar servicios en el contenedor (Tarea #4)
        $this->register_services();
    }

    // -------------------------------------------------------------------------
    // Contenedor de servicios (Tarea #4)
    // -------------------------------------------------------------------------

    /**
     * Registra una fábrica de servicio.
     *
     * @param string   $id      Identificador del servicio
     * @param callable $factory Función que retorna la instancia
     */
    public function register(string $id, callable $factory): void {
        $this->services[$id] = $factory;
        unset($this->resolved[$id]); // invalidar caché si se re-registra
    }

    /**
     * Resuelve y devuelve un servicio registrado.
     *
     * @throws \RuntimeException Si el servicio no está registrado.
     */
    public function make(string $id): mixed {
        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        if (!isset($this->services[$id])) {
            throw new \RuntimeException("Servicio '{$id}' no registrado en el contenedor.");
        }

        $this->resolved[$id] = ($this->services[$id])();
        return $this->resolved[$id];
    }

    /**
     * Registra los servicios principales del plugin.
     */
    private function register_services(): void {
        if (class_exists('Woo_OTEC_Moodle_API')) {
            $this->register('api', fn() => Woo_OTEC_Moodle_API::instance());
        }
        if (class_exists('Woo_OTEC_Moodle_Sync')) {
            $this->register('sync', fn() => Woo_OTEC_Moodle_Sync::instance());
        }
        if (class_exists('Woo_OTEC_Moodle_Mailer')) {
            $this->register('mailer', fn() => Woo_OTEC_Moodle_Mailer::instance());
        }
        if (class_exists('Woo_OTEC_Moodle_Logger')) {
            $this->register('logger', fn() => Woo_OTEC_Moodle_Logger::class);
        }
        if (class_exists('Woo_OTEC_Moodle_Enroll')) {
            $this->register('enroll', fn() => Woo_OTEC_Moodle_Enroll::instance());
        }
    }

    private function register_hooks(): void {

        add_action('init', array($this, 'register_runtime'));
        add_action('before_woocommerce_init', array($this, 'declare_woocommerce_compatibility'));
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('woo-otec-moodle', false, dirname(WOO_OTEC_MOODLE_BASENAME) . '/languages');
    }

    public function register_runtime(): void {

        $this->register_default_options();

        // ADMIN
        if (is_admin()) {

            $settings_file = WOO_OTEC_MOODLE_PATH . 'admin/class-settings.php';
            if (file_exists($settings_file)) {
                require_once $settings_file;
            }

            $ajax_file = WOO_OTEC_MOODLE_PATH . 'admin/class-ajax-handler.php';
            if (file_exists($ajax_file)) {
                require_once $ajax_file;
            }

            $admin_file = WOO_OTEC_MOODLE_PATH . 'admin/class-admin.php';
            if (file_exists($admin_file)) {
                require_once $admin_file;
                if (class_exists('Woo_OTEC_Moodle_Admin')) {
                    Woo_OTEC_Moodle_Admin::instance()->boot();
                }
            }

            $wizard_file = WOO_OTEC_MOODLE_PATH . 'admin/asistente/clase-asistente.php';
            if (file_exists($wizard_file)) {
                require_once $wizard_file;
                if (class_exists('Woo_OTEC_Moodle_Asistente')) {
                    Woo_OTEC_Moodle_Asistente::instance()->boot();
                }
            }

        }

        // FRONTEND
        if (!is_admin()) {
            $frontend = WOO_OTEC_MOODLE_PATH . 'public/class-frontend.php';
            if (file_exists($frontend)) {
                require_once $frontend;
                if (class_exists('Woo_OTEC_Moodle_Frontend')) {
                    Woo_OTEC_Moodle_Frontend::instance()->boot();
                }
            }
        }

        if (class_exists('Woo_OTEC_Moodle_Mailer')) {
            Woo_OTEC_Moodle_Mailer::instance();
        }

        if (class_exists('Woo_OTEC_Moodle_Enroll')) {
            Woo_OTEC_Moodle_Enroll::instance()->boot();
        }

        if (class_exists('Woo_OTEC_Moodle_Cron')) {
            Woo_OTEC_Moodle_Cron::boot();
        }

    }

    public function declare_woocommerce_compatibility(): void {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                WOO_OTEC_MOODLE_FILE,
                true
            );
        }
    }
}
