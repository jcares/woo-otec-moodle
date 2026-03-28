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
                <h2><?php echo esc_html__('Moodle', 'woo-otec-moodle'); ?> <span><?php echo esc_html__('Wizard', 'woo-otec-moodle'); ?></span></h2>
            </div>
            <div class="pcc-wizard-stepper">
                <ul>
                    <li class="<?php echo $step === 1 ? 'active' : ($step > 1 ? 'done' : ''); ?>"><?php echo esc_html__('1. Connection', 'woo-otec-moodle'); ?></li>
                    <li class="<?php echo $step === 2 ? 'active' : ($step > 2 ? 'done' : ''); ?>"><?php echo esc_html__('2. Validation', 'woo-otec-moodle'); ?></li>
                    <li class="<?php echo $step === 3 ? 'active' : ($step > 3 ? 'done' : ''); ?>"><?php echo esc_html__('3. Permissions', 'woo-otec-moodle'); ?></li>
                    <li class="<?php echo $step === 4 ? 'active' : ($step > 4 ? 'done' : ''); ?>"><?php echo esc_html__('4. Base settings', 'woo-otec-moodle'); ?></li>
                    <li class="<?php echo $step === 5 ? 'active' : ''; ?>"><?php echo esc_html__('5. Finish', 'woo-otec-moodle'); ?></li>
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
                        echo '<p>' . esc_html__('Step not found.', 'woo-otec-moodle') . '</p>';
                    }
                    ?>
                </div>

                <div class="pcc-wizard-footer">
                    <?php if ($step > 1 && $step < 5): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=woo-otec-asistente&step=' . ($step - 1))); ?>" class="button button-secondary pcc-btn-back"><?php echo esc_html__('Back', 'woo-otec-moodle'); ?></a>
                    <?php endif; ?>

                    <?php if ($step < 5): ?>
                        <button type="submit" class="button button-primary pcc-btn-next"><?php echo esc_html__('Next step', 'woo-otec-moodle'); ?></button>
                    <?php else: ?>
                        <button type="submit" class="button button-primary pcc-btn-finish"><?php echo esc_html__('Go to main dashboard', 'woo-otec-moodle'); ?></button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
