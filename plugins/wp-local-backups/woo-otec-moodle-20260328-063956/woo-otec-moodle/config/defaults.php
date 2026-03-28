<?php

if (!defined('ABSPATH')) {
    exit;
}

$email_template = <<<'HTML'
<!doctype html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{sitio}}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f4f7fb;margin:0;padding:24px 0;width:100%;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px;max-width:640px;background-color:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background-color:#0f172a;padding:32px 40px;text-align:center;">
                            <p style="margin:0 0 8px;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#93c5fd;">Bienvenido a {{sitio}}</p>
                            <h1 style="margin:0;font-size:28px;line-height:1.2;color:#ffffff;">Tus accesos ya estan listos</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 40px 12px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hola {{nombre}},</p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Tu compra fue confirmada correctamente. Aqui tienes los datos para ingresar a tu plataforma.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 40px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;background-color:#f8fafc;">
                                <tr>
                                    <td style="padding:18px 20px;border-bottom:1px solid #e5e7eb;">
                                        <p style="margin:0 0 4px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;">Usuario</p>
                                        <p style="margin:0;font-size:15px;line-height:1.5;color:#111827;">{{email}}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 20px;border-bottom:1px solid #e5e7eb;">
                                        <p style="margin:0 0 4px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;">Contrasena</p>
                                        <p style="margin:0;font-size:15px;line-height:1.5;color:#111827;">{{password}}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 4px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;">Cursos</p>
                                        <div style="font-size:15px;line-height:1.7;color:#111827;">{{cursos}}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 40px 12px;text-align:center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                                <tr>
                                    <td align="center" bgcolor="#0d6efd" style="border-radius:999px;">
                                        <a href="{{url_acceso}}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:bold;line-height:1.2;color:#ffffff;text-decoration:none;">Acceder a mis cursos</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 40px 36px;">
                            <p style="margin:0;font-size:14px;line-height:1.7;color:#475569;">Si el boton no funciona, copia este enlace en tu navegador:<br><a href="{{url_acceso}}" style="color:#0d6efd;text-decoration:none;word-break:break-all;">{{url_acceso}}</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

return array(
    'moodle_url'              => '',
    'moodle_token'            => '',
    'student_role_id'         => 5,
    'default_price'           => '49000',
    'default_instructor'      => 'No asignado',
    'fallback_description'    => 'Curso sincronico y asincronico disenado para potenciar tus habilidades.',
    'default_image_id'        => 0,
    'sso_enabled'             => 'yes',
    'sso_base_url'            => '',
    'github_repo'             => 'jcares/PCC-WooOTEC-Chile',
    'github_release_url'      => 'https://github.com/jcares/PCC-WooOTEC-Chile/blob/main/release.json',
    'auto_update'             => 'no',
    'last_sync'               => array(),
    'redirect_after_purchase' => 'yes',
    'debug_enabled'           => 'no',
    'email_enabled'           => 'yes',
    'email_from_address'      => '',
    'email_from_name'         => 'Campus Virtual',
    'email_subject'           => 'Bienvenido! Acceso a tus modulos en {{sitio}}',
    'email_builder_enabled'   => 'yes',
    'email_builder_heading'   => 'Tus accesos ya estan listos',
    'email_builder_intro'     => 'Tu compra fue confirmada correctamente. Aqui tienes los datos para ingresar a tu plataforma.',
    'email_builder_button_text' => 'Acceder a mis cursos',
    'email_builder_footer'    => 'Si necesitas ayuda, responde este correo y te apoyaremos.',
    'email_logo_id'           => 0,
    'email_color_primary'     => '#0f3d5e',
    'email_color_accent'      => '#1f9d6f',
    'email_color_bg'          => '#f3f8fc',
    'email_template'          => $email_template,
    'email_test_recipient'    => '',
    'retry_limit'             => 3,
    'pcc_color_primary'       => '#0f3d5e',
    'pcc_color_secondary'     => '#1a628f',
    'pcc_color_text'          => '#4f6678',
    'pcc_color_accent'        => '#1f9d6f',
    'appearance_profile'      => 'product',
    'single_description_heading' => 'Descripcion del curso',
    'single_button_text'      => 'Comprar curso',
    'shop_intro_title'        => 'Explora nuestra oferta de cursos',
    'shop_intro_text'         => 'Descubre nuestros cursos disponibles y selecciona el que mejor se ajusta a tu objetivo.',
    'shop_button_text'        => 'Ver curso',
    'shop_color_bg'           => '#f8fbff',
    'shop_color_title'        => '#21405a',
    'shop_color_text'         => '#2b4b63',
    'shop_color_button'       => '#0f3d5e',
    'cart_intro_title'        => 'Tu carrito de capacitacion',
    'cart_intro_text'         => 'Revisa tus cursos antes de finalizar el pago.',
    'cart_color_bg'           => '#f5fbf8',
    'cart_color_title'        => '#1d5a41',
    'cart_color_text'         => '#355846',
    'cart_color_button'       => '#1f9d6f',
    'checkout_intro_title'    => 'Ultimo paso para activar tus cursos',
    'checkout_intro_text'     => 'Completa tus datos para activar el acceso inmediato a tus cursos.',
    'checkout_button_text'    => 'Finalizar compra',
    'checkout_color_bg'       => '#fff8f1',
    'checkout_color_title'    => '#7b4b12',
    'checkout_color_text'     => '#6f5a40',
    'checkout_color_button'   => '#d9822b',
    'template_style'          => 'pccurico',
    'template_fields'         => array('_start_date', '_end_date', '_instructor', '_duration', '_modality', '_course_format', '_sence_code', '_total_hours'),
    'template_reference'      => 0,
);
