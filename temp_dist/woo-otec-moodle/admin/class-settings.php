<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestiona el registro del menú, las opciones del plugin y los helpers de vista del panel.
 * Extraído de Woo_OTEC_Moodle_Admin para separar responsabilidades.
 */
final class Woo_OTEC_Moodle_Settings {
    private static ?Woo_OTEC_Moodle_Settings $instance = null;

    public static function instance(): Woo_OTEC_Moodle_Settings {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function boot(): void {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Registro de Menús y Submenús
     * Inyecta las interfaces administrativas del plugin dentro del panel de WordPress.
     */

    public function register_menu(): void {
        add_menu_page(
            esc_html__('Woo OTEC Moodle', 'woo-otec-moodle'),
            esc_html__('Woo OTEC Moodle', 'woo-otec-moodle'),
            'manage_options',
            'woo-otec-moodle',
            array(Woo_OTEC_Moodle_Admin::instance(), 'render_settings_page'),
            'dashicons-welcome-learn-more',
            25
        );

        add_submenu_page('woo-otec-moodle', esc_html__('Settings', 'woo-otec-moodle'), esc_html__('Settings', 'woo-otec-moodle'), 'manage_options', 'woo-otec-moodle', array(Woo_OTEC_Moodle_Admin::instance(), 'render_settings_page'));
        add_submenu_page('woo-otec-moodle', esc_html__('Synchronization', 'woo-otec-moodle'), esc_html__('Synchronization', 'woo-otec-moodle'), 'manage_options', 'woo-otec-moodle-sync', array(Woo_OTEC_Moodle_Admin::instance(), 'render_sync_page'));
        add_submenu_page('woo-otec-moodle', esc_html__('Logs', 'woo-otec-moodle'), esc_html__('Logs', 'woo-otec-moodle'), 'manage_options', 'woo-otec-moodle-logs', array(Woo_OTEC_Moodle_Admin::instance(), 'render_logs_page'));
    }

    /**
     * Registro de Opciones (Settings API)
     * Define los parámetros de configuración y sus respectivos validadores dentro de la base de datos de WordPress.
     */

    public function register_settings(): void {
        $fields = array(
            'moodle_url'           => 'esc_url_raw',
            'moodle_token'         => 'sanitize_text_field',
            'moodle_whatsapp_phone'=> 'sanitize_text_field',
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
            'email_builder_enabled'=> array($this, 'sanitize_checkbox'),
            'email_builder_heading'=> 'sanitize_text_field',
            'email_builder_intro'  => 'sanitize_textarea_field',
            'email_builder_button_text' => 'sanitize_text_field',
            'email_builder_footer' => 'sanitize_textarea_field',
            'email_logo_id'        => 'absint',
            'email_color_primary'  => 'sanitize_hex_color',
            'email_color_accent'   => 'sanitize_hex_color',
            'email_color_bg'       => 'sanitize_hex_color',
            'email_template'       => array($this, 'sanitize_email_template'),
            'email_test_recipient' => 'sanitize_email',
            'retry_limit'          => 'absint',
            'pcc_color_primary'    => 'sanitize_hex_color',
            'pcc_color_secondary'  => 'sanitize_hex_color',
            'pcc_color_text'       => 'sanitize_hex_color',
            'pcc_color_accent'     => 'sanitize_hex_color',
            'appearance_profile'   => 'sanitize_text_field',
            'single_description_heading' => 'sanitize_text_field',
            'single_button_text'   => 'sanitize_text_field',
            'single_button_color'  => 'sanitize_hex_color',
            'shop_intro_text'      => 'sanitize_text_field',
            'shop_intro_title'     => 'sanitize_text_field',
            'shop_button_text'     => 'sanitize_text_field',
            'shop_color_bg'        => 'sanitize_hex_color',
            'shop_color_title'     => 'sanitize_hex_color',
            'shop_color_text'      => 'sanitize_hex_color',
            'shop_color_button'    => 'sanitize_hex_color',
            'cart_intro_text'      => 'sanitize_text_field',
            'cart_intro_title'     => 'sanitize_text_field',
            'cart_color_bg'        => 'sanitize_hex_color',
            'cart_color_title'     => 'sanitize_hex_color',
            'cart_color_text'      => 'sanitize_hex_color',
            'cart_color_button'    => 'sanitize_hex_color',
            'checkout_intro_text'  => 'sanitize_text_field',
            'checkout_intro_title' => 'sanitize_text_field',
            'checkout_button_text' => 'sanitize_text_field',
            'checkout_color_bg'    => 'sanitize_hex_color',
            'checkout_color_title' => 'sanitize_hex_color',
            'checkout_color_text'  => 'sanitize_hex_color',
            'checkout_color_button'=> 'sanitize_hex_color',
            'portal_title'         => 'sanitize_text_field',
            'portal_intro_text'    => 'sanitize_text_field',
            'portal_button_text'   => 'sanitize_text_field',
            'portal_color_bg'      => 'sanitize_hex_color',
            'portal_color_title'   => 'sanitize_hex_color',
            'portal_color_text'    => 'sanitize_hex_color',
            'portal_color_button'  => 'sanitize_hex_color',
            'template_style'       => 'sanitize_text_field',
            'template_reference'   => 'absint',
        );

        register_setting(
            'woo_otec_moodle_settings',
            'woo_otec_moodle_template_fields',
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_array'),
                'default'           => Woo_OTEC_Moodle_Core::instance()->get_defaults()['template_fields'] ?? array(),
            )
        );

        register_setting(
            'woo_otec_moodle_settings',
            'woo_otec_moodle_mappings',
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_mappings'),
                'default'           => Woo_OTEC_Moodle_Mapper::instance()->get_default_mappings(),
            )
        );

        foreach ($fields as $field => $sanitize_callback) {
            register_setting(
                'woo_otec_moodle_settings',
                'woo_otec_moodle_' . $field,
                array(
                    'type'              => in_array($sanitize_callback, array('absint'), true) ? 'integer' : 'string',
                    'sanitize_callback' => $sanitize_callback,
                    'default'           => Woo_OTEC_Moodle_Core::instance()->get_defaults()[$field] ?? '',
                )
            );
        }

        foreach (array('sso_enabled', 'auto_update', 'redirect_after_purchase', 'debug_enabled', 'email_enabled') as $field) {
            register_setting(
                'woo_otec_moodle_settings',
                'woo_otec_moodle_' . $field,
                array(
                    'type'              => 'string',
                    'sanitize_callback' => array($this, 'sanitize_checkbox'),
                    'default'           => Woo_OTEC_Moodle_Core::instance()->get_defaults()[$field] ?? 'no',
                )
            );
        }
    }

    /**
     * Callbacks de Sanitización
     * Filtrado riguroso de entradas asociadas a la Settings API según su naturaleza de datos.
     */

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
                'manual_value' => sanitize_text_field($config['manual_value'] ?? ''),
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

        return wp_kses($value, Woo_OTEC_Moodle_Mailer::instance()->get_email_allowed_html());
    }

    /**
     * Facilitadores Visuales (View Helpers)
     * Lógica de presentación delegada en componentes que componen la interfaz del administrador.
     */

    public function get_template_reference_products(): array {
        if (!function_exists('wc_get_products')) {
            return array();
        }

        $products = wc_get_products(array(
            'status'       => array('publish', 'draft', 'private'),
            'limit'        => 200,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'meta_key'     => '_moodle_id',
            'meta_compare' => 'EXISTS',
        ));

        return is_array($products) ? $products : array();
    }

    public function render_template_fields_markup(int $product_id, array $selected_fields, bool $echo = true): string {
        $meta_keys = $this->get_product_meta_keys($product_id);

        ob_start();
        $labels = $this->get_meta_labels();

        if (empty($meta_keys)) {

            echo '<p class="description">' . esc_html__('No metadata was found for the selected product.', 'woo-otec-moodle') . '</p>';

        } else {

            echo '<div class="pcc-template-fields-grid">';

            foreach ($meta_keys as $meta_key) {

                $checked  = in_array($meta_key, $selected_fields, true);
                $field_id = 'pcc-template-field-' . sanitize_key($meta_key);
                $label    = $labels[$meta_key] ?? $this->format_meta_label($meta_key);

                echo '<label class="pcc-meta-checkbox">';
                echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="woo_otec_moodle_template_fields[]" value="' . esc_attr($meta_key) . '" data-meta-key="' . esc_attr($meta_key) . '" data-meta-label="' . esc_attr($label) . '" ' . checked($checked, true, false) . '>';
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
                    'data-meta-key' => true,
                    'data-meta-label' => true,
                    'checked' => true,
                ),
                'span'  => array(),
                'p'     => array('class' => true),
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

            if (in_array($key, $exclude_exact, true)) {
                return false;
            }

            foreach ($exclude_patterns as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    return false;
                }
            }

            return true;
        });

        $keys = array_values(array_unique($keys));

        $priority = array(
            '_start_date',
            '_end_date',
            '_instructor',
            '_duration',
            'duration',
            '_modality',
            'modality',
            '_course_format',
            'course_format',
            '_course_shortname',
            '_course_code',
            '_course_language',
            '_course_visibility',
            '_course_category_name',
            '_price',
            '_regular_price',
            '_stock',
            '_stock_status',
            '_pcc_moodle_image_source',
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
            '_end_date'                    => 'Fecha en que finaliza el curso',
            '_start_date'                  => 'Fecha en que comienza el curso',
            '_instructor'                  => 'Nombre del profesor o relator',
            '_moodle_id'                   => 'Código interno del curso',
            '_moodle_category_id'          => 'Categoría del curso',
            '_course_category_name'        => 'Nombre de la categoría',
            'course_category_name'         => 'Nombre de la categoría',
            '_course_shortname'            => 'Nombre corto del curso',
            'course_shortname'             => 'Nombre corto del curso',
            '_course_code'                 => 'Código interno de Moodle',
            'course_code'                  => 'Código interno de Moodle',
            '_course_language'             => 'Idioma del curso',
            'course_language'              => 'Idioma del curso',
            '_course_visibility'           => 'Estado de publicación en Moodle',
            'course_visibility'            => 'Estado de publicación en Moodle',
            '_moodle_course_details'       => 'Descripción completa del curso',
            '_moodle_course_details_plain' => 'Resumen del curso',
            '_price'                       => 'Precio actual',
            '_regular_price'               => 'Precio normal (sin descuento)',
            '_sku'                         => 'Código del producto',
            '_thumbnail_id'                => 'Imagen principal',
            '_pcc_moodle_image_source'     => 'Portada del curso',
            'pcc_moodle_image_source'      => 'Portada del curso',
            '_virtual'                     => 'Curso en modalidad online',
            '_sold_individually'           => 'Compra limitada a una unidad por cliente',
            '_stock_status'                => 'Disponibilidad',
            '_stock'                       => 'Cupos disponibles',
            'total_sales'                  => 'Cantidad de compras realizadas',
            'moodle_course_id'             => 'Código alternativo del curso',
            '_pcc_synced'                  => 'Sincronizado con la plataforma',
            '_course_sence'                 => 'Código SENCE oficial',
            '_course_hours'                 => 'Horas totales del curso',
            '_product_attributes'          => 'Características del curso',
            '_tax_status'                  => 'Aplicación de impuestos',
            '_tax_class'                   => 'Tipo de impuesto',
            '_duration'                    => 'Duración del curso',
            'duration'                     => 'Duración del curso',
            '_modality'                    => 'Modalidad',
            'modality'                     => 'Modalidad',
            '_course_format'               => 'Formato del curso',
            'course_format'                => 'Formato del curso',
            '_sence_code'                  => 'Código SENCE',
            'sence_code'                   => 'Código SENCE',
            '_total_hours'                 => 'Horas totales',
            'total_hours'                  => 'Horas totales',
            '_sections_count'              => 'Secciones',
            'sections_count'               => 'Secciones',
            '_certificate_available'       => 'Certificado de finalización',
            'certificate_available'        => 'Certificado de finalización',
        );
    }

    public function get_meta_labels_map(): array {
        return $this->get_meta_labels();
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

