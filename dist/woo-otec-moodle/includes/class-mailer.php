<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_Mailer {
    private static ?Woo_OTEC_Moodle_Mailer $instance = null;

    public static function instance(): Woo_OTEC_Moodle_Mailer {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('wp_mail_failed', array($this, 'log_mail_failure'));
    }

    public function log_mail_failure(WP_Error $error): void {
        Woo_OTEC_Moodle_Logger::error('Fallo interno de wp_mail detectado por WordPress', array(
            'message' => $error->get_error_message(),
            'code'    => $error->get_error_code(),
            'data'    => $error->get_error_data()
        ));
    }

    public function render_subject(array $data): string {
        $subject = (string) Woo_OTEC_Moodle_Core::instance()->get_option('email_subject', __('Access your courses on {{sitio}}', 'woo-otec-moodle'));
        return wp_strip_all_tags($this->replace_template_variables($subject, $data));
    }

    public function render_template(array $data, bool $preview = false): string {
        $core = Woo_OTEC_Moodle_Core::instance();
        $custom_template = (string) $core->get_option('email_template', '');
        
        // Prioridad absoluta a la plantilla configurada por el usuario (v2.1.26)
        if ($custom_template !== '') {
            $use_builder = false;
        } else {
            $use_builder = $core->get_option('email_builder_enabled', 'yes') === 'yes';
        }

        if ($use_builder) {
            $html = $this->build_friendly_template($data);
        } else {
            $template = $custom_template;
            $template = html_entity_decode($template, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $html = $this->replace_template_variables($template, $data);
        }

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

        // Usamos filtros locales para que WordPress maneje la codificación (acentos, etc.)
        // v2.1.30 - Método de alta compatibilidad
        add_filter('wp_mail_from', array($this, 'filter_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'filter_mail_from_name'));
        add_filter('wp_mail_content_type', array($this, 'filter_content_type'));

        try {
            $sent = wp_mail(
                $recipient,
                $subject,
                $message
            );
            
            return $sent;
        } catch (Exception $e) {
            Woo_OTEC_Moodle_Logger::error('Excepción al enviar correo', array('error' => $e->getMessage()));
            return false;
        } finally {
            // Limpiamos los filtros para no afectar a otros plugins
            remove_filter('wp_mail_from', array($this, 'filter_mail_from'));
            remove_filter('wp_mail_from_name', array($this, 'filter_mail_from_name'));
            remove_filter('wp_mail_content_type', array($this, 'filter_content_type'));
        }
    }

    public function filter_content_type(): string {
        return 'text/html';
    }

    public function filter_mail_from(string $email): string {
        $configured = (string) Woo_OTEC_Moodle_Core::instance()->get_option('email_from_address', '');
        return is_email($configured) ? $configured : $email;
    }

    public function filter_mail_from_name(string $name): string {
        $configured = (string) Woo_OTEC_Moodle_Core::instance()->get_option('email_from_name', '');
        return $configured !== '' ? $configured : $name;
    }

    private function build_friendly_template(array $data): string {
        $core = Woo_OTEC_Moodle_Core::instance();

        $site = (string) ($data['sitio'] ?? wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $hello_name = trim((string) ($data['nombre'] ?? __('Student', 'woo-otec-moodle')));
        $email = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $courses = (string) ($data['cursos'] ?? '');
        $access_url = esc_url((string) ($data['url_acceso'] ?? ''));

        $heading = sanitize_text_field((string) $core->get_option('email_builder_heading', __('Your access details are ready', 'woo-otec-moodle')));
        $intro = sanitize_textarea_field((string) $core->get_option('email_builder_intro', __('Your purchase has been confirmed successfully. Here are your access details for the platform.', 'woo-otec-moodle')));
        $button_text = sanitize_text_field((string) $core->get_option('email_builder_button_text', __('Access my courses', 'woo-otec-moodle')));
        $footer = sanitize_textarea_field((string) $core->get_option('email_builder_footer', __('If you need help, reply to this email and we will gladly assist you.', 'woo-otec-moodle')));

        $primary = sanitize_hex_color((string) $core->get_option('email_color_primary', '#0f3d5e')) ?: '#0f3d5e';
        $accent = sanitize_hex_color((string) $core->get_option('email_color_accent', '#1f9d6f')) ?: '#1f9d6f';
        $bg = sanitize_hex_color((string) $core->get_option('email_color_bg', '#f3f8fc')) ?: '#f3f8fc';

        $logo_url = $this->resolve_email_logo_url();
        $logo_html = '';
        if ($logo_url !== '') {
            $logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site) . '" style="max-height:54px;width:auto;display:block;margin:0 auto 10px;">';
        }

        $template = '
<!doctype html>
<html lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{sitio}}</title>
</head>
<body style="margin:0;padding:0;background:' . $bg . ';font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $bg . ';padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:640px;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;">
          <tr>
            <td style="background:' . $primary . ';padding:28px 34px;text-align:center;color:#ffffff;">
              ' . $logo_html . '
              <p style="margin:0 0 8px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.9;">' . esc_html__('Welcome to {{sitio}}', 'woo-otec-moodle') . '</p>
              <h1 style="margin:0;font-size:26px;line-height:1.3;color:#ffffff;">' . esc_html($heading) . '</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:26px 34px 10px;">
              <p style="margin:0 0 12px;font-size:16px;">' . esc_html__('Hello {{nombre}},', 'woo-otec-moodle') . '</p>
              <p style="margin:0 0 12px;font-size:15px;line-height:1.6;">' . nl2br(esc_html($intro)) . '</p>
            </td>
          </tr>
          <tr>
            <td style="padding:0 34px 12px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e4ebf2;border-radius:12px;background:#f8fbff;">
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e4ebf2;"><strong>' . esc_html__('User:', 'woo-otec-moodle') . '</strong><br>{{email}}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e4ebf2;"><strong>' . esc_html__('Password:', 'woo-otec-moodle') . '</strong><br>{{password}}</td></tr>
                <tr><td style="padding:14px 16px;"><strong>' . esc_html__('Courses:', 'woo-otec-moodle') . '</strong><br>{{cursos}}</td></tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 34px 8px;text-align:center;">
              <a href="{{url_acceso}}" style="display:inline-block;padding:12px 24px;border-radius:999px;background:' . $accent . ';color:#ffffff;text-decoration:none;font-weight:bold;">' . esc_html($button_text) . '</a>
            </td>
          </tr>
          <tr>
            <td style="padding:10px 34px 24px;">
              <p style="margin:0;font-size:13px;line-height:1.7;color:#44576a;">' . esc_html__('If the button does not work, copy this link into your browser:', 'woo-otec-moodle') . '<br><a href="{{url_acceso}}" style="color:' . $primary . ';text-decoration:none;word-break:break-all;">{{url_acceso}}</a></p>
            </td>
          </tr>
          <tr>
            <td style="padding:14px 34px;background:#f7fafc;border-top:1px solid #e4ebf2;">
              <p style="margin:0;font-size:13px;line-height:1.6;color:#5d7285;">' . nl2br(esc_html($footer)) . '</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

        return $this->replace_template_variables($template, array(
            'sitio' => $site,
            'nombre' => $hello_name,
            'email' => $email,
            'password' => $password,
            'cursos' => $courses,
            'url_acceso' => $access_url,
        ));
    }

    private function resolve_email_logo_url(): string {
        $core = Woo_OTEC_Moodle_Core::instance();
        $logo_id = (int) $core->get_option('email_logo_id', 0);

        if ($logo_id > 0) {
            $custom_logo = wp_get_attachment_image_url($logo_id, 'full');
            if (is_string($custom_logo) && $custom_logo !== '') {
                return $custom_logo;
            }
        }

        $theme_logo_id = (int) get_theme_mod('custom_logo');
        if ($theme_logo_id > 0) {
            $theme_logo = wp_get_attachment_image_url($theme_logo_id, 'full');
            if (is_string($theme_logo) && $theme_logo !== '') {
                return $theme_logo;
            }
        }

        return '';
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
        $allowed['img'] = array('src' => true, 'alt' => true, 'style' => true);
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
