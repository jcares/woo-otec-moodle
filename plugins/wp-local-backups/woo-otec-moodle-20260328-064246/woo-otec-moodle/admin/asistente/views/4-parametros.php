<?php
if (!defined('ABSPATH')) {
    exit;
}

$price = Woo_OTEC_Moodle_Core::instance()->get_option('default_price', '0');
$instructor = Woo_OTEC_Moodle_Core::instance()->get_option('default_instructor', 'No asignado');
?>
<h3>Paso 4: Ajustes Base para tus Cursos</h3>
<p>Para ahorrarte tiempo, cada vez que importes cursos desde la escuela hacia tu tienda web, se rellenarAn automAticamente con esta informaciAn bAsica si es que vienen vacAos.</p>

<table class="form-table">
    <tr>
        <th><label for="default_price">Precio Base Sugerido</label></th>
        <td>
            <input type="text" name="default_price" id="default_price" value="<?php echo esc_attr($price); ?>" class="regular-text">
            <p class="description">Si traes un curso nuevo, el sistema le pondrA este precio temporalmente para no publicar nada "gratis" por error. Ejemplo: <strong>25000</strong> (anAtalo sin comas ni puntos).</p>
        </td>
    </tr>
    <tr>
        <th><label for="default_instructor">Profesor Principal (Nombre General)</label></th>
        <td>
            <input type="text" name="default_instructor" id="default_instructor" value="<?php echo esc_attr($instructor); ?>" class="regular-text">
            <p class="description">AQuA nombre quieres mostrar si el sistema no logra descubrir quiAn hace el curso? Ejemplo: "Equipo AcadAmico" o "Relatores OTEC".</p>
        </td>
    </tr>
    <tr>
        <th><label>Imagen de Portada por Defecto</label></th>
        <td>
            <?php 
            $default_image_id = (int) Woo_OTEC_Moodle_Core::instance()->get_option('default_image_id', 0);
            $image_url = $default_image_id > 0 ? wp_get_attachment_image_url($default_image_id, 'medium') : '';
            ?>
            <div class="woo-otec-image-preview-container" style="margin-bottom: 10px;">
                <img id="woo_otec_default_image_preview" src="<?php echo esc_url($image_url); ?>" style="max-width: 250px; height: auto; display: <?php echo $image_url ? 'block' : 'none'; ?>; border: 1px solid #ddd; padding: 4px; border-radius: 4px;">
            </div>
            
            <input type="hidden" name="default_image_id" id="default_image_id" value="<?php echo esc_attr($default_image_id); ?>">
            
            <button type="button" class="button button-secondary" id="woo_otec_select_image_btn">Seleccionar Imagen</button>
            <button type="button" class="button button-link-delete" id="woo_otec_remove_image_btn" style="display: <?php echo $image_url ? 'inline-block' : 'none'; ?>; color: #a00;">Quitar Imagen</button>
            
            <p class="description">Si importas un curso desde Moodle y no tiene imagen de portada, se le asignarA esta imagen automAticamente en WooCommerce.</p>
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
            title: 'Seleccionar Imagen por Defecto',
            button: { text: 'Usar esta imagen' },
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

