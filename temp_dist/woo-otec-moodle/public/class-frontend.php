<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_Frontend {
    private static ?Woo_OTEC_Moodle_Frontend $instance = null;

    public static function instance(): Woo_OTEC_Moodle_Frontend {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function boot(): void {
        add_shortcode('pcc_mis_cursos', array($this, 'render_my_courses_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp', array($this, 'configure_single_product_layout'));
        add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_my_account_access_action'), 10, 2);
        add_filter('woocommerce_single_product_zoom_enabled', '__return_false');
        add_filter('body_class', array($this, 'add_body_classes'));
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'filter_single_add_to_cart_text'));
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'filter_loop_add_to_cart_text'), 10, 2);
        add_filter('woocommerce_order_button_text', array($this, 'filter_checkout_button_text'));
        add_filter('woocommerce_display_product_attributes', array($this, 'filter_product_attributes'), 20, 2);

        // Hooks para templates personalizados
        add_action('woocommerce_before_shop_loop_item', array($this, 'maybe_open_template_wrapper'), 5);
        add_action('woocommerce_after_shop_loop_item', array($this, 'maybe_close_template_wrapper'), 25);
        add_filter('woocommerce_post_class', array($this, 'add_template_classes'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'maybe_render_single_meta'), 35);
        add_action('woocommerce_before_add_to_cart_form', array($this, 'maybe_render_single_meta'), 5);
        add_filter('woocommerce_get_item_data', array($this, 'add_course_data_to_cart'), 10, 2);
        add_action('woocommerce_before_main_content', array($this, 'render_shop_intro_text'), 8);
        add_action('woocommerce_before_cart', array($this, 'render_cart_intro_text'), 5);
        add_action('woocommerce_before_checkout_form', array($this, 'render_checkout_intro_text'), 5);

        // OTEC Chile Pro Features
        add_filter('woocommerce_product_tabs', array($this, 'add_technical_data_tab'));
        add_action('woocommerce_product_meta_end', array($this, 'render_whatsapp_button'), 15);
        add_shortcode('pcc_ficha_curso', array($this, 'render_technical_data_shortcode'));
    }

    public function configure_single_product_layout(): void {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }

        if (!get_post_meta($product->get_id(), '_moodle_id', true)) {
            return;
        }

        $style = (string) Woo_OTEC_Moodle_Core::instance()->get_option('template_style', 'classic');
        if ($style !== 'pccurico') {
            return;
        }

        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
        remove_action('woocommerce_single_product_summary', array($this, 'maybe_render_single_meta'), 35);
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);

        add_action('woocommerce_single_product_summary', array($this, 'render_pccurico_single_summary'), 8);
        add_action('woocommerce_after_single_product_summary', array($this, 'render_pccurico_long_description'), 12);
    }

    public function filter_product_attributes(array $attributes, WC_Product $product): array {
        if (!$this->is_moodle_product($product->get_id())) {
            return $attributes;
        }

        foreach ($attributes as $key => $attribute) {
            // El key suele ser el meta_key formateado o el nombre del atributo
            if (isset($attribute['value'])) {
                $formatted_value = $this->format_meta_value($attribute['value']);
                if ($formatted_value !== $attribute['value']) {
                    $attributes[$key]['value'] = $formatted_value;
                }
            }
        }

        return $attributes;
    }

    public function add_course_data_to_cart(array $item_data, array $cart_item): array {
        $product_id = isset($cart_item['product_id']) ? (int) $cart_item['product_id'] : 0;
        if ($product_id <= 0) {
            return $item_data;
        }

        if (!get_post_meta($product_id, '_moodle_id', true)) {
            return $item_data;
        }

        $fields = array('_instructor', '_start_date', '_modality');
        foreach ($fields as $field) {
            $value = get_post_meta($product_id, $field, true);
            if ($value) {
                $item_data[] = array(
                    'name'  => $this->humanize_meta_key($field),
                    'value' => $this->format_meta_value($value),
                );
            }
        }

        return $item_data;
    }

    public function add_template_classes(array $classes, $product): array {
        if (!$product instanceof WC_Product) {
            $product = wc_get_product($product);
        }

        if ($product && get_post_meta($product->get_id(), '_moodle_id', true)) {
            $style = Woo_OTEC_Moodle_Core::instance()->get_option('template_style', 'classic');
            $classes[] = 'pcc-course-card';
            $classes[] = 'pcc-style-' . $style;
        }

        return $classes;
    }

    public function maybe_open_template_wrapper(): void {
        global $product;
        if (!$product || !get_post_meta($product->get_id(), '_moodle_id', true)) {
            return;
        }

        $style = Woo_OTEC_Moodle_Core::instance()->get_option('template_style', 'classic');
        if ($style === 'classic' || $style === 'pccurico') {
            return;
        }

        echo '<div class="pcc-template-inner">';
    }

    public function maybe_close_template_wrapper(): void {
        global $product;
        if (!$product || !get_post_meta($product->get_id(), '_moodle_id', true)) {
            return;
        }

        $style = Woo_OTEC_Moodle_Core::instance()->get_option('template_style', 'classic');
        if ($style === 'classic' || $style === 'pccurico') {
            return;
        }

        $fields = $this->get_selected_template_fields();
        $this->render_custom_fields($product, $fields);

        echo '</div>';
    }

    public function maybe_render_single_meta(): void {
        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }

        if (!get_post_meta($product->get_id(), '_moodle_id', true)) {
            return;
        }

        $style = Woo_OTEC_Moodle_Core::instance()->get_option('template_style', 'classic');
        if ($style === 'classic') {
            return;
        }

        $fields = $this->get_selected_template_fields();
        if (empty($fields)) {
            return;
        }

        echo '<div class="pcc-course-single-meta pcc-style-' . esc_attr($style) . '">';
        $this->render_custom_fields($product, $fields);
        echo '</div>';
    }

    public function render_pccurico_single_summary(): void {
        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }

        $product_id = $product->get_id();
        $required = $this->get_required_single_fields($product_id);

        echo '<div class="pcc-course-single-box">';
        echo '<h1 class="product_title entry-title">' . esc_html($product->get_name()) . '</h1>';

        $short = trim((string) $product->get_short_description());
        if ($short !== '') {
            echo '<div class="woocommerce-product-details__short-description"><p>' . esc_html($short) . '</p></div>';
        }

        echo '<div class="pcc-course-meta-fields pcc-grid-layout pcc-grid-layout--single">';
        echo '<div class="pcc-meta-section-header">' . esc_html__('Resumen del Curso', 'woo-otec-moodle') . '</div>';
        foreach ($required as $row) {
            if (($row['value'] ?? '') === '') {
                continue;
            }
            $icon_key = $row['icon_key'] ?? '';
            $svg = $this->get_svg_icon($icon_key);

            echo '<div class="pcc-meta-item">';
            if ($svg) {
                echo '<div class="pcc-meta-icon-circle">' . $svg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '<div class="pcc-meta-content">';
            echo '<span class="pcc-meta-label">' . esc_html((string) $row['label']) . '</span>';
            echo '<span class="pcc-meta-value">' . esc_html((string) $row['value']) . '</span>';
            echo '</div></div>';
        }
        echo '</div>';

        woocommerce_template_single_price();
        woocommerce_template_single_add_to_cart();
        echo '</div>';
    }

    public function render_pccurico_long_description(): void {
        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }

        $description = trim((string) $product->get_description());
        if ($description === '') {
            return;
        }

        echo '<section class="pcc-course-long-description">';
        $heading = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('single_description_heading', __('Course description', 'woo-otec-moodle')));
        echo '<h2>' . esc_html($heading !== '' ? $heading : __('Course description', 'woo-otec-moodle')) . '</h2>';
        echo wp_kses_post(wpautop($description));
        echo '</section>';
    }

    private function get_required_single_fields(int $product_id): array {
        $start = get_post_meta($product_id, '_start_date', true);
        $end = get_post_meta($product_id, '_end_date', true);
        $instructor = get_post_meta($product_id, '_instructor', true);
        $modality = get_post_meta($product_id, '_modality', true);
        $format = get_post_meta($product_id, '_course_format', true);
        $sence = get_post_meta($product_id, '_course_sence', true);

        $rows = array(
            array('label' => __('Start date', 'woo-otec-moodle'), 'value' => $this->format_meta_value($start), 'icon_key' => '_start_date'),
            array('label' => __('End date', 'woo-otec-moodle'), 'value' => $this->format_meta_value($end), 'icon_key' => '_end_date'),
            array('label' => __('Relator', 'woo-otec-moodle'), 'value' => (string) $instructor, 'icon_key' => '_instructor'),
            array('label' => __('Modality', 'woo-otec-moodle'), 'value' => (string) $modality, 'icon_key' => '_modality'),
        );

        if (!empty($sence)) {
            $rows[] = array('label' => __('Código SENCE', 'woo-otec-moodle'), 'value' => (string) $sence, 'icon_key' => '_course_sence');
        }

        return $rows;
    }

    private function get_selected_template_fields(): array {
        $fields = (array) Woo_OTEC_Moodle_Core::instance()->get_option('template_fields', array());
        $fields = array_values(array_unique(array_filter(array_map('strval', $fields))));
        return $fields;
    }

    private function render_custom_fields(WC_Product $product, array $fields): void {
        if (empty($fields)) {
            return;
        }

        echo '<div class="pcc-course-meta-fields pcc-grid-layout">';
        echo '<div class="pcc-meta-section-header">' . esc_html__('Datos del Curso', 'woo-otec-moodle') . '</div>';
        foreach ($fields as $field_key) {
            $value = get_post_meta($product->get_id(), $field_key, true);
            if ($value === '' || $value === null) {
                continue;
            }

            $label = $this->humanize_meta_key($field_key);
            $display = $this->format_meta_value($value);
            if ($display === '' || str_starts_with($display, 'moodle:') || str_starts_with($display, 'http')) {
                continue;
            }

            $svg = $this->get_svg_icon($field_key);

            echo '<div class="pcc-meta-item">';
            if ($svg) {
                echo '<div class="pcc-meta-icon-circle">' . $svg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '<div class="pcc-meta-content">';
            echo '<span class="pcc-meta-label">' . esc_html($label) . '</span>';
            echo '<span class="pcc-meta-value">' . esc_html($display) . '</span>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    private function get_svg_icon(string $key): string {
        $key = ltrim($key, '_');
        $icons = array(
            'instructor'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
            'relator'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
            'start_date'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            'end_date'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg>',
            'duration'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'modality'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
            'course_sence' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76z"/><polyline points="9 12 11 14 15 10"/></svg>',
            'whatsapp'      => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 1 1-7.6-11.7 8.38 8.38 0 0 1 3.8.9L21 3z"/></svg>',
        );

        return $icons[$key] ?? '';
    }

    /**
     * OTEC Pro: Botón de consulta por WhatsApp.
     */
    public function render_whatsapp_button(): void {
        global $product;
        if (!$product instanceof WC_Product || !$this->is_moodle_product($product->get_id())) {
            return;
        }

        $phone = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('moodle_whatsapp_phone', ''));
        if ($phone === '') {
            return;
        }

        $text = sprintf(
            __('Hola, me interesa obtener más información sobre el curso: %s', 'woo-otec-moodle'),
            $product->get_name()
        );

        $url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone) . '?text=' . rawurlencode($text);

        echo '<div class="pcc-whatsapp-support">';
        echo '<a href="' . esc_url($url) . '" class="button pcc-whatsapp-btn" target="_blank">';
        echo $this->get_svg_icon('whatsapp');
        echo '<span>' . esc_html__('Consultar por WhatsApp', 'woo-otec-moodle') . '</span>';
        echo '</a>';
        echo '</div>';
    }

    /**
     * OTEC Pro: Pestaña Técnica con toda la metadata.
     */
    public function add_technical_data_tab(array $tabs): array {
        global $product;
        if (!$product instanceof WC_Product || !$this->is_moodle_product($product->get_id())) {
            return $tabs;
        }

        $tabs['pcc_technical_data'] = array(
            'title'    => __('Datos del Curso', 'woo-otec-moodle'),
            'priority' => 25,
            'callback' => array($this, 'render_technical_data_tab_content'),
        );

        return $tabs;
    }

    public function render_technical_data_tab_content(): void {
        global $product;
        $fields = $this->get_selected_template_fields();
        if (empty($fields)) {
            $fields = array('_instructor', '_start_date', '_end_date', '_modality', '_course_sence', '_course_hours');
        }

        echo '<h3>' . esc_html__('Ficha Técnica del Curso', 'woo-otec-moodle') . '</h3>';
        $this->render_custom_fields($product, $fields);
    }

    /**
     * OTEC Pro: Shortcode para insertar la ficha técnica en Elementor.
     */
    public function render_technical_data_shortcode(array $atts): string {
        global $product;
        $current_product = $product;

        if (isset($atts['id'])) {
            $current_product = wc_get_product((int)$atts['id']);
        }

        if (!$current_product instanceof WC_Product) {
            return '';
        }

        ob_start();
        $fields = $this->get_selected_template_fields();
        echo '<div class="pcc-shortcode-ficha-tecnica">';
        $this->render_custom_fields($current_product, $fields);
        echo '</div>';

        return (string) ob_get_clean();
    }

    private function humanize_meta_key(string $key): string {
        $labels = array(
            '_instructor'   => 'Relator / Docente',
            'instructor'    => 'Relator / Docente',
            '_start_date'   => 'Fecha de Inicio',
            'start_date'    => 'Fecha de Inicio',
            '_end_date'     => 'Fecha de Término',
            'end_date'      => 'Fecha de Término',
            '_duration'     => 'Duración del curso',
            'duration'      => 'Duración del curso',
            '_modality'     => 'Modalidad de Estudio',
            'modality'      => 'Modalidad de Estudio',
            '_course_sence' => 'Código SENCE',
        );

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        $key = trim($key);
        $key = ltrim($key, '_');
        $key = str_replace(array('_', '-'), ' ', $key);
        return $key !== '' ? ucwords($key) : 'Información';
    }

    private function format_meta_value(mixed $value): string {
        if (is_array($value)) {
            $value = array_filter($value);
            return implode(', ', array_map([$this, 'format_meta_value'], $value));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        // Detección robusta de Timestamps Unix (Moodle suele enviar strings numéricos)
        if (is_numeric($value)) {
            $num = (int) $value;
            // Rango razonable para fechas de cursos (2010 - 2050)
            if ($num > 1262304000 && $num < 2524608000) {
                return wp_date(get_option('date_format'), $num);
            }
        }

        return $value;
    }

    public function enqueue_assets(): void {
        wp_enqueue_style('dashicons');
        $deps = array();
        wp_enqueue_style('woo-otec-frontend', WOO_OTEC_MOODLE_URL . 'assets/css/frontend.css', $deps, WOO_OTEC_MOODLE_VERSION);
        
        $core = Woo_OTEC_Moodle_Core::instance();
        $primary   = $core->get_option('pcc_color_primary', '#023E25');
        $secondary = $core->get_option('pcc_color_secondary', '#6EC1E4');
        $text      = $core->get_option('pcc_color_text', '#7A7A7A');
        $accent    = $core->get_option('pcc_color_accent', '#61CE70');
        $single_btn = $core->get_option('single_button_color', '#1f9d6f');

        $shop_bg    = $core->get_option('shop_color_bg', '#f8fbff');
        $shop_title = $core->get_option('shop_color_title', '#21405a');
        $shop_text  = $core->get_option('shop_color_text', '#2b4b63');
        $shop_btn   = $core->get_option('shop_color_button', '#0f3d5e');
        $cart_bg    = $core->get_option('cart_color_bg', '#f5fbf8');
        $cart_title = $core->get_option('cart_color_title', '#1d5a41');
        $cart_text  = $core->get_option('cart_color_text', '#355846');
        $cart_btn   = $core->get_option('cart_color_button', '#1f9d6f');
        $checkout_bg    = $core->get_option('checkout_color_bg', '#fff8f1');
        $checkout_title = $core->get_option('checkout_color_title', '#7b4b12');
        $checkout_text  = $core->get_option('checkout_color_text', '#6f5a40');
        $checkout_btn   = $core->get_option('checkout_color_button', '#d9822b');
        $portal_bg      = $core->get_option('portal_color_bg', '#f7fbff');
        $portal_title   = $core->get_option('portal_title', '#173246');
        $portal_text    = $core->get_option('portal_color_text', '#567187');
        $portal_btn     = $core->get_option('portal_color_button', '#0f3d5e');

        $custom_css = "
        .pcc-course-card,
        .pcc-style-academy,
        .pcc-style-market,
        .pcc-style-pccurico,
        .pcc-course-single-meta,
        .pcc-course-single-box,
        .pcc-course-long-description,
        .pcc-my-courses,
        .pcc-access-box {
            --pcc-primary-color: {$primary};
            --pcc-secondary-color: {$secondary};
            --pcc-text-color: {$text};
            --pcc-accent-color: {$accent};
        }
        .pcc-store-intro--shop {
            --pcc-area-bg: {$shop_bg};
            --pcc-area-title: {$shop_title};
            --pcc-area-text: {$shop_text};
        }
        .pcc-store-intro--cart {
            --pcc-area-bg: {$cart_bg};
            --pcc-area-title: {$cart_title};
            --pcc-area-text: {$cart_text};
        }
        .pcc-store-intro--checkout {
            --pcc-area-bg: {$checkout_bg};
            --pcc-area-title: {$checkout_title};
            --pcc-area-text: {$checkout_text};
        }
        .pcc-my-courses,
        .pcc-access-box {
            --pcc-portal-bg: {$portal_bg};
            --pcc-portal-title: {$portal_title};
            --pcc-portal-text: {$portal_text};
            --pcc-portal-button: {$portal_btn};
        }
        .post-type-archive-product .products .product .button,
        .tax-product_cat .products .product .button {
            background: {$shop_btn} !important;
            border-color: {$shop_btn} !important;
            color: #fff !important;
        }
        .single-product.pcc-moodle-product div.product .single_add_to_cart_button,
        .single-product.pcc-moodle-product .pcc-course-single-box .single_add_to_cart_button,
        .single-product div.product.pcc-course-card .single_add_to_cart_button,
        .single-product .product.pcc-course-card .single_add_to_cart_button,
        .single-product div.product.pcc-course-card form.cart .single_add_to_cart_button.button.alt,
        .single-product .product.pcc-course-card form.cart .single_add_to_cart_button.button.alt {
            background: {$single_btn} !important;
            background-color: {$single_btn} !important;
            background-image: none !important;
            border-color: {$single_btn} !important;
            color: #fff !important;
            box-shadow: none !important;
            opacity: 1 !important;
        }
        .woocommerce-cart .wc-proceed-to-checkout a.checkout-button {
            background: {$cart_btn} !important;
            border-color: {$cart_btn} !important;
            color: #fff !important;
        }
        .woocommerce-checkout #place_order {
            background: {$checkout_btn} !important;
            border-color: {$checkout_btn} !important;
            color: #fff !important;
        }";
        wp_add_inline_style('woo-otec-frontend', $custom_css);
    }

    public function filter_single_add_to_cart_text(string $text): string {
        global $product;
        if (!$product instanceof WC_Product || !$this->is_moodle_product($product->get_id())) {
            return $text;
        }

        $custom = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('single_button_text', ''));
        return $custom !== '' ? $custom : $text;
    }

    public function filter_loop_add_to_cart_text(string $text, $product): string {
        if (!$product instanceof WC_Product || !$this->is_moodle_product($product->get_id())) {
            return $text;
        }

        $custom = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('shop_button_text', ''));
        return $custom !== '' ? $custom : __('View course', 'woo-otec-moodle');
    }

    public function filter_checkout_button_text(string $text): string {
        $custom = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('checkout_button_text', ''));
        return $custom !== '' ? $custom : $text;
    }

    public function add_body_classes(array $classes): array {
        if (!function_exists('is_product') || !is_product()) {
            return $classes;
        }

        global $product;
        if ($product instanceof WC_Product && $this->is_moodle_product($product->get_id())) {
            $classes[] = 'pcc-moodle-product';
            $classes[] = 'pcc-template-' . sanitize_html_class((string) Woo_OTEC_Moodle_Core::instance()->get_option('template_style', 'classic'));
        }

        return $classes;
    }

    private function is_moodle_product(int $product_id): bool {
        return $product_id > 0 && (bool) get_post_meta($product_id, '_moodle_id', true);
    }

    public function render_my_courses_shortcode(): string {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must log in to view your courses.', 'woo-otec-moodle') . '</p>';
        }

        if (!function_exists('wc_get_orders')) {
            return '<p>' . esc_html__('WooCommerce is not available.', 'woo-otec-moodle') . '</p>';
        }

        $user = wp_get_current_user();
        /* Version: 2.1.23 */
        $orders = wc_get_orders(
            array(
                'customer_id' => $user->ID,
                'status'      => array('completed'),
                'limit'       => -1,
            )
        );

        $courses = array();
        foreach ($orders as $order) {
            /** @var WC_Order_Item $item */
            foreach ($order->get_items() as $item) {
                $product_id = (int) $item->get_product_id();
                if ($product_id <= 0 || isset($courses[$product_id])) {
                    continue;
                }

                $course_id = (int) get_post_meta($product_id, '_moodle_id', true);
                if ($course_id <= 0) {
                    continue;
                }

                $courses[$product_id] = array(
                    'title'      => get_the_title($product_id),
                    'image'      => get_the_post_thumbnail_url($product_id, 'medium') ?: WOO_OTEC_MOODLE_URL . 'assets/images/default-course.jpg',
                    'instructor' => (string) get_post_meta($product_id, '_instructor', true),
                    'start_date' => get_post_meta($product_id, '_start_date', true),
                    'end_date'   => get_post_meta($product_id, '_end_date', true),
                    'access_url' => Woo_OTEC_Moodle_SSO::instance()->build_url((string) $user->user_email, $course_id),
                );
            }
        }

        ob_start();
        $template = WOO_OTEC_MOODLE_PATH . 'public/templates/my-courses.php';
        if (file_exists($template)) {
            $courses = array_values($courses);
            include $template;
        }

        return (string) ob_get_clean();
    }

    public function add_my_account_access_action(array $actions, WC_Order $order): array {
        $url = (string) $order->get_meta('_moodle_access_url');
        if ($url !== '') {
            $actions['pcc_access_course'] = array(
                'url'  => esc_url($url),
                'name' => __('Access course', 'woo-otec-moodle'),
            );
        }

        return $actions;
    }

    public function render_shop_intro_text(): void {
        if (!function_exists('is_shop') || !is_shop() || is_product()) {
            return;
        }

        $title = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('shop_intro_title', ''));
        $text = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('shop_intro_text', ''));
        if ($title === '' && $text === '') {
            return;
        }

        echo '<div class="pcc-store-intro pcc-store-intro--shop">';
        if ($title !== '') {
            echo '<h3 class="pcc-store-intro__title">' . esc_html($title) . '</h3>';
        }
        if ($text !== '') {
            echo '<p>' . esc_html($text) . '</p>';
        }
        echo '</div>';
    }

    public function render_cart_intro_text(): void {
        if (!function_exists('is_cart') || !is_cart()) {
            return;
        }

        $title = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('cart_intro_title', ''));
        $text = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('cart_intro_text', ''));
        if ($title === '' && $text === '') {
            return;
        }

        echo '<div class="pcc-store-intro pcc-store-intro--cart">';
        if ($title !== '') {
            echo '<h3 class="pcc-store-intro__title">' . esc_html($title) . '</h3>';
        }
        if ($text !== '') {
            echo '<p>' . esc_html($text) . '</p>';
        }
        echo '</div>';
    }

    public function render_checkout_intro_text(): void {
        if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
            return;
        }

        $title = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('checkout_intro_title', ''));
        $text = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('checkout_intro_text', ''));
        if ($title === '' && $text === '') {
            return;
        }

        echo '<div class="pcc-store-intro pcc-store-intro--checkout">';
        if ($title !== '') {
            echo '<h3 class="pcc-store-intro__title">' . esc_html($title) . '</h3>';
        }
        if ($text !== '') {
            echo '<p>' . esc_html($text) . '</p>';
        }
        echo '</div>';
    }
}
