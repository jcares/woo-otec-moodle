<?php
if (!defined('ABSPATH')) {
    exit;
}

$price = Woo_OTEC_Moodle_Core::instance()->get_option('default_price', '0');
$instructor = Woo_OTEC_Moodle_Core::instance()->get_option('default_instructor', __('Not assigned', 'woo-otec-moodle'));
?>
<h3><?php echo esc_html__('Step 4: Base settings for your courses', 'woo-otec-moodle'); ?></h3>
<p><?php echo esc_html__('To save time, every imported course will use these base values whenever Moodle does not provide them.', 'woo-otec-moodle'); ?></p>

<table class="form-table">
    <tr>
        <th><label for="default_price"><?php echo esc_html__('Suggested base price', 'woo-otec-moodle'); ?></label></th>
        <td>
            <input type="text" name="default_price" id="default_price" value="<?php echo esc_attr($price); ?>" class="regular-text">
            <p class="description"><?php echo wp_kses_post(__('If you import a new course, the system will use this temporary price to avoid publishing something as free by mistake. Example: <strong>25000</strong> without commas or dots.', 'woo-otec-moodle')); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="default_instructor"><?php echo esc_html__('Main instructor name', 'woo-otec-moodle'); ?></label></th>
        <td>
            <input type="text" name="default_instructor" id="default_instructor" value="<?php echo esc_attr($instructor); ?>" class="regular-text">
            <p class="description"><?php echo esc_html__('This name will be used if the system cannot identify the course instructor. Example: Academic Team or OTEC Trainers.', 'woo-otec-moodle'); ?></p>
        </td>
    </tr>
    <tr>
        <th><label><?php echo esc_html__('Default cover image', 'woo-otec-moodle'); ?></label></th>
        <td>
            <?php 
            $default_image_id = (int) Woo_OTEC_Moodle_Core::instance()->get_option('default_image_id', 0);
            $image_url = $default_image_id > 0 ? wp_get_attachment_image_url($default_image_id, 'medium') : '';
            ?>
            <div class="woo-otec-image-preview-container" style="margin-bottom: 10px;">
                <img id="woo_otec_default_image_preview" src="<?php echo esc_url($image_url); ?>" style="max-width: 250px; height: auto; display: <?php echo $image_url ? 'block' : 'none'; ?>; border: 1px solid #ddd; padding: 4px; border-radius: 4px;">
            </div>
            
            <input type="hidden" name="default_image_id" id="default_image_id" value="<?php echo esc_attr($default_image_id); ?>">
            
            <button type="button" class="button button-secondary" id="woo_otec_select_image_btn"><?php echo esc_html__('Select image', 'woo-otec-moodle'); ?></button>
            <button type="button" class="button button-link-delete" id="woo_otec_remove_image_btn" style="display: <?php echo $image_url ? 'inline-block' : 'none'; ?>; color: #a00;"><?php echo esc_html__('Remove image', 'woo-otec-moodle'); ?></button>
            
            <p class="description"><?php echo esc_html__('If a Moodle course does not include a cover image, this image will be assigned automatically in WooCommerce.', 'woo-otec-moodle'); ?></p>
        </td>
    </tr>
</table>

<script>
jQuery(document).ready(function($){
    var mediaUploader;
    
    $('#woo_otec_select_image_btn').on('click', function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: '<?php echo esc_js(__('Select default image', 'woo-otec-moodle')); ?>',
            button: { text: '<?php echo esc_js(__('Use this image', 'woo-otec-moodle')); ?>' },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#default_image_id').val(attachment.id);
            $('#woo_otec_default_image_preview').attr('src', attachment.url).show();
            $('#woo_otec_remove_image_btn').show();
        });
        
        mediaUploader.open();
    });
    
    $('#woo_otec_remove_image_btn').on('click', function(e) {
        e.preventDefault();
        $('#default_image_id').val('');
        $('#woo_otec_default_image_preview').attr('src', '').hide();
        $(this).hide();
    });
});
</script>
