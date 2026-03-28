<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_SSO {
    private static ?Woo_OTEC_Moodle_SSO $instance = null;

    public static function instance(): Woo_OTEC_Moodle_SSO {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function is_enabled(): bool {
        return Woo_OTEC_Moodle_Core::instance()->get_option('sso_enabled', 'yes') === 'yes';
    }

    public function get_base_url(): string {
        $custom = trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('sso_base_url', ''));
        if ($custom !== '') {
            return rtrim($custom, '/');
        }

        return Woo_OTEC_Moodle_API::instance()->get_moodle_url();
    }

    public function build_url(string $email, int $course_id = 0): string {
        if (!$this->is_enabled()) {
            return '';
        }

        $base_url = $this->get_base_url();
        if ($base_url === '') {
            return '';
        }

        $base_url = rtrim($base_url, '/');
        if (str_ends_with($base_url, '/login') || str_ends_with($base_url, '/login/index.php')) {
            return esc_url_raw($base_url);
        }

        return esc_url_raw($base_url . '/login');
    }

    public function store_order_urls(WC_Order $order, WP_User $user, array $course_ids): array {
        $urls = array();

        foreach ($course_ids as $course_id) {
            $url = $this->build_url((string) $user->user_email, (int) $course_id);
            if ($url !== '') {
                $urls[(int) $course_id] = $url;
            }
        }

        $order->update_meta_data('_pcc_moodle_access_urls', $urls);
        $order->update_meta_data('_moodle_access_url', !empty($urls) ? (string) reset($urls) : $this->build_url((string) $user->user_email));

        return $urls;
    }
}
