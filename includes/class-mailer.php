<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_WooOTEC_Pro_Mailer {
    private static ?PCC_WooOTEC_Pro_Mailer $instance = null;

    public static function instance(): PCC_WooOTEC_Pro_Mailer {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function render_subject(array $data): string {
        $subject = (string) PCC_WooOTEC_Pro_Core::instance()->get_option('email_subject', 'Acceso a tus cursos en {{sitio}}');
        return wp_strip_all_tags($this->replace_template_variables($subject, $data));
    }

    public function render_template(array $data, bool $preview = false): string {
        $template = (string) PCC_WooOTEC_Pro_Core::instance()->get_option('email_template', '');
        $template = html_entity_decode($template, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = $this->replace_template_variables($template, $data);

        if ($preview) {
            return '<div class="pcc-email-preview-html">' . wp_kses($html, $this->get_email_allowed_html()) . '</div>';
        }

        return $html;
    }

    public function send(string $recipient, string $subject, string $message): bool {
        $recipient = sanitize_email($recipient);
        if ($recipient === '') {
            return false;
        }

        add_filter('wp_mail_from', array($this, 'filter_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'filter_mail_from_name'));

        try {
            return wp_mail(
                $recipient,
                $subject,
                $message,
                array('Content-Type: text/html; charset=UTF-8')
            );
        } finally {
            remove_filter('wp_mail_from', array($this, 'filter_mail_from'));
            remove_filter('wp_mail_from_name', array($this, 'filter_mail_from_name'));
        }
    }

    public function filter_mail_from(string $email): string {
        $configured = sanitize_email((string) PCC_WooOTEC_Pro_Core::instance()->get_option('email_from_address', ''));
        if ($configured !== '') {
            return $configured;
        }

        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        if ($host === '') {
            return $email;
        }

        return 'no-reply@' . preg_replace('/^www\./', '', strtolower($host));
    }

    public function filter_mail_from_name(string $name): string {
        $configured = sanitize_text_field((string) PCC_WooOTEC_Pro_Core::instance()->get_option('email_from_name', ''));
        if ($configured !== '') {
            return $configured;
        }

        return wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    }

    private function replace_template_variables(string $template, array $data): string {
        return str_replace(
            array('{{nombre}}', '{{email}}', '{{password}}', '{{cursos}}', '{{url_acceso}}', '{{sitio}}'),
            array(
                (string) ($data['nombre'] ?? ''),
                (string) ($data['email'] ?? ''),
                (string) ($data['password'] ?? ''),
                (string) ($data['cursos'] ?? ''),
                (string) ($data['url_acceso'] ?? ''),
                (string) ($data['sitio'] ?? ''),
            ),
            $template
        );
    }

    public function get_email_allowed_html(): array {
        $allowed = wp_kses_allowed_html('post');
        $allowed['html'] = array('lang' => true);
        $allowed['head'] = array();
        $allowed['meta'] = array(
            'charset' => true,
            'content' => true,
            'http-equiv' => true,
            'name' => true,
        );
        $allowed['title'] = array();
        $allowed['body'] = array('style' => true);
        $allowed['table'] = array(
            'role' => true,
            'cellpadding' => true,
            'cellspacing' => true,
            'border' => true,
            'width' => true,
            'style' => true,
            'align' => true,
            'bgcolor' => true,
        );
        $allowed['tr'] = array('style' => true);
        $allowed['td'] = array(
            'align' => true,
            'valign' => true,
            'style' => true,
            'width' => true,
            'bgcolor' => true,
            'colspan' => true,
        );
        $allowed['div'] = array('style' => true, 'class' => true);
        $allowed['a']['target'] = true;
        $allowed['a']['rel'] = true;
        $allowed['a']['style'] = true;
        $allowed['p']['style'] = true;
        $allowed['h1']['style'] = true;
        $allowed['h2']['style'] = true;
        $allowed['h3']['style'] = true;
        $allowed['span']['style'] = true;
        $allowed['strong']['style'] = true;
        $allowed['br'] = array();

        return $allowed;
    }
}
