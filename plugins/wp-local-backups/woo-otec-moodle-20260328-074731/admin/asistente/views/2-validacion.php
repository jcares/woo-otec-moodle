<?php
if (!defined('ABSPATH')) {
    exit;
}

$api = Woo_OTEC_Moodle_API::instance();
$connection_ok = $api->test_connection();
?>
<h3><?php echo esc_html__('Step 2: Validate connection', 'woo-otec-moodle'); ?></h3>
<p><?php echo esc_html__('We will test your school connection to confirm that the URL and token are working correctly.', 'woo-otec-moodle'); ?></p>

<div class="pcc-wizard-test-box <?php echo $connection_ok ? 'success' : 'error'; ?>">
    <?php if ($connection_ok): ?>
        <span class="dashicons dashicons-yes-alt"></span>
        <h4><?php echo esc_html__('Connection successful!', 'woo-otec-moodle'); ?></h4>
        <p><?php echo esc_html__('Great, your store and virtual school are already connected. You can continue.', 'woo-otec-moodle'); ?></p>
    <?php else: ?>
        <span class="dashicons dashicons-warning"></span>
        <h4><?php echo esc_html__('Connection failed', 'woo-otec-moodle'); ?></h4>
        <p><?php echo wp_kses_post(__('We could not reach the school. Please <strong>go back to the previous step</strong> and make sure the URL and token were copied exactly as provided, without trailing spaces.', 'woo-otec-moodle')); ?></p>
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
