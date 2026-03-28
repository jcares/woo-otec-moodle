<?php
if (!defined('ABSPATH')) {
    exit;
}
$moodle_url = Woo_OTEC_Moodle_Core::instance()->get_option('moodle_url', '');
$moodle_token = Woo_OTEC_Moodle_Core::instance()->get_option('moodle_token', '');
?>
<h3><?php echo esc_html__('Step 1: Connect your school', 'woo-otec-moodle'); ?></h3>
<p><?php echo esc_html__('Tell the system where your Moodle course platform is located and provide the access token so both systems can communicate securely.', 'woo-otec-moodle'); ?></p>

<table class="form-table">
    <tr>
        <th><label for="moodle_url"><?php echo esc_html__('School URL', 'woo-otec-moodle'); ?></label></th>
        <td>
            <input type="url" name="moodle_url" id="moodle_url" value="<?php echo esc_attr($moodle_url); ?>" class="regular-text" required placeholder="https://your-school.com">
            <p class="description"><?php echo wp_kses_post(__('Use the exact URL your students use to access their courses. Copy it from your browser and make sure it starts with <strong>https://</strong>.', 'woo-otec-moodle')); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="moodle_token"><?php echo esc_html__('Connection token', 'woo-otec-moodle'); ?></label></th>
        <td>
            <input type="password" name="moodle_token" id="moodle_token" value="<?php echo esc_attr($moodle_token); ?>" class="regular-text" required>
            <p class="description"><?php echo esc_html__('This is a long secret token generated inside Moodle, usually in the External Services section. It allows the store to create students and enroll them automatically after each purchase.', 'woo-otec-moodle'); ?></p>
        </td>
    </tr>
</table>
