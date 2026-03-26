<?php
if (!defined('ABSPATH')) {
    exit;
}

$role_id = PCC_WooOTEC_Pro_Core::instance()->get_option('student_role_id', 5);
?>
<h3>Paso 3: Permisos del Alumno (Rol)</h3>
<p>¿Con qué permisos o nivel de acceso entrarán tus clientes a la escuela cuando compren un curso? (Normalmente entran como Estudiantes).</p>

<table class="form-table">
    <tr>
        <th><label for="student_role_id">Número de Permiso (Rol Moodle)</label></th>
        <td>
            <input type="number" name="student_role_id" id="student_role_id" value="<?php echo esc_attr($role_id); ?>" class="small-text" required min="1">
            <p class="description">En la inmensa mayoría de las plataformas, el permiso de Estudiante equivale al número <strong>5</strong>. Déjalo así a menos que tu técnico te haya indicado un número distinto.</p>
        </td>
    </tr>
</table>
