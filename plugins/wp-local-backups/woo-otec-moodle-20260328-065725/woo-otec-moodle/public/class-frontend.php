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

        // Hooks para templates personalizados
        add_action('woocommerce_before_shop_loop_item', array($this, 'maybe_open_template_wrapper'), 5);
        add_action('woocommerce_after_shop_loop_item', array($this, 'maybe_close_template_wrapper'), 25);
        add_filter('woocommerce_post_class', array($this, 'add_template_classes'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'maybe_render_single_meta'), 35);
        add_filter('woocommerce_get_item_data', array($this, 'add_course_data_to_cart'), 10, 2);
        add_action('woocommerce_before_main_content', array($this, 'render_shop_intro_text'), 8);
        add_action('woocommerce_before_cart', array($this, 'render_cart_intro_text'), 5);
        add_action('woocommerce_before_checkout_form', array($this, 'render_checkout_intro_text'), 5);
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
        foreach ($required as $row) {
            if (($row['value'] ?? '') === '') {
                continue;
            }
            echo '<div class="pcc-meta-item">';
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
        $heading = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('single_description_heading', 'Descripcion del curso'));
        echo '<h2>' . esc_html($heading !== '' ? $heading : 'Descripcion del curso') . '</h2>';
        echo wp_kses_post(wpautop($description));
        echo '</section>';
    }

    private function get_required_single_fields(int $product_id): array {
        $start = get_post_meta($product_id, '_start_date', true);
        $end = get_post_meta($product_id, '_end_date', true);
        $instructor = get_post_meta($product_id, '_instructor', true);
        $modality = get_post_meta($product_id, '_modality', true);
        $format = get_post_meta($product_id, '_course_format', true);
        $sections = get_post_meta($product_id, '_sections_count', true);
        $certificate = get_post_meta($product_id, '_certificate_available', true);

        $rows = array(
            array('label' => 'Fecha de inicio del curso', 'value' => $this->format_meta_value($start)),
            array('label' => 'Fecha final del curso', 'value' => $this->format_meta_value($end)),
            array('label' => 'Nombre del profesor', 'value' => (string) $instructor),
            array('label' => 'Modalidad', 'value' => (string) $modality),
            array('label' => 'Formato del curso', 'value' => (string) $format),
            array('label' => 'Secciones', 'value' => $sections !== '' ? (string) ((int) $sections) : ''),
        );

        if ((string) $certificate === 'yes') {
            $rows[] = array('label' => 'Certificado de finalizacion', 'value' => 'Incluido');
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
        foreach ($fields as $field_key) {
            $value = get_post_meta($product->get_id(), $field_key, true);
            if ($value === '' || $value === null) {
                continue;
            }

            $label = $this->humanize_meta_key($field_key);
            $display = $this->format_meta_value($value);
            if ($display === '') {
                continue;
            }

            $icon = $this->get_field_icon($field_key);

            echo '<div class="pcc-meta-item">';
            if ($icon) {
                echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
            }
            echo '<div class="pcc-meta-content">';
            echo '<span class="pcc-meta-label">' . esc_html($label) . '</span>';
            echo '<span class="pcc-meta-value">' . esc_html($display) . '</span>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    private function get_field_icon(string $key): string {
        $icons = array(
            '_instructor' => 'dashicons-businessman',
            '_start_date' => 'dashicons-calendar-alt',
            '_end_date'   => 'dashicons-calendar',
            '_duration'   => 'dashicons-clock',
            '_modality'   => 'dashicons-laptop',
            '_location'   => 'dashicons-location',
        );

        return $icons[$key] ?? 'dashicons-info';
    }

    private function humanize_meta_key(string $key): string {
        $labels = array(
            '_instructor' => 'Profesor',
            '_start_date' => 'Fecha de inicio',
            '_end_date'   => 'Fecha de termino',
            '_duration'   => 'Duracion',
            '_modality'   => 'Modalidad',
            '_location'   => 'Ubicacion',
        );

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        $key = trim($key);
        $key = ltrim($key, '_');
        $key = str_replace(array('_', '-'), ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        return $key !== '' ? ucwords($key) : 'Campo';
    }

    private function format_meta_value(mixed $value): string {
        if (is_array($value)) {
            $flat = array();
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $flat[] = (string) $item;
                    continue;
                }
                if (is_object($item) && method_exists($item, '__toString')) {
                    $flat[] = (string) $item;
                }
            }
            $flat = array_filter($flat, static function ($item): bool {
                return $item !== '';
            });
            return implode(', ', $flat);
        }

        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        if (ctype_digit($value)) {
            $int = (int) $value;
            if ($int > 1000000000 && $int < 4102444800) {
                return wp_date(get_option('date_format'), $int);
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
        $portal_title   = $core->get_option('portal_color_title', '#173246');
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
        .single-product.pcc-moodle-product .pcc-course-single-box .single_add_to_cart_button {
            background: {$single_btn} !important;
            border-color: {$single_btn} !important;
            color: #fff !important;
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
        return $custom !== '' ? $custom : 'Ver curso';
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
            return '<p>Debes iniciar sesion para ver tus cursos.</p>';
        }

        if (!function_exists('wc_get_orders')) {
            return '<p>WooCommerce no esta disponible.</p>';
        }

        $user = wp_get_current_user();
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
                'name' => 'Acceder al curso',
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
