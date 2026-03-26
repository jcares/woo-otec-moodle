<?php

/**
 * @var Woo_OTEC_Moodle_Core $core
 * @var array $last_sync
 * @var bool $connection_ok
 * @var array $sync_log
 * @var array $error_log
 * @var bool $update_available
 * @var string $active_tab
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variables extraídas por render_view(); aseguramos inicializaciones para análisis estático.
$status = $status ?? '';

$default_image_id = (int) $core->get_option('default_image_id', 0);
$default_image_url = $default_image_id > 0 ? wp_get_attachment_image_url($default_image_id, 'medium') : '';
$brand_logo_url = WOO_OTEC_MOODLE_URL . 'assets/images/logo-pccurico.png';
$release_page_url = 'https://github.com/jcares/PCC-WooOTEC-Chile/releases';
$release_json_url = 'https://github.com/jcares/PCC-WooOTEC-Chile/blob/main/release.json';
$last_sync_label = !empty($last_sync['timestamp']) ? (string) $last_sync['timestamp'] : 'Sin ejecuciones';
$sync_status = !empty($last_sync['status']) ? (string) $last_sync['status'] : 'idle';
$email_enabled = $core->get_option('email_enabled', 'yes') === 'yes';
$sso_enabled = $core->get_option('sso_enabled', 'yes') === 'yes';
$email_from_address = (string) $core->get_option('email_from_address', '');
$fallback_from_address = Woo_OTEC_Moodle_Mailer::instance()->filter_mail_from(get_option('admin_email', ''));
$tabs = array(
    'general'  => 'Conectar Moodle',
    'sync'     => 'Sincronización',
    'sso'      => 'Acceso automático (SSO)',
    'templates'=> 'Metadatos Moodle',
    'emails'   => 'Configurar Emails',
    'logs'     => 'Registro de errores',
);
?>
<div class="wrap pcc-admin-wrap">
    <div class="pcc-brand-bar">
        <div class="pcc-brand-bar__main">
            <span class="pcc-brand-bar__logo-wrap">
                <img class="pcc-brand-bar__logo" src="<?php echo esc_url($brand_logo_url); ?>" alt="PCCurico">
            </span>
            <div>
                <h1>SINCRONIZADOR WooCommerce con Moodle</h1>
            </div>
        </div>
        <div class="pcc-brand-bar__meta">
            <span>www.pccurico.cl</span>
            <p>desarrollado por JCares</p>
        </div>
    </div>

    <div style="margin-top: 15px; margin-bottom: 15px; text-align: right;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=woo-otec-asistente')); ?>" class="button button-primary button-large" style="background:#2271b1; border-color:#2271b1;">💫 Iniciar Asistente de Configuración</a>
    </div>

    <?php if ($status !== '') : ?>
        <div class="notice notice-<?php echo $status === 'success' ? 'success' : 'warning'; ?> is-dismissible">
            <p>
                <?php
                echo esc_html(
                    $status === 'success'
                        ? 'La sincronización manual finalizó correctamente.'
                        : 'La sincronización manual terminó con incidencias. Revisa los logs del tab Sincronización.'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="woo-otec-sync-form">
        <input type="hidden" name="action" value="woo_otec_moodle_run_sync">
        <?php wp_nonce_field('woo_otec_moodle_run_sync'); ?>
    </form>

    <form method="post" action="options.php" class="pcc-settings-form">
        <?php settings_fields('woo_otec_moodle_settings'); ?>

        <!-- Overview Grid -->
        <div class="pcc-overview-grid">
            <article class="pcc-overview-card">
                <span class="pcc-overview-card__label">Conexión Moodle</span>
                <strong class="pcc-overview-card__value <?php echo $connection_ok ? 'text-success' : 'text-danger'; ?>"><?php echo $connection_ok ? 'Conectada' : 'Sin Conexión'; ?></strong>
                <p>
                    <?php 
                    if ($connection_ok) {
                        echo 'Aula responde correctamente.';
                    } else {
                        $last_error = Woo_OTEC_Moodle_Logger::read_tail(Woo_OTEC_Moodle_Logger::ERROR_LOG, 1);
                        $error_text = !empty($last_error) ? $last_error[0] : '';
                        if (strpos($error_text, 'Could not resolve host') !== false) {
                            echo '<span style="color: #ff5370;">Error de DNS: El servidor no alcanza el dominio Moodle.</span>';
                        } else {
                            echo 'Revisa URL y Token para Conectar.';
                        }
                    }
                    ?>
                </p>
            </article>
            <article class="pcc-overview-card">
                <span class="pcc-overview-card__label">Última sincronización</span>
                <strong class="pcc-overview-card__value"><?php echo esc_html($last_sync_label); ?></strong>
                <p>Estado: <?php echo $sync_status === 'success' ? 'Completada con éxito' : ($sync_status === 'idle' ? 'Pendiente de ejecución' : 'Finalizada con alertas'); ?>.</p>
            </article>
            <article class="pcc-overview-card">
                <span class="pcc-overview-card__label">Remitente E-Mail</span>
                <strong class="pcc-overview-card__value"><?php echo esc_html($email_from_address !== '' ? $email_from_address : $fallback_from_address); ?></strong>
                <p>Aparece al Enviar el Correo.</p>
            </article>
            <article class="pcc-overview-card">
                <span class="pcc-overview-card__label">Versión</span>
                <strong class="pcc-overview-card__value"><?php echo esc_html(WOO_OTEC_MOODLE_VERSION); ?></strong>
                <p><?php echo !empty($update_available) ? 'Hay una nueva versión detectada.' : 'Sin actualizaciones pendientes.'; ?></p>
            </article>
        </div>

        <!-- Main Dashboard Layout -->
        <div class="pcc-dashboard-layout">
            <!-- Sidebar -->
            <aside class="pcc-dashboard-sidebar">
                <div class="pcc-tabs" role="tablist" aria-label="Secciones de configuración">
                    <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                        <button
                            type="button"
                            class="pcc-tab <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>"
                            id="pcc-tab-<?php echo esc_attr($tab_key); ?>"
                            data-tab="<?php echo esc_attr($tab_key); ?>"
                            role="tab"
                            aria-selected="<?php echo $active_tab === $tab_key ? 'true' : 'false'; ?>"
                            aria-controls="pcc-panel-<?php echo esc_attr($tab_key); ?>"
                        >
                            <?php echo esc_html($tab_label); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="pcc-dashboard-main">
                <div class="pcc-card">
                    <!-- General Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'general' ? 'is-active' : ''; ?>" id="pcc-panel-general" data-panel="general" role="tabpanel">
                        <h3>Conexión Moodle</h3>
                        <p class="description">Credenciales para realizar la conexión con Moodle.</p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_moodle_url">URL Moodle</label></th>
                                <td>
                                    <input class="regular-text" type="url" id="woo_otec_moodle_moodle_url" name="woo_otec_moodle_moodle_url" value="<?php echo esc_attr((string) $core->get_option('moodle_url', '')); ?>">
                                    <p class="pcc-field-help">Ejemplo: `https://campus.tudominio.cl`.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_moodle_token">Token Moodle</label></th>
                                <td>
                                    <input class="regular-text" type="password" id="woo_otec_moodle_moodle_token" name="woo_otec_moodle_moodle_token" value="<?php echo esc_attr((string) $core->get_option('moodle_token', '')); ?>" autocomplete="off">
                                    <p class="pcc-field-help">Token del servicio web con permisos para usuarios, cursos, categorías y matrículas.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_student_role_id">Role ID estudiante</label></th>
                                <td>
                                    <input class="small-text" type="number" id="woo_otec_moodle_student_role_id" name="woo_otec_moodle_student_role_id" value="<?php echo esc_attr((string) $core->get_option('student_role_id', 5)); ?>">
                                    <p class="pcc-field-help">Normalmente es `5`, pero depende de tu Moodle.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Imagen por defecto</th>
                                <td>
                                    <input type="hidden" id="woo_otec_moodle_default_image_id" name="woo_otec_moodle_default_image_id" value="<?php echo esc_attr((string) $default_image_id); ?>">
                                    <button type="button" class="button pcc-media-picker" data-target="#woo_otec_moodle_default_image_id" data-preview="#pcc-default-image-preview">Seleccionar imagen</button>
                                    <p class="pcc-field-help">Se usa cuando el curso no trae imagen desde Moodle.</p>
                                    <div class="pcc-image-preview-wrap">
                                        <img id="pcc-default-image-preview" src="<?php echo esc_url($default_image_url); ?>" alt="" class="pcc-image-preview<?php echo $default_image_url === '' ? ' is-hidden' : ''; ?>">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_default_price">Precio default</label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_default_price" name="woo_otec_moodle_default_price" value="<?php echo esc_attr((string) $core->get_option('default_price', '49000')); ?>">
                                    <p class="pcc-field-help">Monto sin símbolos ni separadores, por ejemplo `49000`.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_default_instructor">Instructor default</label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_default_instructor" name="woo_otec_moodle_default_instructor" value="<?php echo esc_attr((string) $core->get_option('default_instructor', 'No asignado')); ?>">
                                    <p class="pcc-field-help">Texto fallback cuando Moodle no informa docente.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_fallback_description">Descripción fallback</label></th>
                                <td>
                                    <textarea class="large-text" rows="4" id="woo_otec_moodle_fallback_description" name="woo_otec_moodle_fallback_description"><?php echo esc_textarea((string) $core->get_option('fallback_description', '')); ?></textarea>
                                    <p class="pcc-field-help">Descripción usada al crear o actualizar productos sin contenido suficiente.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Mantenimiento</th>
                                <td>
                                    <button type="button" class="button button-secondary" id="pcc-generate-zip">Generar ZIP del plugin</button>
                                    <p class="pcc-field-help">Crea un archivo comprimido del plugin para respaldos o instalaciones manuales.</p>
                                    <div id="pcc-zip-result"></div>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <!-- Sync Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'sync' ? 'is-active' : ''; ?>" id="pcc-panel-sync" data-panel="sync" role="tabpanel">
                        <div class="pcc-sync-header">
                            <h2>Sincronización</h2>
                            <div class="pcc-badge is-info">Moodle &rarr; WooCommerce</div>
                        </div>

                        <!-- Ultima Sincronizacion Informativa -->
                        <div class="pcc-sync-result-box">
                            <div class="pcc-sync-result-header">
                                <h3>Resumen de última ejecución</h3>
                                <span class="pcc-badge <?php echo $sync_status === 'success' ? 'is-success' : 'is-warning'; ?>">
                                    <?php echo $sync_status === 'success' ? 'Completada' : 'Error / Incompleta'; ?>
                                </span>
                            </div>
                            <div class="pcc-sync-result-grid">
                                <div class="pcc-sync-result-item">
                                    <span>Fecha y Hora</span>
                                    <strong><?php echo esc_html($last_sync_label); ?></strong>
                                </div>
                                <div class="pcc-sync-result-item success">
                                    <span>Categorías creadas</span>
                                    <strong><?php echo esc_html((string) ($last_sync['categories_created'] ?? 0)); ?></strong>
                                </div>
                                <div class="pcc-sync-result-item success">
                                    <span>Cursos creados</span>
                                    <strong><?php echo esc_html((string) ($last_sync['products_created'] ?? 0)); ?></strong>
                                </div>
                                <div class="pcc-sync-result-item warning">
                                    <span>Cursos actualizados</span>
                                    <strong><?php echo esc_html((string) ($last_sync['products_updated'] ?? 0)); ?></strong>
                                </div>
                            </div>
                            <?php if (!empty($last_sync['message'])) : ?>
                                <div class="pcc-sync-result-message" style="margin-top: 15px; padding: 10px; background: #fffbeb; border-radius: 4px; border-left: 4px solid #ffb64d; font-size: 13px;">
                                    <strong>Mensaje:</strong> <?php echo esc_html((string) $last_sync['message']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h3>Asistente de Sincronización</h3>
                        <div class="pcc-wizard-steps">
                            <div class="pcc-step is-active" data-step="1">
                                <span class="pcc-step-num">1</span>
                                <span class="pcc-step-label">Categorías</span>
                            </div>
                            <div class="pcc-step" data-step="2">
                                <span class="pcc-step-num">2</span>
                                <span class="pcc-step-label">Profesores</span>
                            </div>
                            <div class="pcc-step" data-step="3">
                                <span class="pcc-step-num">3</span>
                                <span class="pcc-step-label">Cursos</span>
                            </div>
                            <div class="pcc-step" data-step="4">
                                <span class="pcc-step-num">4</span>
                                <span class="pcc-step-label">Confirmación</span>
                            </div>
                        </div>

                        <!-- Step 1: Categories -->
                        <div class="pcc-wizard-content is-active" id="pcc-step-1-content">
                            <h3>Paso 1: Selección de Categorías</h3>
                            <p>Obteniendo categorías desde Moodle... Por favor, selecciona las que deseas importar.</p>
                            <div id="pcc-categories-list-container">
                                <div class="pcc-loading-spinner">Cargando categorías...</div>
                            </div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-primary" id="pcc-btn-next-1">Confirmar y continuar</button>
                            </div>
                        </div>

                        <!-- Step 2: Teachers -->
                        <div class="pcc-wizard-content" id="pcc-step-2-content" style="display:none;">
                            <h3>Paso 2: Profesores Detectados</h3>
                            <p>Estos son los profesores asociados a los cursos de las categorías seleccionadas. Puedes verificar sus nombres antes de proceder.</p>
                            <div id="pcc-teachers-list-container">
                                <div class="pcc-loading-spinner">Obteniendo profesores...</div>
                            </div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-secondary" id="pcc-btn-prev-2">Anterior</button>
                                <button type="button" class="button button-primary" id="pcc-btn-next-2">Confirmar profesores</button>
                            </div>
                        </div>

                        <!-- Step 3: Courses -->
                        <div class="pcc-wizard-content" id="pcc-step-3-content" style="display:none;">
                            <h3>Paso 3: Configuración de Cursos</h3>
                            <p>Edita la información de los cursos antes de importarlos.</p>
                            <div id="pcc-courses-table-container" style="overflow-x: auto;"></div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-secondary" id="pcc-btn-prev-3">Anterior</button>
                                <button type="button" class="button button-primary" id="pcc-btn-next-3">Siguiente paso</button>
                            </div>
                        </div>

                        <!-- Step 4: Confirmation -->
                        <div class="pcc-wizard-content" id="pcc-step-4-content" style="display:none;">
                            <h3>Paso 4: Resumen y Confirmación</h3>
                            <div id="pcc-sync-summary-container"></div>
                            <div class="pcc-progress" style="display:none; margin: 20px 0;">
                                <div class="pcc-progress-bar" style="width:0%">0%</div>
                            </div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-secondary" id="pcc-btn-prev-4">Anterior</button>
                                <button type="button" class="button button-primary" id="pcc-btn-execute-sync">Ejecutar sincronización</button>
                            </div>
                        </div>

                        <details class="pcc-log-details" style="margin-top: 20px;">
                            <summary style="cursor: pointer; font-weight: 600; color: var(--pcc-primary);">Ver historial técnico (Logs)</summary>
                            <pre class="pcc-log-view" style="margin-top: 10px; max-height: 200px; overflow-y: auto;"><?php echo esc_html(implode("\n", $sync_log)); ?></pre>
                        </details>
                    </section>

                    <!-- SSO Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'sso' ? 'is-active' : ''; ?>" id="pcc-panel-sso" data-panel="sso" role="tabpanel">
                        <h3>Acceso automático (SSO)</h3>
                        <p class="description">Configuración del inicio de sesión único entre WooCommerce y Moodle.</p>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Estado del SSO</th>
                                <td>
                                    <input type="checkbox" id="woo_otec_moodle_sso_enabled" name="woo_otec_moodle_sso_enabled" value="yes" <?php checked($core->get_option('sso_enabled', 'yes'), 'yes'); ?>>
                                    <label for="woo_otec_moodle_sso_enabled">Activar SSO</label>
                                    <p class="pcc-field-help">Permite acceso directo al aula sin login manual adicional.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_sso_base_url">URL base SSO</label></th>
                                <td>
                                    <input class="regular-text" type="url" id="woo_otec_moodle_sso_base_url" name="woo_otec_moodle_sso_base_url" value="<?php echo esc_attr((string) $core->get_option('sso_base_url', '')); ?>">
                                    <p class="pcc-field-help">Ejemplo: `https://campus.tudominio.cl/auth/userkey/login.php`.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_redirect_after_purchase">Redirigir tras compra</label></th>
                                <td>
                                    <input type="checkbox" id="woo_otec_moodle_redirect_after_purchase" name="woo_otec_moodle_redirect_after_purchase" value="yes" <?php checked($core->get_option('redirect_after_purchase', 'no'), 'yes'); ?>>
                                    <p class="pcc-field-help">Envía al alumno directo al aula virtual al finalizar la compra.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Prueba</th>
                                <td>
                                    <button type="button" class="button button-secondary" id="pcc-test-sso-connection">Probar conexión</button>
                                    <span id="sso-test-result" style="margin-left: 10px;"></span>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <!-- Templates Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'templates' ? 'is-active' : ''; ?>" id="pcc-panel-templates" data-panel="templates" role="tabpanel">
                        <h3>Metadatos y Mapeo</h3>
                        <p class="description">Configura cómo se muestran y vinculan los datos de Moodle.</p>
                        <?php
                        $reference_id = (int) $core->get_option('template_reference', 0);
                        $reference_products = Woo_OTEC_Moodle_Settings::instance()->get_template_reference_products();
                        $selected_fields = (array) $core->get_option('template_fields', array());
                        ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_template_style">Estilo de Plantilla</label></th>
                                <td>
                                    <select id="woo_otec_moodle_template_style" name="woo_otec_moodle_template_style">
                                        <?php $current_style = $core->get_option('template_style', 'classic'); ?>
                                        <option value="classic" <?php selected($current_style, 'classic'); ?>>Classic (WooCommerce Default)</option>
                                        <option value="academy" <?php selected($current_style, 'academy'); ?>>Academy (Moderna/LMS)</option>
                                        <option value="market" <?php selected($current_style, 'market'); ?>>Market (Grid compacta)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_template_reference">Producto de referencia</label></th>
                                <td>
                                    <select id="woo_otec_moodle_template_reference" name="woo_otec_moodle_template_reference">
                                        <option value="">Selecciona un curso sincronizado</option>
                                        <?php foreach ($reference_products as $product) : ?>
                                            <option value="<?php echo esc_attr((string) $product->get_id()); ?>" <?php selected($reference_id, $product->get_id()); ?>>
                                                <?php echo esc_html($product->get_name()); ?> (#<?php echo esc_html((string) $product->get_id()); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <hr>
                        <h3>Mapeo Flexible de Campos (Moodle &rarr; WooCommerce)</h3>
                        <p class="description">Selecciona un producto de referencia arriba para ver qué datos reales se están importando.</p>
                        <table class="pcc-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Activo</th>
                                    <th>Campo Moodle</th>
                                    <th>WooCommerce (Etiqueta)</th>
                                    <th>Dato Real (Ejemplo)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $mapper = Woo_OTEC_Moodle_Mapper::instance();
                                $mappings = $mapper->get_mappings();
                                $reference_product = $reference_id > 0 ? wc_get_product($reference_id) : null;
                                
                                foreach ($mappings as $moodle_key => $config) : 
                                    $target = $config['target'] ?? '';
                                    $real_value = 'Sin datos';
                                    
                                    if ($reference_product) {
                                        if ($target === 'post_title') {
                                            $real_value = $reference_product->get_name();
                                        } elseif ($target === 'post_content') {
                                            $real_value = wp_trim_words($reference_product->get_description(), 10);
                                        } else {
                                            $real_value = $reference_product->get_meta($target);
                                            if (empty($real_value)) {
                                                // Try without underscore if it has one
                                                $real_value = $reference_product->get_meta(ltrim($target, '_'));
                                            }
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="woo_otec_moodle_mappings[<?php echo esc_attr($moodle_key); ?>][enabled]" value="yes" <?php checked($config['enabled'] ?? 'yes', 'yes'); ?>>
                                        </td>
                                        <td><strong><?php echo esc_html($moodle_key); ?></strong></td>
                                        <td>
                                            <input type="text" name="woo_otec_moodle_mappings[<?php echo esc_attr($moodle_key); ?>][label]" value="<?php echo esc_attr($config['label']); ?>" style="width: 100%;">
                                            <input type="hidden" name="woo_otec_moodle_mappings[<?php echo esc_attr($moodle_key); ?>][target]" value="<?php echo esc_attr($target); ?>">
                                        </td>
                                        <td>
                                            <div style="font-size: 11px; color: #666; font-style: italic; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr((string)$real_value); ?>">
                                                <?php echo esc_html(!empty($real_value) ? (string)$real_value : 'Vacío'); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>

                    <!-- Emails Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'emails' ? 'is-active' : ''; ?>" id="pcc-panel-emails" data-panel="emails" role="tabpanel">
                        <h3>Emails de Notificación</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_enabled">Activar envío</label></th>
                                <td>
                                    <input type="checkbox" id="woo_otec_moodle_email_enabled" name="woo_otec_moodle_email_enabled" value="yes" <?php checked($core->get_option('email_enabled', 'yes'), 'yes'); ?>>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_from_address">Email remitente</label></th>
                                <td>
                                    <input class="regular-text" type="email" id="woo_otec_moodle_email_from_address" name="woo_otec_moodle_email_from_address" value="<?php echo esc_attr($email_from_address); ?>" placeholder="<?php echo esc_attr($fallback_from_address); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_subject">Asunto</label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_email_subject" name="woo_otec_moodle_email_subject" value="<?php echo esc_attr((string) $core->get_option('email_subject', '')); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_template">Plantilla HTML</label></th>
                                <td>
                                    <textarea class="large-text code pcc-email-template-field" rows="12" id="woo_otec_moodle_email_template" name="woo_otec_moodle_email_template"><?php echo esc_textarea((string) $core->get_option('email_template', '')); ?></textarea>
                                </td>
                            </tr>
                        </table>
                        <div class="pcc-email-tools" style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
                            <button type="button" class="button" data-email-preview>Vista previa</button>
                            <input type="email" id="woo_otec_moodle_email_test_recipient" class="regular-text" placeholder="Correo para enviar prueba" style="max-width: 250px;">
                            <button type="button" class="button button-secondary" data-email-send-test>Enviar prueba</button>
                        </div>
                        <div class="pcc-email-feedback" data-email-feedback></div>
                        <div class="pcc-email-preview" data-email-preview-box></div>
                    </section>

                    <!-- Logs Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'logs' ? 'is-active' : ''; ?>" id="pcc-panel-logs" data-panel="logs" role="tabpanel">
                        <h3>Registro de errores</h3>
                        <div class="pcc-log-viewer-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <strong>Últimas 100 líneas del log:</strong>
                            <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=woo_otec_moodle_export_logs&nonce=' . wp_create_nonce('woo_otec_moodle_export_logs'))); ?>" class="button button-secondary">Exportar log</a>
                        </div>
                        <div class="pcc-log-viewer" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;">
                            <?php 
                            if (!empty($error_log)) {
                                foreach ($error_log as $line) {
                                    echo esc_html($line) . '<br>';
                                }
                            } else {
                                echo 'No hay registros recientes.';
                            }
                            ?>
                        </div>
                    </section>
                </div>

                <!-- Submit Button -->
                <div class="pcc-settings-actions" style="margin-top: 20px;">
                    <?php submit_button('Guardar configuración', 'primary', 'submit', false); ?>
                </div>
            </main>
        </div>
    </form>

    <div class="pcc-admin-signature" style="margin-top: 40px; text-align: center; font-size: 12px; color: #888;">
        <span>www.pccurico.cl</span> | <span>desarrollado por JCares</span>
    </div>
</div>
