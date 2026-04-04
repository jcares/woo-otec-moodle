<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_Enroll {
    public const RETRY_HOOK = 'woo_otec_moodle_retry_enrollment';

    private static ?Woo_OTEC_Moodle_Enroll $instance = null;

    public static function instance(): Woo_OTEC_Moodle_Enroll {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function boot(): void {
        add_action('woocommerce_order_status_completed', array($this, 'handle_completed_order'));
        add_action('woocommerce_order_status_processing', array($this, 'handle_completed_order'));
        add_action('template_redirect', array($this, 'maybe_redirect_after_purchase'));
        add_action(self::RETRY_HOOK, array($this, 'retry_enrollment'));
        add_action('woocommerce_thankyou', array($this, 'render_thankyou_actions'), 20);
        add_action('woocommerce_order_details_after_order_table', array($this, 'render_order_access_buttons'));
        add_filter('woocommerce_order_actions', array($this, 'register_order_actions'));
        add_action('woocommerce_order_action_pcc_resend_course_email', array($this, 'handle_resend_order_email'));
    }

    public function handle_completed_order(int $order_id): void {
        Woo_OTEC_Moodle_Logger::info('Hook de orden completada (o processing) disparado', array('order_id' => $order_id));
        $this->process_order($order_id, false, false);
    }

    public function retry_enrollment(int $order_id): void {
        $this->process_order((int) $order_id, false, true);
    }

    public function handle_resend_order_email(WC_Order $order): void {
        $this->send_order_access_email($order, true);
        $order->add_order_note('PCC WooOTEC: correo de acceso reenviado manualmente.');
        $order->save();
    }

    public function maybe_redirect_after_purchase(): void {
        if (Woo_OTEC_Moodle_Core::instance()->get_option('redirect_after_purchase', 'no') !== 'yes') {
            return;
        }

        if (!function_exists('is_order_received_page') || !is_order_received_page()) {
            return;
        }

        $order_id = absint(get_query_var('order-received'));
        if ($order_id <= 0) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        $url = (string) $order->get_meta('_moodle_access_url');
        if ($url !== '') {
            wp_safe_redirect($url);
            exit;
        }
    }

    public function render_email_preview(): string {
        $sample = $this->get_sample_email_data();
        return Woo_OTEC_Moodle_Mailer::instance()->render_template($sample, true);
    }

    public function send_test_email(string $recipient): bool|WP_Error {
        $recipient = sanitize_email($recipient);
        if ($recipient === '') {
            return new WP_Error('pcc_invalid_test_email', __('You must provide a valid test email.', 'woo-otec-moodle'));
        }

        $subject = Woo_OTEC_Moodle_Mailer::instance()->render_subject($this->get_sample_email_data());
        $body = $this->render_email_preview();
        $sent = Woo_OTEC_Moodle_Mailer::instance()->send($recipient, $subject, $body);

        if (!$sent) {
            Woo_OTEC_Moodle_Logger::error('Fallo envío de correo de prueba', array('recipient' => $recipient));
            return new WP_Error('pcc_test_email_failed', __('The test email could not be sent.', 'woo-otec-moodle'));
        }

        Woo_OTEC_Moodle_Logger::info('Correo de prueba enviado', array('recipient' => $recipient));
        return true;
    }

    public function render_thankyou_actions(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        $this->render_access_box($order);
    }

    public function render_order_access_buttons($order): void {
        if (is_numeric($order)) {
            $order = wc_get_order((int) $order);
        }

        if (!$order instanceof WC_Order) {
            return;
        }

        $this->render_access_box($order);
    }

    public function register_order_actions(array $actions): array {
        $actions['pcc_resend_course_email'] = __('PCC: resend access email', 'woo-otec-moodle');
        return $actions;
    }

    private function process_order(int $order_id, bool $force_email = false, bool $is_retry = false): bool {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return false;
        }

        if (!in_array($order->get_status(), array('completed', 'processing'), true)) {
            return false;
        }

        Woo_OTEC_Moodle_Logger::info('Compra detectada', array('order_id' => $order_id, 'retry' => $is_retry, 'status' => $order->get_status()));

        $learner = $this->resolve_learner_data($order);
        if (!$learner) {
            Woo_OTEC_Moodle_Logger::error('No fue posible resolver datos del alumno', array('order_id' => $order_id));
            return false;
        }

        $course_ids = $this->get_order_course_ids($order);
        if (empty($course_ids)) {
            Woo_OTEC_Moodle_Logger::info('La orden no contiene cursos de Moodle. Saltando matrícula.', array('order_id' => $order_id));
            $order->update_meta_data('_pcc_moodle_enrollment_complete', '1');
            $order->save();
            return true;
        }

        $moodle_result = Woo_OTEC_Moodle_API::instance()->get_or_create_user($learner);
        if (is_wp_error($moodle_result)) {
            Woo_OTEC_Moodle_Logger::error('Error al crear/reutilizar usuario Moodle', array('order_id' => $order_id, 'error' => $moodle_result->get_error_message()));
            $this->schedule_retry($order);
            return false;
        }

        $moodle_user_id = (int) ($moodle_result['id'] ?? 0);
        if (!empty($learner['wp_user_id'])) {
            update_user_meta((int) $learner['wp_user_id'], '_pcc_moodle_user_id', $moodle_user_id);
        }

        $order->update_meta_data('_pcc_moodle_user_id', (string) $moodle_user_id);

        if (!empty($moodle_result['created'])) {
            Woo_OTEC_Moodle_Logger::info('Usuario Moodle creado', array('order_id' => $order_id, 'email' => $learner['email'], 'moodle_user_id' => $moodle_user_id));
        } else {
            Woo_OTEC_Moodle_Logger::info('Usuario Moodle reutilizado', array('order_id' => $order_id, 'email' => $learner['email'], 'moodle_user_id' => $moodle_user_id));
        }

        $already = $order->get_meta('_pcc_moodle_enrolled_courses');
        if (!is_array($already)) {
            $already = array();
        }

        $enrolled = array_values(array_unique(array_map('intval', $already)));
        $failed = array();

        foreach ($course_ids as $course_id) {
            if (in_array($course_id, $enrolled, true)) {
                continue;
            }

            $enroll_ok = Woo_OTEC_Moodle_API::instance()->enroll_user($moodle_user_id, $course_id);
            if ($enroll_ok) {
                $enrolled[] = $course_id;
                Woo_OTEC_Moodle_Logger::info('Matrícula exitosa', array('order_id' => $order_id, 'course_id' => $course_id, 'moodle_user_id' => $moodle_user_id));
            } else {
                $failed[] = $course_id;
                Woo_OTEC_Moodle_Logger::error('Matrícula fallida', array('order_id' => $order_id, 'course_id' => $course_id, 'moodle_user_id' => $moodle_user_id));
            }
        }

        $urls = Woo_OTEC_Moodle_SSO::instance()->store_order_urls($order, $this->build_virtual_wp_user($learner), $enrolled);
        $order->update_meta_data('_pcc_moodle_enrolled_courses', array_values(array_unique($enrolled)));
        $order->update_meta_data('_pcc_moodle_enrollment_complete', empty($failed) ? '1' : '0');
        $order->update_meta_data('_pcc_moodle_enrollment_last', array(
            'enrolled' => array_values(array_unique($enrolled)),
            'failed'   => array_values(array_unique($failed)),
        ));
        $order->save();

        if (!empty($failed)) {
            $this->schedule_retry($order);
            return false;
        }

        $this->clear_retry($order);
        $this->send_order_access_email($order, $force_email, $learner, $moodle_result['password'] ?? null, $urls);
        return true;
    }

    private function resolve_learner_data(WC_Order $order): array|false {
        $user_id = (int) $order->get_user_id();
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            if ($user instanceof WP_User) {
                return array(
                    'wp_user_id' => $user->ID,
                    'firstname'  => $user->first_name !== '' ? $user->first_name : $order->get_billing_first_name(),
                    'lastname'   => $user->last_name !== '' ? $user->last_name : $order->get_billing_last_name(),
                    'email'      => $user->user_email,
                    'display'    => $user->display_name,
                );
            }
        }

        $email = sanitize_email((string) $order->get_billing_email());
        if ($email === '') {
            return false;
        }

        $existing = get_user_by('email', $email);
        if ($existing instanceof WP_User) {
            return array(
                'wp_user_id' => $existing->ID,
                'firstname'  => $existing->first_name !== '' ? $existing->first_name : $order->get_billing_first_name(),
                'lastname'   => $existing->last_name !== '' ? $existing->last_name : $order->get_billing_last_name(),
                'email'      => $existing->user_email,
                'display'    => $existing->display_name,
            );
        }

        return array(
            'wp_user_id' => 0,
            'firstname'  => (string) ($order->get_billing_first_name() ?: __('Student', 'woo-otec-moodle')),
            'lastname'   => (string) ($order->get_billing_last_name() ?: __('Student', 'woo-otec-moodle')),
            'email'      => $email,
            'display'    => trim((string) ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())),
        );
    }

    private function build_virtual_wp_user(array $learner): WP_User {
        $user = new WP_User();
        $user->ID = (int) ($learner['wp_user_id'] ?? 0);
        $user->user_email = (string) ($learner['email'] ?? '');
        $user->first_name = (string) ($learner['firstname'] ?? '');
        $user->last_name = (string) ($learner['lastname'] ?? '');
        $user->display_name = (string) ($learner['display'] ?? trim($user->first_name . ' ' . $user->last_name));
        return $user;
    }

    private function get_order_course_ids(WC_Order $order): array {
        $course_ids = array();

        /** @var \WC_Order_Item $item */
        foreach ($order->get_items() as $item) {
            $product_id = (int) $item->get_product_id();
            $moodle_id = (int) get_post_meta($product_id, '_moodle_id', true);
            if ($moodle_id > 0) {
                $course_ids[$moodle_id] = $moodle_id;
            }
        }

        return array_values($course_ids);
    }

    private function schedule_retry(WC_Order $order): void {
        $limit = max(1, (int) Woo_OTEC_Moodle_Core::instance()->get_option('retry_limit', 3));
        $attempts = (int) $order->get_meta('_pcc_retry_attempts');

        if ($attempts >= $limit) {
            return;
        }

        $attempts++;
        $order->update_meta_data('_pcc_retry_attempts', (string) $attempts);
        $order->save();

        wp_schedule_single_event(time() + (5 * MINUTE_IN_SECONDS), self::RETRY_HOOK, array((int) $order->get_id()));
    }

    private function clear_retry(WC_Order $order): void {
        $order->delete_meta_data('_pcc_retry_attempts');
        $order->save();
    }

    private function send_order_access_email(WC_Order $order, bool $force = false, ?array $learner = null, ?string $password = null, ?array $urls = null): bool {
        $email_enabled = Woo_OTEC_Moodle_Core::instance()->get_option('email_enabled', 'yes');
        
        if ($email_enabled !== 'yes') {
            Woo_OTEC_Moodle_Logger::info('Envío de email saltado: deshabilitado en configuración', array('order_id' => $order->get_id()));
            return false;
        }

        // Version: 2.1.25

        if (!$force && $order->get_meta('_pcc_access_email_sent')) {
            Woo_OTEC_Moodle_Logger::info('Envío de email saltado: ya fue enviado anteriormente', array('order_id' => $order->get_id()));
            return false;
        }

        $learner = $learner ?: $this->resolve_learner_data($order);
        if (!$learner) {
            Woo_OTEC_Moodle_Logger::error('No se pudo enviar email: no hay datos del alumno.', array('order_id' => $order->get_id()));
            return false;
        }

        $urls = is_array($urls) ? $urls : (array) $order->get_meta('_pcc_moodle_access_urls');
        $data = $this->build_email_data($order, $learner, $password, $urls);
        $subject = Woo_OTEC_Moodle_Mailer::instance()->render_subject($data);
        $body = Woo_OTEC_Moodle_Mailer::instance()->render_template($data, false);
        
        Woo_OTEC_Moodle_Logger::info('Intentando enviar email de acceso', array('order_id' => $order->get_id(), 'email' => $learner['email']));
        $sent = Woo_OTEC_Moodle_Mailer::instance()->send((string) $learner['email'], $subject, $body);

        if ($sent) {
            $order->update_meta_data('_pcc_access_email_sent', '1');
            $order->save();
            Woo_OTEC_Moodle_Logger::info('Correo de acceso enviado exitosamente', array('order_id' => $order->get_id(), 'email' => $learner['email']));
            return true;
        }

        Woo_OTEC_Moodle_Logger::error('Fallo crítico al enviar correo de acceso (wp_mail devolvió false)', array('order_id' => $order->get_id(), 'email' => $learner['email']));
        return false;
    }

    private function build_email_data(WC_Order $order, array $learner, ?string $password, array $urls): array {
        $course_names = array();
        /** @var \WC_Order_Item $item */
        foreach ($order->get_items() as $item) {
            $product_id = (int) $item->get_product_id();
            if ((int) get_post_meta($product_id, '_moodle_id', true) > 0) {
                $course_names[] = $item->get_name();
            }
        }

        return array(
            'nombre'      => trim((string) (($learner['firstname'] ?? '') . ' ' . ($learner['lastname'] ?? ''))),
            'email'       => (string) ($learner['email'] ?? ''),
            'password'    => $password !== null && $password !== '' ? $password : __('Use your current Moodle password', 'woo-otec-moodle'),
            'url_acceso'  => !empty($urls) ? (string) reset($urls) : (string) $order->get_meta('_moodle_access_url'),
            'cursos'      => implode('<br>', array_map('esc_html', $course_names)),
            'sitio'       => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        );
    }

    private function get_sample_email_data(): array {
        return array(
            'nombre'     => __('Demo student', 'woo-otec-moodle'),
            'email'      => 'student@example.com',
            'password'   => 'TempPassword123!',
            'url_acceso' => Woo_OTEC_Moodle_SSO::instance()->build_url('student@example.com', 123),
            'cursos'     => __('Demo Course 1', 'woo-otec-moodle') . '<br>' . __('Demo Course 2', 'woo-otec-moodle'),
            'sitio'      => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        );
    }

    private function render_access_box(WC_Order $order): void {
        $urls = (array) $order->get_meta('_pcc_moodle_access_urls');
        $single_url = (string) $order->get_meta('_moodle_access_url');

        if (empty($urls) && $single_url === '') {
            return;
        }

        echo '<section class="pcc-access-box"><h2>' . esc_html__('Access your courses', 'woo-otec-moodle') . '</h2><p>' . esc_html__('Use the following direct access links:', 'woo-otec-moodle') . '</p>';

        if (!empty($urls)) {
            echo '<ul class="pcc-access-links">';
            foreach ($urls as $course_id => $url) {
                echo '<li><a class="button" href="' . esc_url((string) $url) . '">' . esc_html__('Access course', 'woo-otec-moodle') . ' #' . (int) $course_id . '</a></li>';
            }
            echo '</ul>';
        } elseif ($single_url !== '') {
            echo '<p><a class="button" href="' . esc_url($single_url) . '">' . esc_html__('Access course', 'woo-otec-moodle') . '</a></p>';
        }

        echo '</section>';
    }
}
