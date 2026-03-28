<?php
if (!defined('ABSPATH')) {
    exit;
}
$step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
?>
<div class="wrap pcc-wizard-wrap">
    <div class="pcc-wizard-container">
        <!-- Header -->
        <div class="pcc-wizard-header">
            <div class="pcc-wizard-logo">
                <h2>Asistente <span>Moodle</span></h2>
            </div>
            <div class="pcc-wizard-stepper">
                <ul>
                    <li class="<?php echo $step === 1 ? 'active' : ($step > 1 ? 'done' : ''); ?>">1. Conexion</li>
                    <li class="<?php echo $step === 2 ? 'active' : ($step > 2 ? 'done' : ''); ?>">2. ValidaciAn</li>
                    <li class="<?php echo $step === 3 ? 'active' : ($step > 3 ? 'done' : ''); ?>">3. Permisos</li>
                    <li class="<?php echo $step === 4 ? 'active' : ($step > 4 ? 'done' : ''); ?>">4. Ajustes Base</li>
                    <li class="<?php echo $step === 5 ? 'active' : ''; ?>">5. AFinal!</li>
                </ul>
            </div>
        </div>

        <!-- Content -->
        <div class="pcc-wizard-content">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="woo_otec_moodle_asistente_save">
                <input type="hidden" name="step" value="<?php echo esc_attr($step); ?>">
                <?php wp_nonce_field('woo_otec_moodle_asistente_save', 'wizard_nonce'); ?>

                <div class="pcc-wizard-step-body">
                    <?php
                    $archivos = array(
                        1 => '1-conexion.php',
                        2 => '2-validacion.php',
                        3 => '3-roles.php',
                        4 => '4-parametros.php',
                        5 => '5-finalizar.php'
                    );
                    $step_file = WOO_OTEC_MOODLE_PATH . 'admin/asistente/views/' . ($archivos[$step] ?? '1-conexion.php');
                    if (file_exists($step_file)) {
                        include $step_file;
                    } else {
                        echo '<p>Paso no encontrado.</p>';
                    }
                    ?>
                </div>

                <div class="pcc-wizard-footer">
                    <?php if ($step > 1 && $step < 5): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=woo-otec-asistente&step=' . ($step - 1))); ?>" class="button button-secondary pcc-btn-back">Volver AtrAs</a>
                    <?php endif; ?>

                    <?php if ($step < 5): ?>
                        <button type="submit" class="button button-primary pcc-btn-next">Siguiente Paso</button>
                    <?php else: ?>
                        <button type="submit" class="button button-primary pcc-btn-finish">Entrar al Panel Principal</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
