<?php
if (!defined('ABSPATH')) {
    exit;
}

$role_id = Woo_OTEC_Moodle_Core::instance()->get_option('student_role_id', 5);
?>
<h3>Paso 3: Permisos del Alumno (Rol)</h3>
<p>ACon quA permisos o nivel de acceso entrarAn tus clientes a la escuela cuando compren un curso? (Normalmente entran como Estudiantes).</p>

<table class="form-table">
    <tr>
        <th><label for="student_role_id">NAmero de Permiso (Rol Moodle)</label></th>
        <td>
            <input type="number" name="student_role_id" id="student_role_id" value="<?php echo esc_attr($role_id); ?>" class="small-text" required min="1">
            <p class="description">En la inmensa mayorAa de las plataformas, el permiso de Estudiante equivale al nAmero <strong>5</strong>. DAjalo asA a menos que tu tecnico te haya indicado un nAmero distinto.</p>
        </td>
    </tr>
</table>
