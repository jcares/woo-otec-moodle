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
                    <tr>
                        <td style="padding:20px 40px;background-color:#f8fafc;border-top:1px solid #e5e7eb;">
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">Este correo fue enviado por {{sitio}}. Como mejora futura, este flujo migrara a enlaces de activacion con expiracion.</p>
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
    'default_instructor'      => 'Cipres Alto',
    'fallback_description'    => 'Programas disenados para personas reales, en contextos reales.',
    'default_image_id'        => 0,
    'sso_enabled'             => 'yes',
    'sso_base_url'            => '',
    'github_repo'             => 'jcares/PCC-WooOTEC-Chile',
    'github_release_url'      => 'https://github.com/jcares/PCC-WooOTEC-Chile/blob/main/release.json',
    'auto_update'             => 'no',
    'last_sync'               => array(),
    'redirect_after_purchase' => 'no',
    'debug_enabled'           => 'no',
    'email_enabled'           => 'yes',
    'email_from_address'      => '',
    'email_from_name'         => 'Plataforma de Cursos',
    'email_subject'           => 'Acceso a tus cursos en {{sitio}}',
    'email_template'          => $email_template,
    'email_test_recipient'    => '',
    'retry_limit'             => 3,
    'template_style'          => 'classic',
    'template_fields'         => array(),
    'template_reference'      => 0,
);
