<?php
if (!defined('ABSPATH')) {
    exit;
}
$moodle_url = Woo_OTEC_Moodle_Core::instance()->get_option('moodle_url', '');
$moodle_token = Woo_OTEC_Moodle_Core::instance()->get_option('moodle_token', '');
?>
<h3>Paso 1: Vincular tu Escuela</h3>
<p>Dile al sistema dAnde esta ubicada tu plataforma de cursos (Moodle) y entrega la "Llave Maestra" para que se puedan comunicar en privado de manera segura.</p>

<table class="form-table">
    <tr>
        <th><label for="moodle_url">Enlace (URL) de tu Escuela</label></th>
        <td>
            <input type="url" name="moodle_url" id="moodle_url" value="<?php echo esc_attr($moodle_url); ?>" class="regular-text" required placeholder="https://mi-escuela.com">
            <p class="description">El enlace exacto con el que tus alumnos entran a tomar sus cursos. CApialo de tu navegador asegurando que empiece con <strong>https://</strong></p>
        </td>
    </tr>
    <tr>
        <th><label for="moodle_token">Llave Maestra (Token de conexion)</label></th>
        <td>
            <input type="password" name="moodle_token" id="moodle_token" value="<?php echo esc_attr($moodle_token); ?>" class="regular-text" required>
            <p class="description">Es un cAdigo secreto largo que se genera dentro de tu plataforma Moodle, en la secciAn de "Servicios Externos". Permite que esta tienda pueda crear alumnos y matricularlos por ti automAticamente tras cada compra.</p>
        </td>
    </tr>
</table>
