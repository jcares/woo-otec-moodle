<?php
if (!defined('ABSPATH')) {
    exit;
}

$api = Woo_OTEC_Moodle_API::instance();
$connection_ok = $api->test_connection();
?>
<h3>Paso 2: Comprobar Conexion</h3>
<p>Vamos a "tocar la puerta" de tu escuela para asegurarnos de que el enlace y la llave secreta funcionan correctamente.</p>

<div class="pcc-wizard-test-box <?php echo $connection_ok ? 'success' : 'error'; ?>">
    <?php if ($connection_ok): ?>
        <span class="dashicons dashicons-yes-alt"></span>
        <h4>AConexion Exitosa!</h4>
        <p>Excelente, tu tienda y tu escuela virtual ya estan comunicadas. Podemos continuar.</p>
    <?php else: ?>
        <span class="dashicons dashicons-warning"></span>
        <h4>Fallo en la Conexion</h4>
        <p>No pudimos entrar a la escuela. Por favor, <strong>vuelve al paso anterior</strong> y asegArate de haber copiado el enlace y la llave exactamente como te los entregaron (sin espacios al final).</p>
    <?php endif; ?>
</div>

<?php if (!$connection_ok): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var btnNext = document.querySelector('.pcc-btn-next');
            if(btnNext) btnNext.style.display = 'none'; // Impide avanzar si falla
        });
    </script>
<?php endif; ?>
