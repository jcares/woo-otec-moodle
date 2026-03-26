<?php
if (!defined('ABSPATH')) {
    exit;
}

$price = Woo_OTEC_Moodle_Core::instance()->get_option('default_price', '0');
$instructor = Woo_OTEC_Moodle_Core::instance()->get_option('default_instructor', 'No asignado');
?>
<h3>Paso 4: Ajustes Base para tus Cursos</h3>
<p>Para ahorrarte tiempo, cada vez que importes cursos desde la escuela hacia tu tienda web, se rellenarán automáticamente con esta información básica si es que vienen vacíos.</p>

<table class="form-table">
    <tr>
        <th><label for="default_price">Precio Base Sugerido</label></th>
        <td>
            <input type="text" name="default_price" id="default_price" value="<?php echo esc_attr($price); ?>" class="regular-text">
            <p class="description">Si traes un curso nuevo, el sistema le pondrá este precio temporalmente para no publicar nada "gratis" por error. Ejemplo: <strong>25000</strong> (anótalo sin comas ni puntos).</p>
        </td>
    </tr>
    <tr>
        <th><label for="default_instructor">Profesor Principal (Nombre General)</label></th>
        <td>
            <input type="text" name="default_instructor" id="default_instructor" value="<?php echo esc_attr($instructor); ?>" class="regular-text">
            <p class="description">¿Qué nombre quieres mostrar si el sistema no logra descubrir quién hace el curso? Ejemplo: "Equipo Académico" o "Relatores OTEC".</p>
        </td>
    </tr>
</table>
