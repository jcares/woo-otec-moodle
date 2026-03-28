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

// Variables extraidas por render_view(); aseguramos inicializaciones para analisis estatico.
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
$route_connection_ok = (bool) $connection_ok;
$route_sync_ok = $sync_status === 'success';
$route_email_ok = $email_enabled && ($email_from_address !== '' || $fallback_from_address !== '');
$route_sales_ok = $route_connection_ok && $route_sync_ok && $route_email_ok;
$sync_status_label = $sync_status === 'success'
    ? 'Completada'
    : ($sync_status === 'warning' || $sync_status === 'error' ? 'Con incidencias' : 'Sin ejecutar');
$sync_resume = sprintf(
    'Creados: %d | Actualizados: %d',
    (int) ($last_sync['products_created'] ?? 0),
    (int) ($last_sync['products_updated'] ?? 0)
);
$status_cards = array(
    array(
        'label' => 'Estado Conexion',
        'value' => $route_connection_ok ? 'OK' : 'Pendiente',
        'class' => $route_connection_ok ? 'is-success' : 'is-danger',
        'ok'    => $route_connection_ok,
    ),
    array(
        'label' => 'Ultima Sincronizacion',
        'value' => $last_sync_label,
        'class' => $route_sync_ok ? 'is-success' : 'is-warning',
        'ok'    => $route_sync_ok,
    ),
    array(
        'label' => 'Correo',
        'value' => $route_email_ok ? 'Configurado' : 'Pendiente',
        'class' => $route_email_ok ? 'is-success' : 'is-warning',
        'ok'    => $route_email_ok,
    ),
    array(
        'label' => 'Portal Alumnos',
        'value' => $sso_enabled ? 'Activo' : 'Desactivado',
        'class' => $sso_enabled ? 'is-success' : 'is-warning',
        'ok'    => $sso_enabled,
    ),
);
$tabs = array(
    'inicio'   => 'Inicio',
    'general'  => 'Conectar Moodle',
    'sync'     => 'Sincronizacion',
    'sso'      => 'Acceso Logins Alumno',
    'templates'=> 'Metadatos Moodle',
    'appearance'=> 'Apariencia y Colores',
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

    <?php if ($active_tab === 'sync') : ?>
    <div class="pcc-sync-result-box" style="margin-top: 0;">
        <div class="pcc-sync-result-header">
            <h3 style="margin:0;">Ruta recomendada para comenzar</h3>
            <span class="pcc-badge is-info">Guia rapida</span>
        </div>
        <div class="pcc-sync-result-grid">
            <?php
            $route_steps = array(
                array('step' => 'Paso 1', 'label' => 'Conectar Moodle', 'done' => $route_connection_ok),
                array('step' => 'Paso 2', 'label' => 'Sincronizar cursos', 'done' => $route_sync_ok),
                array('step' => 'Paso 3', 'label' => 'Probar correo', 'done' => $route_email_ok),
                array('step' => 'Paso 4', 'label' => 'Activar ventas', 'done' => $route_sales_ok),
            );
            ?>
            <?php foreach ($route_steps as $route_step) : ?>
                <div class="pcc-sync-result-item <?php echo !empty($route_step['done']) ? 'is-done' : 'is-pending'; ?>">
                    <span><?php echo esc_html((string) $route_step['step']); ?></span>
                    <strong><?php echo esc_html((string) $route_step['label']); ?></strong>
                    <p><?php echo !empty($route_step['done']) ? 'Estado: OK' : 'Estado: Pendiente'; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <div class="pcc-sync-result-message" style="margin-top: 12px; padding: 10px; background: #f2f8fd; border-radius: 8px; border: 1px solid #d7e6f1; font-size: 13px;">
            Recomendacion: si es tu primera vez, usa el <strong>Asistente</strong> y despues revisa cada pestana para ajustes finos.
        </div>
    </div>
    <?php endif; ?>


    <?php if ($status !== '') : ?>
        <div class="notice notice-<?php echo $status === 'success' ? 'success' : 'warning'; ?> is-dismissible">
            <p>
                <?php
                echo esc_html(
                    $status === 'success'
                        ? 'La sincronizacion manual finalizo correctamente.'
                        : 'La sincronizacion manual termino con incidencias. Revisa los logs del tab Sincronizacion.'
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

        <!-- Main Dashboard Layout -->
        <div class="pcc-dashboard-layout">
            <!-- Sidebar -->
            <aside class="pcc-dashboard-sidebar">
                <div class="pcc-tabs" role="tablist" aria-label="Secciones de configuracion">
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
                    <!-- Inicio Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'inicio' ? 'is-active' : ''; ?>" id="pcc-panel-inicio" data-panel="inicio" role="tabpanel">
                        <div class="pcc-home-hero">
                            <div>
                                <h2>Inicio</h2>
                            </div>
                            <div class="pcc-home-hero__actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-otec-asistente')); ?>" class="button button-primary pcc-button-compact">Iniciar Asistente de Configuracion</a>
                            </div>
                        </div>

                        <div class="pcc-overview-grid">
                            <?php foreach ($status_cards as $card) : ?>
                                <div class="pcc-overview-card <?php echo esc_attr((string) $card['class']); ?>">
                                    <span class="pcc-overview-card__status" aria-hidden="true">
                                        <?php if (!empty($card['ok'])) : ?>
                                            <svg viewBox="0 0 24 24" focusable="false"><path d="M9.55 17.45 4.8 12.7l1.4-1.4 3.35 3.35 8.25-8.25 1.4 1.4-9.65 9.65Z"/></svg>
                                        <?php else : ?>
                                            <svg viewBox="0 0 24 24" focusable="false"><path d="m13.4 12 4.9-4.9-1.4-1.4-4.9 4.9-4.9-4.9-1.4 1.4 4.9 4.9-4.9 4.9 1.4 1.4 4.9-4.9 4.9 4.9 1.4-1.4-4.9-4.9Z"/></svg>
                                        <?php endif; ?>
                                    </span>
                                    <span class="pcc-overview-card__label"><?php echo esc_html((string) $card['label']); ?></span>
                                    <strong class="pcc-overview-card__value"><?php echo esc_html((string) $card['value']); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- General Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'general' ? 'is-active' : ''; ?>" id="pcc-panel-general" data-panel="general" role="tabpanel">
                        <h3>Conexion Moodle</h3>
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
                                    <p class="pcc-field-help">Token del servicio web con permisos para usuarios, cursos, categorias y matriculas.</p>
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
                                    <p class="pcc-field-help">Monto sin simbolos ni separadores, por ejemplo `49000`.</p>
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
                                <th scope="row"><label for="woo_otec_moodle_fallback_description">Descripcion fallback</label></th>
                                <td>
                                    <textarea class="large-text" rows="4" id="woo_otec_moodle_fallback_description" name="woo_otec_moodle_fallback_description"><?php echo esc_textarea((string) $core->get_option('fallback_description', '')); ?></textarea>
                                    <p class="pcc-field-help">Descripcion usada al crear o actualizar productos sin contenido suficiente.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Exportar configuracion</th>
                                <td>
                                    <button type="button" class="button button-secondary" id="pcc-export-config">Descargar configuracion actual</button>
                                    <p class="pcc-field-help">Descarga un JSON con la configuracion actual del plugin, incluyendo conexion Moodle, parametros, apariencia, correo, mappings y campos activos.</p>
                                    <div id="pcc-config-export-result"></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Importar configuracion</th>
                                <td>
                                    <input type="file" id="pcc-import-config-file" accept=".json,application/json">
                                    <button type="button" class="button button-secondary" id="pcc-import-config">Importar configuracion</button>
                                    <p class="pcc-field-help">Carga un archivo JSON exportado desde este plugin para restaurar conexion Moodle, parametros, apariencia, correo, mappings y campos activos.</p>
                                    <div id="pcc-config-import-result"></div>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <!-- Sync Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'sync' ? 'is-active' : ''; ?>" id="pcc-panel-sync" data-panel="sync" role="tabpanel">
                        <div class="pcc-sync-header">
                            <h3>Asistente de Sincronizacion</h3>
                        </div>

                        <div class="pcc-wizard-steps">
                            <div class="pcc-step is-active" data-step="1">
                                <span class="pcc-step-num">1</span>
                                <span class="pcc-step-label">Categorias</span>
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
                                <span class="pcc-step-label">Confirmacion</span>
                            </div>
                        </div>

                        <!-- Step 1: Categories -->
                        <div class="pcc-wizard-content is-active" id="pcc-step-1-content">
                            <h3>Paso 1: Seleccion de Categorias</h3>
                            <p>Obteniendo categorias desde Moodle... Por favor, selecciona las que deseas importar.</p>
                            <div id="pcc-categories-list-container">
                                <div class="pcc-loading-spinner">Cargando categorias...</div>
                            </div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-primary" id="pcc-btn-next-1">Confirmar y continuar</button>
                            </div>
                        </div>

                        <!-- Step 2: Teachers -->
                        <div class="pcc-wizard-content" id="pcc-step-2-content" style="display:none;">
                            <h3>Paso 2: Profesores Detectados</h3>
                            <p>Estos son los profesores asociados a los cursos de las categorias seleccionadas. Puedes verificar sus nombres antes de proceder.</p>
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
                            <h3>Paso 3: Configuracion de Cursos</h3>
                            <p>Edita la informacion de los cursos antes de importarlos.</p>
                            <div id="pcc-courses-table-container" style="overflow-x: auto;"></div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-secondary" id="pcc-btn-prev-3">Anterior</button>
                                <button type="button" class="button button-primary" id="pcc-btn-next-3">Siguiente paso</button>
                            </div>
                        </div>

                        <!-- Step 4: Confirmation -->
                        <div class="pcc-wizard-content" id="pcc-step-4-content" style="display:none;">
                            <h3>Paso 4: Resumen y Confirmacion</h3>
                            <div id="pcc-sync-summary-container"></div>
                            <div class="pcc-progress" style="display:none; margin: 20px 0;">
                                <div class="pcc-progress-bar" style="width:0%">0%</div>
                            </div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-secondary" id="pcc-btn-prev-4">Anterior</button>
                                <button type="button" class="button button-primary" id="pcc-btn-execute-sync">Ejecutar sincronizacion</button>
                            </div>
                        </div>

                        <details class="pcc-log-details" style="margin-top: 20px;">
                            <summary style="cursor: pointer; font-weight: 600; color: var(--pcc-primary);">Ver historial tecnico (Logs)</summary>
                            <pre class="pcc-log-view" style="margin-top: 10px; max-height: 200px; overflow-y: auto;"><?php echo esc_html(implode("\n", $sync_log)); ?></pre>
                        </details>
                    </section>

                    <!-- SSO Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'sso' ? 'is-active' : ''; ?>" id="pcc-panel-sso" data-panel="sso" role="tabpanel">
                        <h3>Acceso automatico (SSO)</h3>
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
                                    <p class="pcc-field-help">Envia al alumno directo al aula virtual al finalizar la compra.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Prueba</th>
                                <td>
                                    <button type="button" class="button button-secondary" id="pcc-test-sso-connection">Probar conexion</button>
                                    <span id="sso-test-result" style="margin-left: 10px;"></span>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <!-- Templates Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'templates' ? 'is-active' : ''; ?>" id="pcc-panel-templates" data-panel="templates" role="tabpanel">
                        <h3>Metadatos y Mapeo</h3>
                        <?php
                        $reference_id = (int) $core->get_option('template_reference', 0);
                        $reference_products = Woo_OTEC_Moodle_Settings::instance()->get_template_reference_products();
                        $selected_fields = (array) $core->get_option('template_fields', array());
                        ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_template_reference">Curso de ejemplo</label></th>
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
                            <tr>
                                <th scope="row">Datos que quieres mostrar</th>
                                <td>
                                    <p class="pcc-field-help">Selecciona abajo los datos que quieres ver en la ficha del curso. Los nombres ya aparecen en un lenguaje mas claro para el usuario final.</p>
                                </td>
                            </tr>
                        </table>

                        <hr>
                        <h3>Datos disponibles del curso</h3>
                        <p class="description">Activa solo la informacion que quieras mostrar. Si el curso tiene portada sincronizada desde Moodle, aqui tambien veras la imagen de forma visual.</p>
                        <div data-template-fields>
                            <?php
                            if ($reference_id > 0) {
                                Woo_OTEC_Moodle_Settings::instance()->render_template_fields_markup($reference_id, $selected_fields, true);
                            } else {
                                echo '<p class="description">Selecciona un curso para listar los metadatos disponibles.</p>';
                            }
                            ?>
                        </div>
                        <p class="description pcc-template-preview-description">La vista previa se actualiza segun el curso de ejemplo y los campos que dejes activos.</p>
                        <div class="pcc-template-preview-card" style="margin-top: 16px;">
                            <h4>Vista previa en vivo</h4>
                            <div data-template-live-preview>
                                <p class="description">Selecciona un curso y marca los datos que quieres mostrar para ver la ficha en tiempo real.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Emails Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'emails' ? 'is-active' : ''; ?>" id="pcc-panel-emails" data-panel="emails" role="tabpanel">
                        <h3>Envio y Pruebas de Email</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_enabled">Activar envio</label></th>
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
                                    <p class="pcc-field-help">La apariencia visual del correo ahora se edita desde la pestana Apariencia.</p>
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

                    <!-- Appearance Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'appearance' ? 'is-active' : ''; ?>" id="pcc-panel-appearance" data-panel="appearance" role="tabpanel">
                        <h3>Constructor Visual</h3>
                        <p class="pcc-field-help">Configura cada plantilla por separado. Los cambios se aplican solo en las areas del plugin para evitar afectar el resto del sitio.</p>
                        <?php $appearance_profile = (string) $core->get_option('appearance_profile', 'product'); ?>
                        <input type="hidden" id="woo_otec_moodle_appearance_profile" name="woo_otec_moodle_appearance_profile" value="<?php echo esc_attr($appearance_profile); ?>">
                        <div class="pcc-appearance-nav">
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'product' ? 'is-active' : ''; ?>" data-appearance-tab="product">Single Product</button>
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'shop' ? 'is-active' : ''; ?>" data-appearance-tab="shop">Tienda</button>
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'cart' ? 'is-active' : ''; ?>" data-appearance-tab="cart">Carrito</button>
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'checkout' ? 'is-active' : ''; ?>" data-appearance-tab="checkout">Checkout</button>
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'email' ? 'is-active' : ''; ?>" data-appearance-tab="email">Email</button>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="product">
                            <?php
                            $appearance_preview_id = $reference_id;
                            if ($appearance_preview_id <= 0 && !empty($reference_products)) {
                                $appearance_preview_id = (int) $reference_products[0]->get_id();
                            }
                            $appearance_preview_product = $appearance_preview_id > 0 ? wc_get_product($appearance_preview_id) : null;
                            $appearance_preview_name = $appearance_preview_product instanceof WC_Product ? $appearance_preview_product->get_name() : 'Curso sincronizado de ejemplo';
                            $appearance_preview_short = $appearance_preview_product instanceof WC_Product ? wp_trim_words(wp_strip_all_tags((string) $appearance_preview_product->get_short_description()), 18) : 'Asi se vera el bloque principal del curso con tu paleta actual.';
                            $appearance_preview_price = $appearance_preview_product instanceof WC_Product ? wp_strip_all_tags((string) $appearance_preview_product->get_price_html()) : '$49.000';
                            $appearance_preview_image = $appearance_preview_product instanceof WC_Product ? (get_the_post_thumbnail_url($appearance_preview_product->get_id(), 'medium') ?: '') : '';
                            $appearance_preview_modality = $appearance_preview_product instanceof WC_Product ? (string) get_post_meta($appearance_preview_product->get_id(), '_modality', true) : 'Modalidad no informada';
                            $appearance_preview_teacher = $appearance_preview_product instanceof WC_Product ? (string) get_post_meta($appearance_preview_product->get_id(), '_instructor', true) : 'Relator no informado';
                            ?>
                            <div class="pcc-appearance-head">
                                <h4>Single Product</h4>
                                <p>Controla la ficha del curso, el texto del boton principal y la seccion descriptiva.</p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_template_style">Estilo de plantilla</label></th>
                                    <td>
                                        <select id="woo_otec_moodle_template_style" name="woo_otec_moodle_template_style">
                                            <?php $current_style = $core->get_option('template_style', 'classic'); ?>
                                            <option value="classic" <?php selected($current_style, 'classic'); ?>>Classic (WooCommerce)</option>
                                            <option value="academy" <?php selected($current_style, 'academy'); ?>>Academy</option>
                                            <option value="pccurico" <?php selected($current_style, 'pccurico'); ?>>PCCurico</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="pcc-appearance-preview-product">Curso para vista previa</label></th>
                                    <td>
                                        <select id="pcc-appearance-preview-product">
                                            <option value="">Selecciona un curso sincronizado</option>
                                            <?php foreach ($reference_products as $product) : ?>
                                                <?php
                                                $preview_product = wc_get_product($product->get_id());
                                                if (!$preview_product instanceof WC_Product) {
                                                    continue;
                                                }
                                                $preview_name = $preview_product->get_name();
                                                $preview_short = wp_trim_words(wp_strip_all_tags((string) $preview_product->get_short_description()), 18);
                                                $preview_price = wp_strip_all_tags((string) $preview_product->get_price_html());
                                                $preview_image = get_the_post_thumbnail_url($preview_product->get_id(), 'medium') ?: '';
                                                $preview_modality = (string) get_post_meta($preview_product->get_id(), '_modality', true);
                                                $preview_teacher = (string) get_post_meta($preview_product->get_id(), '_instructor', true);
                                                ?>
                                                <option
                                                    value="<?php echo esc_attr((string) $preview_product->get_id()); ?>"
                                                    data-name="<?php echo esc_attr($preview_name); ?>"
                                                    data-short="<?php echo esc_attr($preview_short !== '' ? $preview_short : 'Sin resumen breve disponible.'); ?>"
                                                    data-price="<?php echo esc_attr($preview_price !== '' ? $preview_price : 'Sin precio'); ?>"
                                                    data-image="<?php echo esc_url($preview_image); ?>"
                                                    data-modality="<?php echo esc_attr($preview_modality !== '' ? $preview_modality : 'Modalidad no informada'); ?>"
                                                    data-teacher="<?php echo esc_attr($preview_teacher !== '' ? $preview_teacher : 'Relator no informado'); ?>"
                                                    <?php selected($appearance_preview_id, $preview_product->get_id()); ?>
                                                >
                                                    <?php echo esc_html($preview_name); ?> (#<?php echo esc_html((string) $preview_product->get_id()); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="pcc-field-help">Usa un curso real para ver la plantilla con informacion verdadera.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_single_button_text">Texto boton compra</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_single_button_text" name="woo_otec_moodle_single_button_text" value="<?php echo esc_attr((string) $core->get_option('single_button_text', 'Comprar curso')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_single_description_heading">Titulo descripcion</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_single_description_heading" name="woo_otec_moodle_single_description_heading" value="<?php echo esc_attr((string) $core->get_option('single_description_heading', 'Descripcion del curso')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_pcc_color_primary">Color Primario</label></th>
                                    <td>
                                        <input type="color" id="woo_otec_moodle_pcc_color_primary" name="woo_otec_moodle_pcc_color_primary" value="<?php echo esc_attr((string) $core->get_option('pcc_color_primary', '#023E25')); ?>" class="regular-text">
                                        <p class="pcc-field-help">Usado en botones, iconos y titulares.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_pcc_color_secondary">Color Secundario</label></th>
                                    <td>
                                        <input type="color" id="woo_otec_moodle_pcc_color_secondary" name="woo_otec_moodle_pcc_color_secondary" value="<?php echo esc_attr((string) $core->get_option('pcc_color_secondary', '#6EC1E4')); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_pcc_color_text">Color de Texto</label></th>
                                    <td>
                                        <input type="color" id="woo_otec_moodle_pcc_color_text" name="woo_otec_moodle_pcc_color_text" value="<?php echo esc_attr((string) $core->get_option('pcc_color_text', '#7A7A7A')); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_pcc_color_accent">Color de Acento</label></th>
                                    <td>
                                        <input type="color" id="woo_otec_moodle_pcc_color_accent" name="woo_otec_moodle_pcc_color_accent" value="<?php echo esc_attr((string) $core->get_option('pcc_color_accent', '#61CE70')); ?>" class="regular-text">
                                    </td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-card pcc-appearance-mini-card--<?php echo esc_attr((string) $core->get_option('template_style', 'classic')); ?>" data-appearance-single-preview>
                                    <span class="pcc-appearance-mini-kicker">Vista rapida</span>
                                    <div class="pcc-appearance-mini-card__media<?php echo $appearance_preview_image === '' ? ' is-empty' : ''; ?>">
                                        <?php if ($appearance_preview_image !== '') : ?>
                                            <img src="<?php echo esc_url($appearance_preview_image); ?>" alt="<?php echo esc_attr($appearance_preview_name); ?>" data-preview-image>
                                        <?php else : ?>
                                            <span data-preview-image-placeholder>Sin portada</span>
                                        <?php endif; ?>
                                    </div>
                                    <h5 style="color: <?php echo esc_attr((string) $core->get_option('pcc_color_primary', '#023E25')); ?>;" data-preview-name><?php echo esc_html($appearance_preview_name); ?></h5>
                                    <p style="color: <?php echo esc_attr((string) $core->get_option('pcc_color_text', '#7A7A7A')); ?>;">Asi se verá el bloque principal del curso con tu paleta actual.</p>
                                    <div class="pcc-appearance-mini-meta">
                                        <span data-preview-modality><?php echo esc_html($appearance_preview_modality); ?></span>
                                        <span data-preview-teacher><?php echo esc_html($appearance_preview_teacher); ?></span>
                                    </div>
                                    <strong class="pcc-appearance-mini-price" style="color: <?php echo esc_attr((string) $core->get_option('pcc_color_primary', '#023E25')); ?>;" data-preview-price><?php echo esc_html($appearance_preview_price); ?></strong>
                                    <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('pcc_color_accent', '#61CE70')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('pcc_color_accent', '#61CE70')); ?>;">
                                        <?php echo esc_html((string) $core->get_option('single_button_text', 'Comprar curso')); ?>
                                    </button>
                                    <div class="pcc-appearance-mini-description">
                                        <span data-preview-description-heading><?php echo esc_html((string) $core->get_option('single_description_heading', 'Descripcion del curso')); ?></span>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="shop">
                            <div class="pcc-appearance-head">
                                <h4>Tienda</h4>
                                <p>Edita el bloque superior y el color principal de los botones del catalogo.</p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_intro_title">Titulo bloque</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_shop_intro_title" name="woo_otec_moodle_shop_intro_title" value="<?php echo esc_attr((string) $core->get_option('shop_intro_title', 'Explora nuestra oferta de cursos')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_intro_text">Texto apoyo</label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_shop_intro_text" name="woo_otec_moodle_shop_intro_text"><?php echo esc_textarea((string) $core->get_option('shop_intro_text', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_color_bg">Color fondo</label></th>
                                    <td><input type="color" id="woo_otec_moodle_shop_color_bg" name="woo_otec_moodle_shop_color_bg" value="<?php echo esc_attr((string) $core->get_option('shop_color_bg', '#f8fbff')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_color_title">Color titulo</label></th>
                                    <td><input type="color" id="woo_otec_moodle_shop_color_title" name="woo_otec_moodle_shop_color_title" value="<?php echo esc_attr((string) $core->get_option('shop_color_title', '#21405a')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_color_text">Color texto</label></th>
                                    <td><input type="color" id="woo_otec_moodle_shop_color_text" name="woo_otec_moodle_shop_color_text" value="<?php echo esc_attr((string) $core->get_option('shop_color_text', '#2b4b63')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_button_text">Texto boton</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_shop_button_text" name="woo_otec_moodle_shop_button_text" value="<?php echo esc_attr((string) $core->get_option('shop_button_text', 'Ver curso')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_color_button">Color botones</label></th>
                                    <td><input type="color" id="woo_otec_moodle_shop_color_button" name="woo_otec_moodle_shop_color_button" value="<?php echo esc_attr((string) $core->get_option('shop_color_button', '#0f3d5e')); ?>"></td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-banner" style="background: <?php echo esc_attr((string) $core->get_option('shop_color_bg', '#f8fbff')); ?>;">
                                    <h5 style="color: <?php echo esc_attr((string) $core->get_option('shop_color_title', '#21405a')); ?>;"><?php echo esc_html((string) $core->get_option('shop_intro_title', 'Explora nuestra oferta de cursos')); ?></h5>
                                    <p style="color: <?php echo esc_attr((string) $core->get_option('shop_color_text', '#2b4b63')); ?>;"><?php echo esc_html((string) $core->get_option('shop_intro_text', 'Descubre nuestros cursos disponibles y selecciona el que mejor se ajusta a tu objetivo.')); ?></p>
                                    <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('shop_color_button', '#0f3d5e')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('shop_color_button', '#0f3d5e')); ?>;">
                                        <?php echo esc_html((string) $core->get_option('shop_button_text', 'Ver curso')); ?>
                                    </button>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="cart">
                            <div class="pcc-appearance-head">
                                <h4>Carrito</h4>
                                <p>Define el mensaje superior y el color del boton de avanzar al pago.</p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_intro_title">Titulo bloque</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_cart_intro_title" name="woo_otec_moodle_cart_intro_title" value="<?php echo esc_attr((string) $core->get_option('cart_intro_title', 'Tu carrito de capacitacion')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_intro_text">Texto apoyo</label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_cart_intro_text" name="woo_otec_moodle_cart_intro_text"><?php echo esc_textarea((string) $core->get_option('cart_intro_text', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_color_bg">Color fondo</label></th>
                                    <td><input type="color" id="woo_otec_moodle_cart_color_bg" name="woo_otec_moodle_cart_color_bg" value="<?php echo esc_attr((string) $core->get_option('cart_color_bg', '#f5fbf8')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_color_title">Color titulo</label></th>
                                    <td><input type="color" id="woo_otec_moodle_cart_color_title" name="woo_otec_moodle_cart_color_title" value="<?php echo esc_attr((string) $core->get_option('cart_color_title', '#1d5a41')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_color_text">Color texto</label></th>
                                    <td><input type="color" id="woo_otec_moodle_cart_color_text" name="woo_otec_moodle_cart_color_text" value="<?php echo esc_attr((string) $core->get_option('cart_color_text', '#355846')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_color_button">Color boton</label></th>
                                    <td><input type="color" id="woo_otec_moodle_cart_color_button" name="woo_otec_moodle_cart_color_button" value="<?php echo esc_attr((string) $core->get_option('cart_color_button', '#1f9d6f')); ?>"></td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-banner" style="background: <?php echo esc_attr((string) $core->get_option('cart_color_bg', '#f5fbf8')); ?>;">
                                    <h5 style="color: <?php echo esc_attr((string) $core->get_option('cart_color_title', '#1d5a41')); ?>;"><?php echo esc_html((string) $core->get_option('cart_intro_title', 'Tu carrito de capacitacion')); ?></h5>
                                    <p style="color: <?php echo esc_attr((string) $core->get_option('cart_color_text', '#355846')); ?>;"><?php echo esc_html((string) $core->get_option('cart_intro_text', 'Revisa tus cursos antes de finalizar el pago.')); ?></p>
                                    <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('cart_color_button', '#1f9d6f')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('cart_color_button', '#1f9d6f')); ?>;">
                                        Continuar al pago
                                    </button>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="checkout">
                            <div class="pcc-appearance-head">
                                <h4>Checkout</h4>
                                <p>Define el bloque introductorio y el color del boton final de compra.</p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_intro_title">Titulo bloque</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_checkout_intro_title" name="woo_otec_moodle_checkout_intro_title" value="<?php echo esc_attr((string) $core->get_option('checkout_intro_title', 'Ultimo paso para activar tus cursos')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_intro_text">Texto apoyo</label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_checkout_intro_text" name="woo_otec_moodle_checkout_intro_text"><?php echo esc_textarea((string) $core->get_option('checkout_intro_text', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_color_bg">Color fondo</label></th>
                                    <td><input type="color" id="woo_otec_moodle_checkout_color_bg" name="woo_otec_moodle_checkout_color_bg" value="<?php echo esc_attr((string) $core->get_option('checkout_color_bg', '#fff8f1')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_color_title">Color titulo</label></th>
                                    <td><input type="color" id="woo_otec_moodle_checkout_color_title" name="woo_otec_moodle_checkout_color_title" value="<?php echo esc_attr((string) $core->get_option('checkout_color_title', '#7b4b12')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_color_text">Color texto</label></th>
                                    <td><input type="color" id="woo_otec_moodle_checkout_color_text" name="woo_otec_moodle_checkout_color_text" value="<?php echo esc_attr((string) $core->get_option('checkout_color_text', '#6f5a40')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_button_text">Texto boton</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_checkout_button_text" name="woo_otec_moodle_checkout_button_text" value="<?php echo esc_attr((string) $core->get_option('checkout_button_text', 'Finalizar compra')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_color_button">Color boton</label></th>
                                    <td><input type="color" id="woo_otec_moodle_checkout_color_button" name="woo_otec_moodle_checkout_color_button" value="<?php echo esc_attr((string) $core->get_option('checkout_color_button', '#d9822b')); ?>"></td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-banner" style="background: <?php echo esc_attr((string) $core->get_option('checkout_color_bg', '#fff8f1')); ?>;">
                                    <h5 style="color: <?php echo esc_attr((string) $core->get_option('checkout_color_title', '#7b4b12')); ?>;"><?php echo esc_html((string) $core->get_option('checkout_intro_title', 'Ultimo paso para activar tus cursos')); ?></h5>
                                    <p style="color: <?php echo esc_attr((string) $core->get_option('checkout_color_text', '#6f5a40')); ?>;"><?php echo esc_html((string) $core->get_option('checkout_intro_text', 'Completa tus datos para activar el acceso inmediato a tus cursos.')); ?></p>
                                    <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('checkout_color_button', '#d9822b')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('checkout_color_button', '#d9822b')); ?>;">
                                        <?php echo esc_html((string) $core->get_option('checkout_button_text', 'Finalizar compra')); ?>
                                    </button>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="email">
                            <div class="pcc-appearance-head">
                                <h4>Email</h4>
                                <p>Edita aqui el contenido visual del correo sin tocar HTML.</p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row">Editor amigable</th>
                                    <td>
                                        <input type="checkbox" id="woo_otec_moodle_email_builder_enabled" name="woo_otec_moodle_email_builder_enabled" value="yes" <?php checked($core->get_option('email_builder_enabled', 'yes'), 'yes'); ?>>
                                        <label for="woo_otec_moodle_email_builder_enabled">Usar constructor visual sin HTML</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_heading_appearance">Titulo principal</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_email_builder_heading_appearance" name="woo_otec_moodle_email_builder_heading" value="<?php echo esc_attr((string) $core->get_option('email_builder_heading', 'Tus accesos ya estan listos')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_intro_appearance">Texto principal</label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_email_builder_intro_appearance" name="woo_otec_moodle_email_builder_intro"><?php echo esc_textarea((string) $core->get_option('email_builder_intro', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_button_text_appearance">Texto boton</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_email_builder_button_text_appearance" name="woo_otec_moodle_email_builder_button_text" value="<?php echo esc_attr((string) $core->get_option('email_builder_button_text', 'Acceder a mis cursos')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_footer_appearance">Texto final</label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_email_builder_footer_appearance" name="woo_otec_moodle_email_builder_footer"><?php echo esc_textarea((string) $core->get_option('email_builder_footer', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row">Logo del correo</th>
                                    <td>
                                        <?php
                                        $email_logo_id = (int) $core->get_option('email_logo_id', 0);
                                        $theme_logo_id = (int) get_theme_mod('custom_logo');
                                        $effective_logo_id = $email_logo_id > 0 ? $email_logo_id : $theme_logo_id;
                                        $email_logo_url = $effective_logo_id > 0 ? wp_get_attachment_image_url($effective_logo_id, 'medium') : '';
                                        ?>
                                        <input type="hidden" id="woo_otec_moodle_email_logo_id" name="woo_otec_moodle_email_logo_id" value="<?php echo esc_attr((string) $email_logo_id); ?>">
                                        <button type="button" class="button pcc-media-picker" data-target="#woo_otec_moodle_email_logo_id" data-preview="#pcc-email-logo-preview">Seleccionar logo</button>
                                        <p class="pcc-field-help">Si no seleccionas uno, se usa automaticamente el logo configurado en tu tema.</p>
                                        <div class="pcc-image-preview-wrap">
                                            <img id="pcc-email-logo-preview" src="<?php echo esc_url((string) $email_logo_url); ?>" alt="" class="pcc-image-preview<?php echo $email_logo_url === '' ? ' is-hidden' : ''; ?>">
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_color_primary_appearance">Color encabezado</label></th>
                                    <td><input type="color" id="woo_otec_moodle_email_color_primary_appearance" name="woo_otec_moodle_email_color_primary" value="<?php echo esc_attr((string) $core->get_option('email_color_primary', '#0f3d5e')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_color_accent_appearance">Color boton</label></th>
                                    <td><input type="color" id="woo_otec_moodle_email_color_accent_appearance" name="woo_otec_moodle_email_color_accent" value="<?php echo esc_attr((string) $core->get_option('email_color_accent', '#1f9d6f')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_color_bg_appearance">Color fondo</label></th>
                                    <td><input type="color" id="woo_otec_moodle_email_color_bg_appearance" name="woo_otec_moodle_email_color_bg" value="<?php echo esc_attr((string) $core->get_option('email_color_bg', '#f3f8fc')); ?>"></td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-email" style="background: <?php echo esc_attr((string) $core->get_option('email_color_bg', '#f3f8fc')); ?>;">
                                    <div class="pcc-appearance-mini-email__head" style="background: <?php echo esc_attr((string) $core->get_option('email_color_primary', '#0f3d5e')); ?>;">
                                        <strong><?php echo esc_html((string) $core->get_option('email_builder_heading', 'Tus accesos ya estan listos')); ?></strong>
                                    </div>
                                    <div class="pcc-appearance-mini-email__body">
                                        <p><?php echo esc_html((string) $core->get_option('email_builder_intro', 'Tu compra fue confirmada correctamente. Aqui tienes los datos para ingresar a tu plataforma.')); ?></p>
                                        <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('email_color_accent', '#1f9d6f')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('email_color_accent', '#1f9d6f')); ?>;">
                                            <?php echo esc_html((string) $core->get_option('email_builder_button_text', 'Acceder a mis cursos')); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </section>

                    <!-- Logs Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'logs' ? 'is-active' : ''; ?>" id="pcc-panel-logs" data-panel="logs" role="tabpanel">
                        <h3>Registro de errores</h3>
                        <div class="pcc-log-viewer-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <strong>Ultimas 100 lineas del log:</strong>
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
                    <?php submit_button('Guardar configuracion', 'primary', 'submit', false); ?>
                </div>
            </main>
        </div>
    </form>

    <div class="pcc-admin-signature" style="margin-top: 40px; text-align: center; font-size: 12px; color: #888;">
        <span>www.pccurico.cl</span> | <span>desarrollado por JCares</span>
    </div>

    <div id="pcc-admin-notices-bottom" class="pcc-admin-notices-bottom"></div>
</div>
