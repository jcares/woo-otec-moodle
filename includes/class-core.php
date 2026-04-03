<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_Core {

    private static ?Woo_OTEC_Moodle_Core $instance = null;
    private array $defaults  = array();

    /**
     * @var array<string, callable> Mapeo de servicios registrados en el contenedor.
     */
    private array $services  = array();

    /** @var array<string, mixed> Instancias resueltas */
    private array $resolved  = array();

    /** @var array<string, string>|null */
    private ?array $runtime_translations = null;

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

        // Requiere una carga estricta de dependencias; la clase de excepciones debe cargarse antes que la API base.
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

        // Inicialización y registro de los servicios base del plugin en el contenedor.
        $this->register_services();
    }

    /**
     * Contenedor de Servicios
     * Gestiona la inyección de dependencias para los componentes principales.
     */

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
            $this->register('logger', fn() => new Woo_OTEC_Moodle_Logger());
        }
        if (class_exists('Woo_OTEC_Moodle_Enroll')) {
            $this->register('enroll', fn() => Woo_OTEC_Moodle_Enroll::instance());
        }
    }

    private function register_hooks(): void {

        add_action('init', array($this, 'register_runtime'));
        add_action('init', array($this, 'load_textdomain'));
        add_action('init', array($this, 'normalize_localized_defaults'), 20);
        add_filter('gettext', array($this, 'translate_runtime_strings'), 20, 3);
        add_action('before_woocommerce_init', array($this, 'declare_woocommerce_compatibility'));
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('woo-otec-moodle', false, dirname(WOO_OTEC_MOODLE_BASENAME) . '/languages');
    }

    public function normalize_localized_defaults(): void {
        $migration_version = '20260328_3';
        if ((string) get_option('woo_otec_moodle_i18n_migration_version', '') === $migration_version) {
            return;
        }

        $locale = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
        $is_spanish = stripos($locale, 'es_') === 0 || stripos($locale, 'es') === 0;

        $text_defaults = array(
            'default_instructor' => 'Not assigned',
            'fallback_description' => 'Course synchronized automatically from Moodle.',
            'email_from_name' => 'Virtual Campus',
            'email_subject' => 'Welcome! Access your modules on {{sitio}}',
            'email_builder_heading' => 'Your access details are ready',
            'email_builder_intro' => 'Your purchase has been confirmed successfully. Here are your access details for the platform.',
            'email_builder_button_text' => 'Access my courses',
            'email_builder_footer' => 'If you need help, reply to this email and we will gladly assist you.',
            'single_description_heading' => 'Course description',
            'single_button_text' => 'Buy course',
            'shop_intro_title' => 'Explore our course catalog',
            'shop_intro_text' => 'Discover our available courses and choose the one that best fits your goal.',
            'shop_button_text' => 'View course',
            'cart_intro_title' => 'Your training cart',
            'cart_intro_text' => 'Review your courses before completing the payment.',
            'checkout_intro_title' => 'Last step to activate your courses',
            'checkout_intro_text' => 'Complete your information to activate immediate access to your courses.',
            'checkout_button_text' => 'Complete purchase',
            'portal_title' => 'My courses',
            'portal_intro_text' => 'From here you can enter each purchased course directly.',
            'portal_button_text' => 'Enter course',
        );

        $legacy_values = array(
            'shop_intro_title' => array('Explora nuestra oferta de cursos'),
            'shop_button_text' => array('Ver curso'),
            'cart_intro_title' => array('Tu carrito de capacitacion'),
            'portal_title' => array('Mis cursos'),
            'portal_intro_text' => array('Desde aqui puedes entrar directamente a cada curso comprado.'),
            'portal_button_text' => array('Entrar al curso'),
            'email_builder_heading' => array('Tus accesos ya estan listos'),
            'email_builder_intro' => array('Tu compra fue confirmada correctamente. Aqui tienes los datos para ingresar a tu plataforma.'),
            'email_builder_button_text' => array('Acceder a mis cursos'),
        );

        foreach ($text_defaults as $key => $english_value) {
            $option_name = 'woo_otec_moodle_' . $key;
            $current = get_option($option_name, null);
            if (!is_string($current)) {
                continue;
            }

            $allowed_values = array_merge(array($english_value), $legacy_values[$key] ?? array());
            if (!in_array($current, $allowed_values, true)) {
                continue;
            }

            update_option($option_name, $is_spanish ? __($english_value, 'woo-otec-moodle') : $english_value);
        }

        update_option('woo_otec_moodle_i18n_migration_version', $migration_version);
    }

    public function translate_runtime_strings(string $translation, string $text, string $domain): string {
        if ($domain !== 'woo-otec-moodle' || $text === '') {
            return $translation;
        }

        $map = $this->get_runtime_translations();
        if ($map === array()) {
            return $translation;
        }

        return $map[$text] ?? $translation;
    }

    private function get_runtime_translations(): array {
        if ($this->runtime_translations !== null) {
            return $this->runtime_translations;
        }

        $locale = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
        if (stripos($locale, 'es_') !== 0 && stripos($locale, 'es') !== 0) {
            $this->runtime_translations = array();
            return $this->runtime_translations;
        }

        $candidates = array(
            WOO_OTEC_MOODLE_PATH . 'languages/woo-otec-moodle-' . $locale . '.po',
            WOO_OTEC_MOODLE_PATH . 'languages/woo-otec-moodle-es_CL.po',
            WOO_OTEC_MOODLE_PATH . 'languages/woo-otec-moodle-es_ES.po',
        );

        foreach ($candidates as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $map = $this->parse_po_file($path);
            if ($map !== array()) {
                $this->runtime_translations = $map;
                return $this->runtime_translations;
            }
        }

        $this->runtime_translations = array();
        return $this->runtime_translations;
    }

    private function parse_po_file(string $path): array {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return array();
        }

        $translations = array();
        $state = null;
        $msgid = '';
        $msgstr = '';
        $is_fuzzy = false;

        $finalize = static function () use (&$translations, &$msgid, &$msgstr, &$is_fuzzy): void {
            if ($msgid === '') {
                $msgid = '';
                $msgstr = '';
                $is_fuzzy = false;
                return;
            }

            if (!$is_fuzzy && $msgstr !== '') {
                $translations[$msgid] = $msgstr;
            }

            $msgid = '';
            $msgstr = '';
            $is_fuzzy = false;
        };

        foreach ($lines as $line) {
            if (strncmp($line, '#, fuzzy', 8) === 0) {
                $is_fuzzy = true;
                continue;
            }

            if ($line === '') {
                $finalize();
                $state = null;
                continue;
            }

            if (strncmp($line, 'msgid ', 6) === 0) {
                if ($msgid !== '' || $msgstr !== '') {
                    $finalize();
                }
                $state = 'msgid';
                $msgid = $this->decode_po_string(substr($line, 6));
                continue;
            }

            if (strncmp($line, 'msgstr ', 7) === 0) {
                $state = 'msgstr';
                $msgstr = $this->decode_po_string(substr($line, 7));
                continue;
            }

            if ($line !== '' && $line[0] === '"') {
                if ($state === 'msgid') {
                    $msgid .= $this->decode_po_string($line);
                } elseif ($state === 'msgstr') {
                    $msgstr .= $this->decode_po_string($line);
                }
            }
        }

        $finalize();
        return $translations;
    }

    private function decode_po_string(string $value): string {
        $value = trim($value);
        $value = trim($value, '"');
        return stripcslashes($value);
    }

    public function register_runtime(): void {

        $this->register_default_options();

        // Inicialización del panel de administración
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

        // Inicialización de la vista pública (Frontend)
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
