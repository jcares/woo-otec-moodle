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
                        <p class="description">Credenciales para realizar la conexion con Moodle.</p>
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
                            <h2>Sincronizacion</h2>
                            <div class="pcc-badge is-info">Moodle &rarr; WooCommerce</div>
                        </div>

                        <div class="pcc-sync-inline-summary">
                            <span class="pcc-sync-inline-summary__title">Resumen de ultima ejecucion</span>
                            <span class="pcc-badge <?php echo $sync_status === 'success' ? 'is-success' : 'is-warning'; ?>">
                                <?php echo esc_html($sync_status_label); ?>
                            </span>
                            <span class="pcc-sync-inline-summary__item"><strong>Fecha:</strong> <?php echo esc_html($last_sync_label); ?></span>
                            <span class="pcc-sync-inline-summary__item"><strong>Categorias:</strong> <?php echo esc_html((string) ($last_sync['categories_created'] ?? 0)); ?></span>
                            <span class="pcc-sync-inline-summary__item"><strong>Creados:</strong> <?php echo esc_html((string) ($last_sync['products_created'] ?? 0)); ?></span>
                            <span class="pcc-sync-inline-summary__item"><strong>Actualizados:</strong> <?php echo esc_html((string) ($last_sync['products_updated'] ?? 0)); ?></span>
                            <?php if (!empty($last_sync['message'])) : ?>
                                <span class="pcc-sync-inline-summary__item pcc-sync-inline-summary__message"><?php echo esc_html((string) $last_sync['message']); ?></span>
                            <?php endif; ?>
                        </div>

                        <h3>Asistente de Sincronizacion</h3>
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
                        <p class="description">Configuracion del inicio de sesion unico entre WooCommerce y Moodle.</p>
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
                        <p class="description">Configura como se muestran y vinculan los datos de Moodle.</p>
                        <?php
                        $reference_id = (int) $core->get_option('template_reference', 0);
                        $reference_products = Woo_OTEC_Moodle_Settings::instance()->get_template_reference_products();
                        $selected_fields = (array) $core->get_option('template_fields', array());
                        ?>
                        <table class="form-table">
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
                            <tr>
                                <th scope="row">Campos Visibles a Mostrar</th>
                                <td>
                                    <p class="pcc-field-help">Los campos visibles se gestionan en la seccion inferior de mapeo flexible.</p>
                                </td>
                            </tr>
                        </table>

                        <hr>
                        <h3>Mapeo Flexible de Campos (Moodle &rarr; WooCommerce)</h3>
                        <p class="description">Activa los campos que quieras mostrar. La etiqueta se mantiene fija y el dato real se puede ajustar manualmente.</p>
                        <div data-template-fields>
                            <?php
                            if ($reference_id > 0) {
                                Woo_OTEC_Moodle_Settings::instance()->render_template_fields_markup($reference_id, $selected_fields, true);
                            } else {
                                echo '<p class="description">Selecciona un curso para listar los metadatos disponibles.</p>';
                            }
                            ?>
                        </div>
                        <div data-template-feedback class="pcc-template-feedback"></div>
                        <table class="pcc-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Activo</th>
                                    <th>WooCommerce (Etiqueta)</th>
                                    <th>Dato Real (Editable)</th>
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
                                        <td>
                                            <strong><?php echo esc_html((string) ($config['label'] ?? '')); ?></strong>
                                            <input type="hidden" name="woo_otec_moodle_mappings[<?php echo esc_attr($moodle_key); ?>][label]" value="<?php echo esc_attr((string) ($config['label'] ?? '')); ?>">
                                            <input type="hidden" name="woo_otec_moodle_mappings[<?php echo esc_attr($moodle_key); ?>][target]" value="<?php echo esc_attr($target); ?>">
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="regular-text pcc-mapping-manual-value"
                                                data-meta-key="<?php echo esc_attr((string) $target); ?>"
                                                data-moodle-key="<?php echo esc_attr((string) $moodle_key); ?>"
                                                name="woo_otec_moodle_mappings[<?php echo esc_attr($moodle_key); ?>][manual_value]"
                                                value="<?php echo esc_attr((string) ($config['manual_value'] ?? $real_value)); ?>"
                                                placeholder="Sin valor">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="pcc-template-preview-card" style="margin-top: 16px;">
                            <h4>Vista previa en vivo</h4>
                            <div data-template-live-preview>
                                <p class="description">Selecciona campos y edita valores para ver la plantilla en tiempo real.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Emails Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'emails' ? 'is-active' : ''; ?>" id="pcc-panel-emails" data-panel="emails" role="tabpanel">
                        <h3>Emails de Notificacion</h3>
                        <p class="description">Configura el correo sin tocar HTML. Puedes usar el logo del sitio o subir uno propio.</p>
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
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Editor amigable</th>
                                <td>
                                    <input type="checkbox" id="woo_otec_moodle_email_builder_enabled" name="woo_otec_moodle_email_builder_enabled" value="yes" <?php checked($core->get_option('email_builder_enabled', 'yes'), 'yes'); ?>>
                                    <label for="woo_otec_moodle_email_builder_enabled">Usar editor visual sin HTML</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_builder_heading">Titulo principal</label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_email_builder_heading" name="woo_otec_moodle_email_builder_heading" value="<?php echo esc_attr((string) $core->get_option('email_builder_heading', 'Tus accesos ya estan listos')); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_builder_intro">Texto de bienvenida</label></th>
                                <td>
                                    <textarea class="large-text" rows="4" id="woo_otec_moodle_email_builder_intro" name="woo_otec_moodle_email_builder_intro"><?php echo esc_textarea((string) $core->get_option('email_builder_intro', '')); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_builder_button_text">Texto del boton</label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_email_builder_button_text" name="woo_otec_moodle_email_builder_button_text" value="<?php echo esc_attr((string) $core->get_option('email_builder_button_text', 'Acceder a mis cursos')); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_builder_footer">Texto final</label></th>
                                <td>
                                    <textarea class="large-text" rows="3" id="woo_otec_moodle_email_builder_footer" name="woo_otec_moodle_email_builder_footer"><?php echo esc_textarea((string) $core->get_option('email_builder_footer', '')); ?></textarea>
                                </td>
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
                                <th scope="row">Colores del correo</th>
                                <td>
                                    <label>Encabezado: <input type="color" id="woo_otec_moodle_email_color_primary" name="woo_otec_moodle_email_color_primary" value="<?php echo esc_attr((string) $core->get_option('email_color_primary', '#0f3d5e')); ?>"></label>
                                    <label style="margin-left:12px;">Boton: <input type="color" id="woo_otec_moodle_email_color_accent" name="woo_otec_moodle_email_color_accent" value="<?php echo esc_attr((string) $core->get_option('email_color_accent', '#1f9d6f')); ?>"></label>
                                    <label style="margin-left:12px;">Fondo: <input type="color" id="woo_otec_moodle_email_color_bg" name="woo_otec_moodle_email_color_bg" value="<?php echo esc_attr((string) $core->get_option('email_color_bg', '#f3f8fc')); ?>"></label>
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
                        <h3>Apariencia y Colores</h3>
                        <p class="description">Centraliza aqui el look &amp; feel de Producto, Email y Tienda/Carrito/Checkout.</p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_appearance_profile">Perfil visual a editar</label></th>
                                <td>
                                    <?php $appearance_profile = (string) $core->get_option('appearance_profile', 'product'); ?>
                                    <select id="woo_otec_moodle_appearance_profile" name="woo_otec_moodle_appearance_profile">
                                        <option value="product" <?php selected($appearance_profile, 'product'); ?>>Plantilla Producto (PCCurico)</option>
                                        <option value="email" <?php selected($appearance_profile, 'email'); ?>>Plantilla Email</option>
                                        <option value="store" <?php selected($appearance_profile, 'store'); ?>>Tienda, Carrito y Checkout</option>
                                    </select>
                                    <p class="pcc-field-help">Selecciona un perfil para mostrar sus controles.</p>
                                </td>
                            </tr>
                        </table>

                        <div class="pcc-appearance-group" data-appearance-group="product">
                            <h4>Plantilla Producto</h4>
                            <table class="form-table">
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

                        <div class="pcc-appearance-group" data-appearance-group="email">
                            <h4>Plantilla Email</h4>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_color_primary_appearance">Color Encabezado</label></th>
                                    <td><input type="color" id="woo_otec_moodle_email_color_primary_appearance" name="woo_otec_moodle_email_color_primary" value="<?php echo esc_attr((string) $core->get_option('email_color_primary', '#0f3d5e')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_color_accent_appearance">Color Boton</label></th>
                                    <td><input type="color" id="woo_otec_moodle_email_color_accent_appearance" name="woo_otec_moodle_email_color_accent" value="<?php echo esc_attr((string) $core->get_option('email_color_accent', '#1f9d6f')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_color_bg_appearance">Color Fondo</label></th>
                                    <td><input type="color" id="woo_otec_moodle_email_color_bg_appearance" name="woo_otec_moodle_email_color_bg" value="<?php echo esc_attr((string) $core->get_option('email_color_bg', '#f3f8fc')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_heading_appearance">Titulo Email</label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_email_builder_heading_appearance" name="woo_otec_moodle_email_builder_heading" value="<?php echo esc_attr((string) $core->get_option('email_builder_heading', 'Tus accesos ya estan listos')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_intro_appearance">Texto principal Email</label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_email_builder_intro_appearance" name="woo_otec_moodle_email_builder_intro"><?php echo esc_textarea((string) $core->get_option('email_builder_intro', '')); ?></textarea></td>
                                </tr>
                            </table>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="store">
                            <h4>Tienda, Carrito y Checkout</h4>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_intro_text">Texto pagina Tienda</label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_shop_intro_text" name="woo_otec_moodle_shop_intro_text"><?php echo esc_textarea((string) $core->get_option('shop_intro_text', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_intro_text">Texto pagina Carrito</label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_cart_intro_text" name="woo_otec_moodle_cart_intro_text"><?php echo esc_textarea((string) $core->get_option('cart_intro_text', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_intro_text">Texto pagina Checkout</label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_checkout_intro_text" name="woo_otec_moodle_checkout_intro_text"><?php echo esc_textarea((string) $core->get_option('checkout_intro_text', '')); ?></textarea></td>
                                </tr>
                            </table>
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


