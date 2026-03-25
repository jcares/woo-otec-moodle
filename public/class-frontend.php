<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_WooOTEC_Pro_Frontend {
    private static ?PCC_WooOTEC_Pro_Frontend $instance = null;

    public static function instance(): PCC_WooOTEC_Pro_Frontend {
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
        add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_my_account_access_action'), 10, 2);
        add_filter('woocommerce_single_product_zoom_enabled', '__return_false');

        // Hooks para templates personalizados
        add_action('woocommerce_before_shop_loop_item', array($this, 'maybe_open_template_wrapper'), 5);
        add_action('woocommerce_after_shop_loop_item', array($this, 'maybe_close_template_wrapper'), 25);
        add_filter('woocommerce_post_class', array($this, 'add_template_classes'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'maybe_render_single_meta'), 35);
        add_filter('woocommerce_get_item_data', array($this, 'add_course_data_to_cart'), 10, 2);
    }

    public function add_course_data_to_cart(array $item_data, array $cart_item): array {
        $product_id = $cart_item['product_id'];
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
            $style = PCC_WooOTEC_Pro_Core::instance()->get_option('template_style', 'classic');
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

        $style = PCC_WooOTEC_Pro_Core::instance()->get_option('template_style', 'classic');
        if ($style === 'classic') {
            return;
        }

        echo '<div class="pcc-template-inner">';
    }

    public function maybe_close_template_wrapper(): void {
        global $product;
        if (!$product || !get_post_meta($product->get_id(), '_moodle_id', true)) {
            return;
        }

        $style = PCC_WooOTEC_Pro_Core::instance()->get_option('template_style', 'classic');
        if ($style === 'classic') {
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

        $style = PCC_WooOTEC_Pro_Core::instance()->get_option('template_style', 'classic');
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

    private function get_selected_template_fields(): array {
        $fields = (array) PCC_WooOTEC_Pro_Core::instance()->get_option('template_fields', array());
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
            '_end_date'   => 'Fecha de término',
            '_duration'   => 'Duración',
            '_modality'   => 'Modalidad',
            '_location'   => 'Ubicación',
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
        wp_enqueue_style('pcc-woootec-frontend', PCC_WOOOTEC_PRO_URL . 'assets/css/frontend.css', $deps, PCC_WOOOTEC_PRO_VERSION);
    }

    public function render_my_courses_shortcode(): string {
        if (!is_user_logged_in()) {
            return '<p>Debes iniciar sesión para ver tus cursos.</p>';
        }

        if (!function_exists('wc_get_orders')) {
            return '<p>WooCommerce no está disponible.</p>';
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
                    'image'      => get_the_post_thumbnail_url($product_id, 'medium') ?: PCC_WOOOTEC_PRO_URL . 'assets/images/default-course.jpg',
                    'instructor' => (string) get_post_meta($product_id, '_instructor', true),
                    'start_date' => (int) get_post_meta($product_id, '_start_date', true),
                    'end_date'   => (int) get_post_meta($product_id, '_end_date', true),
                    'access_url' => PCC_WooOTEC_Pro_SSO::instance()->build_url((string) $user->user_email, $course_id),
                );
            }
        }

        ob_start();
        $template = PCC_WOOOTEC_PRO_PATH . 'public/templates/my-courses.php';
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
}

