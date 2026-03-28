<?php
if (!defined('ABSPATH')) {
    exit;
}

$role_id = Woo_OTEC_Moodle_Core::instance()->get_option('student_role_id', 5);
?>
<h3><?php echo esc_html__('Step 3: Student permissions (role)', 'woo-otec-moodle'); ?></h3>
<p><?php echo esc_html__('Choose the access role your customers will use when entering the school after buying a course. Most Moodle sites use the Student role.', 'woo-otec-moodle'); ?></p>

<table class="form-table">
    <tr>
        <th><label for="student_role_id"><?php echo esc_html__('Role ID in Moodle', 'woo-otec-moodle'); ?></label></th>
        <td>
            <input type="number" name="student_role_id" id="student_role_id" value="<?php echo esc_attr($role_id); ?>" class="small-text" required min="1">
            <p class="description"><?php echo wp_kses_post(__('In most Moodle platforms, the Student role is <strong>5</strong>. Leave this value unless your technical team told you to use a different role ID.', 'woo-otec-moodle')); ?></p>
        </td>
    </tr>
</table>
