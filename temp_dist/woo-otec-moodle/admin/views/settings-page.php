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
$last_sync_label = !empty($last_sync['timestamp']) ? (string) $last_sync['timestamp'] : esc_html__('No runs yet', 'woo-otec-moodle');
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
    ? esc_html__('Completed', 'woo-otec-moodle')
    : ($sync_status === 'warning' || $sync_status === 'error' ? esc_html__('With issues', 'woo-otec-moodle') : esc_html__('Not started', 'woo-otec-moodle'));
$sync_resume = sprintf(
    /* translators: 1: created products, 2: updated products */
    esc_html__('Created: %1$d | Updated: %2$d', 'woo-otec-moodle'),
    (int) ($last_sync['products_created'] ?? 0),
    (int) ($last_sync['products_updated'] ?? 0)
);
$status_cards = array(
    array(
        'label' => esc_html__('Connection status', 'woo-otec-moodle'),
        'value' => $route_connection_ok ? esc_html__('OK', 'woo-otec-moodle') : esc_html__('Pending', 'woo-otec-moodle'),
        'class' => $route_connection_ok ? 'is-success' : 'is-danger',
        'ok'    => $route_connection_ok,
    ),
    array(
        'label' => esc_html__('Last synchronization', 'woo-otec-moodle'),
        'value' => $last_sync_label,
        'class' => $route_sync_ok ? 'is-success' : 'is-warning',
        'ok'    => $route_sync_ok,
    ),
    array(
        'label' => esc_html__('Email', 'woo-otec-moodle'),
        'value' => $route_email_ok ? esc_html__('Configured', 'woo-otec-moodle') : esc_html__('Pending', 'woo-otec-moodle'),
        'class' => $route_email_ok ? 'is-success' : 'is-warning',
        'ok'    => $route_email_ok,
    ),
    array(
        'label' => esc_html__('Student portal', 'woo-otec-moodle'),
        'value' => $sso_enabled ? esc_html__('Active', 'woo-otec-moodle') : esc_html__('Disabled', 'woo-otec-moodle'),
        'class' => $sso_enabled ? 'is-success' : 'is-warning',
        'ok'    => $sso_enabled,
    ),
);
$tabs = array(
    'inicio'   => esc_html__('Home', 'woo-otec-moodle'),
    'general'  => esc_html__('Connect Moodle', 'woo-otec-moodle'),
    'sync'     => esc_html__('Synchronization', 'woo-otec-moodle'),
    'sso'      => esc_html__('Student login access', 'woo-otec-moodle'),
    'templates'=> esc_html__('Moodle metadata', 'woo-otec-moodle'),
    'appearance'=> esc_html__('Appearance and colors', 'woo-otec-moodle'),
    'emails'   => esc_html__('Email settings', 'woo-otec-moodle'),
    'logs'     => esc_html__('Error log', 'woo-otec-moodle'),
);
?>
<div class="wrap pcc-admin-wrap">
    <div class="pcc-brand-bar">
        <div class="pcc-brand-bar__main">
            <span class="pcc-brand-bar__logo-wrap">
                <img class="pcc-brand-bar__logo" src="<?php echo esc_url($brand_logo_url); ?>" alt="<?php echo esc_attr__('PCCurico', 'woo-otec-moodle'); ?>">
            </span>
            <div>
                <h1><?php echo esc_html__('WooCommerce to Moodle Synchronizer', 'woo-otec-moodle'); ?></h1>
            </div>
        </div>
        <div class="pcc-brand-bar__meta">
            <span>www.pccurico.cl</span>
            <p><?php echo esc_html__('developed by JCares', 'woo-otec-moodle'); ?></p>
        </div>
    </div>

    <?php if ($active_tab === 'sync') : ?>
    <div class="pcc-sync-result-box" style="margin-top: 0;">
        <div class="pcc-sync-result-header">
            <h3 style="margin:0;"><?php echo esc_html__('Recommended path to get started', 'woo-otec-moodle'); ?></h3>
            <span class="pcc-badge is-info"><?php echo esc_html__('Quick guide', 'woo-otec-moodle'); ?></span>
        </div>
        <div class="pcc-sync-result-grid">
            <?php
            $route_steps = array(
                array('step' => esc_html__('Step 1', 'woo-otec-moodle'), 'label' => esc_html__('Connect Moodle', 'woo-otec-moodle'), 'done' => $route_connection_ok),
                array('step' => esc_html__('Step 2', 'woo-otec-moodle'), 'label' => esc_html__('Synchronize courses', 'woo-otec-moodle'), 'done' => $route_sync_ok),
                array('step' => esc_html__('Step 3', 'woo-otec-moodle'), 'label' => esc_html__('Test email', 'woo-otec-moodle'), 'done' => $route_email_ok),
                array('step' => esc_html__('Step 4', 'woo-otec-moodle'), 'label' => esc_html__('Enable sales', 'woo-otec-moodle'), 'done' => $route_sales_ok),
            );
            ?>
            <?php foreach ($route_steps as $route_step) : ?>
                <div class="pcc-sync-result-item <?php echo !empty($route_step['done']) ? 'is-done' : 'is-pending'; ?>">
                    <span><?php echo esc_html((string) $route_step['step']); ?></span>
                    <strong><?php echo esc_html((string) $route_step['label']); ?></strong>
                    <p><?php echo !empty($route_step['done']) ? esc_html__('Status: OK', 'woo-otec-moodle') : esc_html__('Status: Pending', 'woo-otec-moodle'); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <div class="pcc-sync-result-message" style="margin-top: 12px; padding: 10px; background: #f2f8fd; border-radius: 8px; border: 1px solid #d7e6f1; font-size: 13px;">
            <?php echo wp_kses_post(__('Recommendation: if this is your first time, use the <strong>Wizard</strong> first and then review each tab for fine adjustments.', 'woo-otec-moodle')); ?>
        </div>
    </div>
    <?php endif; ?>


    <?php if ($status !== '') : ?>
        <div class="notice notice-<?php echo $status === 'success' ? 'success' : 'warning'; ?> is-dismissible">
            <p>
                <?php
                echo esc_html(
                    $status === 'success'
                        ? esc_html__('Manual synchronization finished successfully.', 'woo-otec-moodle')
                        : esc_html__('Manual synchronization finished with issues. Review the logs in the Synchronization tab.', 'woo-otec-moodle')
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
                <div class="pcc-tabs" role="tablist" aria-label="<?php echo esc_attr__('Configuration sections', 'woo-otec-moodle'); ?>">
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
                                <h2><?php echo esc_html__('Home', 'woo-otec-moodle'); ?></h2>
                            </div>
                            <div class="pcc-home-hero__actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-otec-asistente')); ?>" class="button button-primary pcc-button-compact"><?php echo esc_html__('Start configuration wizard', 'woo-otec-moodle'); ?></a>
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
                        <h3><?php echo esc_html__('Moodle connection', 'woo-otec-moodle'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_moodle_url"><?php echo esc_html__('Moodle URL', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="regular-text" type="url" id="woo_otec_moodle_moodle_url" name="woo_otec_moodle_moodle_url" value="<?php echo esc_attr((string) $core->get_option('moodle_url', '')); ?>">
                                    <p class="pcc-field-help"><?php echo esc_html__('Example: `https://campus.yourdomain.com`.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_moodle_token"><?php echo esc_html__('Moodle token', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="regular-text" type="password" id="woo_otec_moodle_moodle_token" name="woo_otec_moodle_moodle_token" value="<?php echo esc_attr((string) $core->get_option('moodle_token', '')); ?>" autocomplete="off">
                                    <p class="pcc-field-help"><?php echo esc_html__('Web service token with permissions for users, courses, categories, and enrollments.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_moodle_whatsapp_phone"><?php echo esc_html__('Support WhatsApp', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_moodle_whatsapp_phone" name="woo_otec_moodle_moodle_whatsapp_phone" value="<?php echo esc_attr((string) $core->get_option('moodle_whatsapp_phone', '')); ?>" placeholder="+56912345678">
                                    <p class="pcc-field-help"><?php echo esc_html__('Phone number for the conversion button on course pages.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_student_role_id"><?php echo esc_html__('Student role ID', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="small-text" type="number" id="woo_otec_moodle_student_role_id" name="woo_otec_moodle_student_role_id" value="<?php echo esc_attr((string) $core->get_option('student_role_id', 5)); ?>">
                                    <p class="pcc-field-help"><?php echo esc_html__('Usually `5`, but it depends on your Moodle setup.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Default image', 'woo-otec-moodle'); ?></th>
                                <td>
                                    <input type="hidden" id="woo_otec_moodle_default_image_id" name="woo_otec_moodle_default_image_id" value="<?php echo esc_attr((string) $default_image_id); ?>">
                                    <button type="button" class="button pcc-media-picker" data-target="#woo_otec_moodle_default_image_id" data-preview="#pcc-default-image-preview"><?php echo esc_html__('Select image', 'woo-otec-moodle'); ?></button>
                                    <p class="pcc-field-help"><?php echo esc_html__('Used when the course does not include an image from Moodle.', 'woo-otec-moodle'); ?></p>
                                    <div class="pcc-image-preview-wrap">
                                        <img id="pcc-default-image-preview" src="<?php echo esc_url($default_image_url); ?>" alt="" class="pcc-image-preview<?php echo $default_image_url === '' ? ' is-hidden' : ''; ?>">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_default_price"><?php echo esc_html__('Default price', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_default_price" name="woo_otec_moodle_default_price" value="<?php echo esc_attr((string) $core->get_option('default_price', '49000')); ?>">
                                    <p class="pcc-field-help"><?php echo esc_html__('Amount without currency symbols or separators, for example `49000`.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_default_instructor"><?php echo esc_html__('Default instructor', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_default_instructor" name="woo_otec_moodle_default_instructor" value="<?php echo esc_attr((string) $core->get_option('default_instructor', __('Not assigned', 'woo-otec-moodle'))); ?>">
                                    <p class="pcc-field-help"><?php echo esc_html__('Fallback text when Moodle does not provide an instructor.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_fallback_description"><?php echo esc_html__('Fallback description', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <textarea class="large-text" rows="4" id="woo_otec_moodle_fallback_description" name="woo_otec_moodle_fallback_description"><?php echo esc_textarea((string) $core->get_option('fallback_description', '')); ?></textarea>
                                    <p class="pcc-field-help"><?php echo esc_html__('Description used when creating or updating products without enough content.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Export configuration', 'woo-otec-moodle'); ?></th>
                                <td>
                                    <button type="button" class="button button-secondary" id="pcc-export-config"><?php echo esc_html__('Download current configuration', 'woo-otec-moodle'); ?></button>
                                    <p class="pcc-field-help"><?php echo esc_html__('Download a JSON file with the current plugin configuration, including Moodle connection, parameters, appearance, email, mappings, and active fields.', 'woo-otec-moodle'); ?></p>
                                    <div id="pcc-config-export-result"></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Import configuration', 'woo-otec-moodle'); ?></th>
                                <td>
                                    <input type="file" id="pcc-import-config-file" accept=".json,application/json">
                                    <button type="button" class="button button-secondary" id="pcc-import-config"><?php echo esc_html__('Import configuration', 'woo-otec-moodle'); ?></button>
                                    <p class="pcc-field-help"><?php echo esc_html__('Upload a JSON file exported from this plugin to restore Moodle connection, parameters, appearance, email, mappings, and active fields.', 'woo-otec-moodle'); ?></p>
                                    <div id="pcc-config-import-result"></div>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <!-- Sync Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'sync' ? 'is-active' : ''; ?>" id="pcc-panel-sync" data-panel="sync" role="tabpanel">
                        <div class="pcc-sync-header">
                            <h3><?php echo esc_html__('Synchronization wizard', 'woo-otec-moodle'); ?></h3>
                        </div>

                        <div class="pcc-wizard-steps">
                            <div class="pcc-step is-active" data-step="1">
                                <span class="pcc-step-num">1</span>
                                <span class="pcc-step-label"><?php echo esc_html__('Categories', 'woo-otec-moodle'); ?></span>
                            </div>
                            <div class="pcc-step" data-step="2">
                                <span class="pcc-step-num">2</span>
                                <span class="pcc-step-label"><?php echo esc_html__('Teachers', 'woo-otec-moodle'); ?></span>
                            </div>
                            <div class="pcc-step" data-step="3">
                                <span class="pcc-step-num">3</span>
                                <span class="pcc-step-label"><?php echo esc_html__('Courses', 'woo-otec-moodle'); ?></span>
                            </div>
                            <div class="pcc-step" data-step="4">
                                <span class="pcc-step-num">4</span>
                                <span class="pcc-step-label"><?php echo esc_html__('Confirmation', 'woo-otec-moodle'); ?></span>
                            </div>
                        </div>

                        <!-- Step 1: Categories -->
                        <div class="pcc-wizard-content is-active" id="pcc-step-1-content">
                            <h3><?php echo esc_html__('Step 1: Category selection', 'woo-otec-moodle'); ?></h3>
                            <p><?php echo esc_html__('Loading categories from Moodle. Please select the ones you want to import.', 'woo-otec-moodle'); ?></p>
                            <div id="pcc-categories-list-container">
                                <div class="pcc-loading-spinner"><?php echo esc_html__('Loading categories...', 'woo-otec-moodle'); ?></div>
                            </div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-primary" id="pcc-btn-next-1"><?php echo esc_html__('Confirm and continue', 'woo-otec-moodle'); ?></button>
                            </div>
                        </div>

                        <!-- Step 2: Teachers -->
                        <div class="pcc-wizard-content" id="pcc-step-2-content" style="display:none;">
                            <h3><?php echo esc_html__('Step 2: Detected teachers', 'woo-otec-moodle'); ?></h3>
                            <p><?php echo esc_html__('These are the teachers associated with the courses in the selected categories. You can verify their names before proceeding.', 'woo-otec-moodle'); ?></p>
                            <div id="pcc-teachers-list-container">
                                <div class="pcc-loading-spinner"><?php echo esc_html__('Loading teachers...', 'woo-otec-moodle'); ?></div>
                            </div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-secondary" id="pcc-btn-prev-2"><?php echo esc_html__('Back', 'woo-otec-moodle'); ?></button>
                                <button type="button" class="button button-primary" id="pcc-btn-next-2"><?php echo esc_html__('Confirm teachers', 'woo-otec-moodle'); ?></button>
                            </div>
                        </div>

                        <!-- Step 3: Courses -->
                        <div class="pcc-wizard-content" id="pcc-step-3-content" style="display:none;">
                            <h3><?php echo esc_html__('Step 3: Course configuration', 'woo-otec-moodle'); ?></h3>
                            <p><?php echo esc_html__('Edit the course information before importing.', 'woo-otec-moodle'); ?></p>
                            <div id="pcc-courses-table-container" style="overflow-x: auto;"></div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-secondary" id="pcc-btn-prev-3"><?php echo esc_html__('Back', 'woo-otec-moodle'); ?></button>
                                <button type="button" class="button button-primary" id="pcc-btn-next-3"><?php echo esc_html__('Next step', 'woo-otec-moodle'); ?></button>
                            </div>
                        </div>

                        <!-- Step 4: Confirmation -->
                        <div class="pcc-wizard-content" id="pcc-step-4-content" style="display:none;">
                            <h3><?php echo esc_html__('Step 4: Summary and confirmation', 'woo-otec-moodle'); ?></h3>
                            <div id="pcc-sync-summary-container"></div>
                            <div class="pcc-progress" style="display:none; margin: 20px 0;">
                                <div class="pcc-progress-bar" style="width:0%">0%</div>
                            </div>
                            <div class="pcc-wizard-actions">
                                <button type="button" class="button button-secondary" id="pcc-btn-prev-4"><?php echo esc_html__('Back', 'woo-otec-moodle'); ?></button>
                                <button type="button" class="button button-primary" id="pcc-btn-execute-sync"><?php echo esc_html__('Run synchronization', 'woo-otec-moodle'); ?></button>
                            </div>
                        </div>

                        <details class="pcc-log-details" style="margin-top: 20px;">
                            <summary style="cursor: pointer; font-weight: 600; color: var(--pcc-primary);"><?php echo esc_html__('View technical history (logs)', 'woo-otec-moodle'); ?></summary>
                            <pre class="pcc-log-view" style="margin-top: 10px; max-height: 200px; overflow-y: auto;"><?php echo esc_html(implode("\n", $sync_log)); ?></pre>
                        </details>
                    </section>

                    <!-- SSO Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'sso' ? 'is-active' : ''; ?>" id="pcc-panel-sso" data-panel="sso" role="tabpanel">
                        <h3><?php echo esc_html__('Automatic access (SSO)', 'woo-otec-moodle'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php echo esc_html__('SSO status', 'woo-otec-moodle'); ?></th>
                                <td>
                                    <input type="checkbox" id="woo_otec_moodle_sso_enabled" name="woo_otec_moodle_sso_enabled" value="yes" <?php checked($core->get_option('sso_enabled', 'yes'), 'yes'); ?>>
                                    <label for="woo_otec_moodle_sso_enabled"><?php echo esc_html__('Enable SSO', 'woo-otec-moodle'); ?></label>
                                    <p class="pcc-field-help"><?php echo esc_html__('Allows direct access to the classroom without an additional manual login.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_sso_base_url"><?php echo esc_html__('SSO base URL', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="regular-text" type="url" id="woo_otec_moodle_sso_base_url" name="woo_otec_moodle_sso_base_url" value="<?php echo esc_attr((string) $core->get_option('sso_base_url', '')); ?>">
                                    <p class="pcc-field-help"><?php echo esc_html__('Example: `https://campus.yourdomain.com/auth/userkey/login.php`.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_redirect_after_purchase"><?php echo esc_html__('Redirect after purchase', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input type="checkbox" id="woo_otec_moodle_redirect_after_purchase" name="woo_otec_moodle_redirect_after_purchase" value="yes" <?php checked($core->get_option('redirect_after_purchase', 'no'), 'yes'); ?>>
                                    <p class="pcc-field-help"><?php echo esc_html__('Send the student directly to the virtual classroom after checkout.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Test', 'woo-otec-moodle'); ?></th>
                                <td>
                                    <button type="button" class="button button-secondary" id="pcc-test-sso-connection"><?php echo esc_html__('Test connection', 'woo-otec-moodle'); ?></button>
                                    <span id="sso-test-result" style="margin-left: 10px;"></span>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <!-- Templates Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'templates' ? 'is-active' : ''; ?>" id="pcc-panel-templates" data-panel="templates" role="tabpanel">
                        <h3><?php echo esc_html__('Metadata and mapping', 'woo-otec-moodle'); ?></h3>
                        <?php
                        $reference_id = (int) $core->get_option('template_reference', 0);
                        $reference_products = Woo_OTEC_Moodle_Settings::instance()->get_template_reference_products();
                        $selected_fields = (array) $core->get_option('template_fields', array());
                        ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_template_reference"><?php echo esc_html__('Example course', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <select id="woo_otec_moodle_template_reference" name="woo_otec_moodle_template_reference">
                                        <option value=""><?php echo esc_html__('Select a synchronized course', 'woo-otec-moodle'); ?></option>
                                        <?php foreach ($reference_products as $product) : ?>
                                            <option value="<?php echo esc_attr((string) $product->get_id()); ?>" <?php selected($reference_id, $product->get_id()); ?>>
                                                <?php echo esc_html($product->get_name()); ?> (#<?php echo esc_html((string) $product->get_id()); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Fields to display', 'woo-otec-moodle'); ?></th>
                                <td>
                                    <p class="pcc-field-help"><?php echo esc_html__('Select the data you want to show in the course card. Labels already use clearer language for the final user.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr>
                        <h3><?php echo esc_html__('Available course data', 'woo-otec-moodle'); ?></h3>
                        <p class="description"><?php echo esc_html__('Enable only the information you want to display. If the course has a synchronized cover image from Moodle, you will also see it visually here.', 'woo-otec-moodle'); ?></p>
                        <div data-template-fields>
                            <?php
                            if ($reference_id > 0) {
                                Woo_OTEC_Moodle_Settings::instance()->render_template_fields_markup($reference_id, $selected_fields, true);
                            } else {
                                echo '<p class="description">' . esc_html__('Select a course to list the available metadata.', 'woo-otec-moodle') . '</p>';
                            }
                            ?>
                        </div>
                        <p class="description pcc-template-preview-description"><?php echo esc_html__('The preview updates according to the selected example course and the fields you keep active.', 'woo-otec-moodle'); ?></p>
                        <div class="pcc-template-preview-card" style="margin-top: 16px;">
                            <h4><?php echo esc_html__('Live preview', 'woo-otec-moodle'); ?></h4>
                            <div data-template-live-preview>
                                <p class="description"><?php echo esc_html__('Select a course and enable the fields you want to display to preview the card in real time.', 'woo-otec-moodle'); ?></p>
                            </div>
                        </div>
                    </section>

                    <!-- Emails Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'emails' ? 'is-active' : ''; ?>" id="pcc-panel-emails" data-panel="emails" role="tabpanel">
                        <h3><?php echo esc_html__('Email delivery and tests', 'woo-otec-moodle'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_enabled"><?php echo esc_html__('Enable sending', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input type="checkbox" id="woo_otec_moodle_email_enabled" name="woo_otec_moodle_email_enabled" value="yes" <?php checked($core->get_option('email_enabled', 'yes'), 'yes'); ?>>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_from_address"><?php echo esc_html__('Sender email', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="regular-text" type="email" id="woo_otec_moodle_email_from_address" name="woo_otec_moodle_email_from_address" value="<?php echo esc_attr($email_from_address); ?>" placeholder="<?php echo esc_attr($fallback_from_address); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_from_name"><?php echo esc_html__('Sender name', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_email_from_name" name="woo_otec_moodle_email_from_name" value="<?php echo esc_attr((string) $core->get_option('email_from_name', '')); ?>" placeholder="<?php echo esc_attr(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="woo_otec_moodle_email_subject"><?php echo esc_html__('Subject', 'woo-otec-moodle'); ?></label></th>
                                <td>
                                    <input class="regular-text" type="text" id="woo_otec_moodle_email_subject" name="woo_otec_moodle_email_subject" value="<?php echo esc_attr((string) $core->get_option('email_subject', '')); ?>">
                                    <p class="pcc-field-help"><?php echo esc_html__('The visual email appearance is now edited from the Appearance tab.', 'woo-otec-moodle'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <div class="pcc-email-tools" style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
                            <button type="button" class="button" data-email-preview><?php echo esc_html__('Preview', 'woo-otec-moodle'); ?></button>
                            <input type="email" id="woo_otec_moodle_email_test_recipient" class="regular-text" placeholder="<?php echo esc_attr__('Email for test delivery', 'woo-otec-moodle'); ?>" style="max-width: 250px;">
                            <button type="button" class="button button-secondary" data-email-send-test><?php echo esc_html__('Send test', 'woo-otec-moodle'); ?></button>
                        </div>
                        <div class="pcc-email-feedback" data-email-feedback></div>
                        <div class="pcc-email-preview" data-email-preview-box></div>
                    </section>

                    <!-- Appearance Tab -->
                    <section class="pcc-tab-panel <?php echo $active_tab === 'appearance' ? 'is-active' : ''; ?>" id="pcc-panel-appearance" data-panel="appearance" role="tabpanel">
                        <h3><?php echo esc_html__('Visual builder', 'woo-otec-moodle'); ?></h3>
                        <p class="pcc-field-help"><?php echo esc_html__('Configure each template separately. Changes apply only to plugin areas to avoid affecting the rest of the site.', 'woo-otec-moodle'); ?></p>
                        <?php $appearance_profile = (string) $core->get_option('appearance_profile', 'product'); ?>
                        <input type="hidden" id="woo_otec_moodle_appearance_profile" name="woo_otec_moodle_appearance_profile" value="<?php echo esc_attr($appearance_profile); ?>">
                        <div class="pcc-appearance-nav">
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'product' ? 'is-active' : ''; ?>" data-appearance-tab="product">Single Product</button>
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'shop' ? 'is-active' : ''; ?>" data-appearance-tab="shop"><?php echo esc_html__('Shop', 'woo-otec-moodle'); ?></button>
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'cart' ? 'is-active' : ''; ?>" data-appearance-tab="cart"><?php echo esc_html__('Cart', 'woo-otec-moodle'); ?></button>
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'checkout' ? 'is-active' : ''; ?>" data-appearance-tab="checkout">Checkout</button>
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'portal' ? 'is-active' : ''; ?>" data-appearance-tab="portal"><?php echo esc_html__('Portal', 'woo-otec-moodle'); ?></button>
                            <button type="button" class="pcc-appearance-tab <?php echo $appearance_profile === 'email' ? 'is-active' : ''; ?>" data-appearance-tab="email">Email</button>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="product">
                            <?php
                            $appearance_preview_id = $reference_id;
                            if ($appearance_preview_id <= 0 && !empty($reference_products)) {
                                $appearance_preview_id = (int) $reference_products[0]->get_id();
                            }
                            $appearance_preview_product = $appearance_preview_id > 0 ? wc_get_product($appearance_preview_id) : null;
                            $appearance_preview_name = $appearance_preview_product instanceof WC_Product ? $appearance_preview_product->get_name() : __('Example synchronized course', 'woo-otec-moodle');
                            $appearance_preview_short = $appearance_preview_product instanceof WC_Product ? wp_trim_words(wp_strip_all_tags((string) $appearance_preview_product->get_short_description()), 18) : __('This is how the main course block will look with your current palette.', 'woo-otec-moodle');
                            $appearance_preview_price = $appearance_preview_product instanceof WC_Product ? wp_strip_all_tags((string) $appearance_preview_product->get_price_html()) : '$49.000';
                            $appearance_preview_image = $appearance_preview_product instanceof WC_Product ? (get_the_post_thumbnail_url($appearance_preview_product->get_id(), 'medium') ?: '') : '';
                            $appearance_preview_modality = $appearance_preview_product instanceof WC_Product ? (string) get_post_meta($appearance_preview_product->get_id(), '_modality', true) : __('Modality not provided', 'woo-otec-moodle');
                            $appearance_preview_teacher = $appearance_preview_product instanceof WC_Product ? (string) get_post_meta($appearance_preview_product->get_id(), '_instructor', true) : __('Instructor not provided', 'woo-otec-moodle');
                            ?>
                            <div class="pcc-appearance-head">
                                <h4>Single Product</h4>
                                <p><?php echo esc_html__('Control the course card, the main button text, and the description section.', 'woo-otec-moodle'); ?></p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_template_style"><?php echo esc_html__('Template style', 'woo-otec-moodle'); ?></label></th>
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
                                    <th scope="row"><label for="pcc-appearance-preview-product"><?php echo esc_html__('Preview course', 'woo-otec-moodle'); ?></label></th>
                                    <td>
                                        <select id="pcc-appearance-preview-product">
                                            <option value=""><?php echo esc_html__('Select a synchronized course', 'woo-otec-moodle'); ?></option>
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
                                                    data-short="<?php echo esc_attr($preview_short !== '' ? $preview_short : __('No short summary available.', 'woo-otec-moodle')); ?>"
                                                    data-price="<?php echo esc_attr($preview_price !== '' ? $preview_price : __('No price available', 'woo-otec-moodle')); ?>"
                                                    data-image="<?php echo esc_url($preview_image); ?>"
                                                    data-modality="<?php echo esc_attr($preview_modality !== '' ? $preview_modality : __('Modality not provided', 'woo-otec-moodle')); ?>"
                                                    data-teacher="<?php echo esc_attr($preview_teacher !== '' ? $preview_teacher : __('Instructor not provided', 'woo-otec-moodle')); ?>"
                                                    <?php selected($appearance_preview_id, $preview_product->get_id()); ?>
                                                >
                                                    <?php echo esc_html($preview_name); ?> (#<?php echo esc_html((string) $preview_product->get_id()); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="pcc-field-help"><?php echo esc_html__('Use a real course to preview the template with actual information.', 'woo-otec-moodle'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_single_button_text"><?php echo esc_html__('Buy button text', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_single_button_text" name="woo_otec_moodle_single_button_text" value="<?php echo esc_attr((string) $core->get_option('single_button_text', __('Buy course', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_single_button_color"><?php echo esc_html__('Buy button color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_single_button_color" name="woo_otec_moodle_single_button_color" value="<?php echo esc_attr((string) $core->get_option('single_button_color', '#1f9d6f')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_single_description_heading"><?php echo esc_html__('Description title', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_single_description_heading" name="woo_otec_moodle_single_description_heading" value="<?php echo esc_attr((string) $core->get_option('single_description_heading', __('Course description', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_pcc_color_primary"><?php echo esc_html__('Primary color', 'woo-otec-moodle'); ?></label></th>
                                    <td>
                                        <input type="color" id="woo_otec_moodle_pcc_color_primary" name="woo_otec_moodle_pcc_color_primary" value="<?php echo esc_attr((string) $core->get_option('pcc_color_primary', '#023E25')); ?>" class="regular-text">
                                        <p class="pcc-field-help"><?php echo esc_html__('Used in buttons, icons, and headings.', 'woo-otec-moodle'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_pcc_color_secondary"><?php echo esc_html__('Secondary color', 'woo-otec-moodle'); ?></label></th>
                                    <td>
                                        <input type="color" id="woo_otec_moodle_pcc_color_secondary" name="woo_otec_moodle_pcc_color_secondary" value="<?php echo esc_attr((string) $core->get_option('pcc_color_secondary', '#6EC1E4')); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_pcc_color_text"><?php echo esc_html__('Text color', 'woo-otec-moodle'); ?></label></th>
                                    <td>
                                        <input type="color" id="woo_otec_moodle_pcc_color_text" name="woo_otec_moodle_pcc_color_text" value="<?php echo esc_attr((string) $core->get_option('pcc_color_text', '#7A7A7A')); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_pcc_color_accent"><?php echo esc_html__('Accent color', 'woo-otec-moodle'); ?></label></th>
                                    <td>
                                        <input type="color" id="woo_otec_moodle_pcc_color_accent" name="woo_otec_moodle_pcc_color_accent" value="<?php echo esc_attr((string) $core->get_option('pcc_color_accent', '#61CE70')); ?>" class="regular-text">
                                    </td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-card pcc-appearance-mini-card--<?php echo esc_attr((string) $core->get_option('template_style', 'classic')); ?>" data-appearance-single-preview>
                                    <span class="pcc-appearance-mini-kicker"><?php echo esc_html__('Quick preview', 'woo-otec-moodle'); ?></span>
                                    <div class="pcc-appearance-mini-card__media<?php echo $appearance_preview_image === '' ? ' is-empty' : ''; ?>">
                                        <?php if ($appearance_preview_image !== '') : ?>
                                            <img src="<?php echo esc_url($appearance_preview_image); ?>" alt="<?php echo esc_attr($appearance_preview_name); ?>" data-preview-image>
                                        <?php else : ?>
                                            <span data-preview-image-placeholder><?php echo esc_html__('No cover image', 'woo-otec-moodle'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h5 style="color: <?php echo esc_attr((string) $core->get_option('pcc_color_primary', '#023E25')); ?>;" data-preview-name><?php echo esc_html($appearance_preview_name); ?></h5>
                                    <p style="color: <?php echo esc_attr((string) $core->get_option('pcc_color_text', '#7A7A7A')); ?>;"><?php echo esc_html__('This is how the main course block will look with your current palette.', 'woo-otec-moodle'); ?></p>
                                    <div class="pcc-appearance-mini-meta">
                                        <span data-preview-modality><?php echo esc_html($appearance_preview_modality); ?></span>
                                        <span data-preview-teacher><?php echo esc_html($appearance_preview_teacher); ?></span>
                                    </div>
                                    <strong class="pcc-appearance-mini-price" style="color: <?php echo esc_attr((string) $core->get_option('pcc_color_primary', '#023E25')); ?>;" data-preview-price><?php echo esc_html($appearance_preview_price); ?></strong>
                                    <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('single_button_color', '#1f9d6f')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('single_button_color', '#1f9d6f')); ?>;">
                                        <?php echo esc_html((string) $core->get_option('single_button_text', __('Buy course', 'woo-otec-moodle'))); ?>
                                    </button>
                                    <div class="pcc-appearance-mini-description">
                                        <span data-preview-description-heading><?php echo esc_html((string) $core->get_option('single_description_heading', __('Course description', 'woo-otec-moodle'))); ?></span>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="shop">
                            <div class="pcc-appearance-head">
                                <h4><?php echo esc_html__('Shop', 'woo-otec-moodle'); ?></h4>
                                <p><?php echo esc_html__('Edit the top block and the main button color for the catalog.', 'woo-otec-moodle'); ?></p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_intro_title"><?php echo esc_html__('Block title', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_shop_intro_title" name="woo_otec_moodle_shop_intro_title" value="<?php echo esc_attr((string) $core->get_option('shop_intro_title', __('Explore our course catalog', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_intro_text"><?php echo esc_html__('Supporting text', 'woo-otec-moodle'); ?></label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_shop_intro_text" name="woo_otec_moodle_shop_intro_text"><?php echo esc_textarea((string) $core->get_option('shop_intro_text', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_color_bg"><?php echo esc_html__('Background color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_shop_color_bg" name="woo_otec_moodle_shop_color_bg" value="<?php echo esc_attr((string) $core->get_option('shop_color_bg', '#f8fbff')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_color_title"><?php echo esc_html__('Title color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_shop_color_title" name="woo_otec_moodle_shop_color_title" value="<?php echo esc_attr((string) $core->get_option('shop_color_title', '#21405a')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_color_text"><?php echo esc_html__('Text color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_shop_color_text" name="woo_otec_moodle_shop_color_text" value="<?php echo esc_attr((string) $core->get_option('shop_color_text', '#2b4b63')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_button_text"><?php echo esc_html__('Button text', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_shop_button_text" name="woo_otec_moodle_shop_button_text" value="<?php echo esc_attr((string) $core->get_option('shop_button_text', __('View course', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_shop_color_button"><?php echo esc_html__('Button color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_shop_color_button" name="woo_otec_moodle_shop_color_button" value="<?php echo esc_attr((string) $core->get_option('shop_color_button', '#0f3d5e')); ?>"></td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-banner" data-appearance-shop-preview style="background: <?php echo esc_attr((string) $core->get_option('shop_color_bg', '#f8fbff')); ?>;">
                                    <h5 style="color: <?php echo esc_attr((string) $core->get_option('shop_color_title', '#21405a')); ?>;" data-preview-title><?php echo esc_html((string) $core->get_option('shop_intro_title', __('Explore our course catalog', 'woo-otec-moodle'))); ?></h5>
                                    <p style="color: <?php echo esc_attr((string) $core->get_option('shop_color_text', '#2b4b63')); ?>;" data-preview-text><?php echo esc_html((string) $core->get_option('shop_intro_text', __('Discover our available courses and choose the one that best fits your goal.', 'woo-otec-moodle'))); ?></p>
                                    <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('shop_color_button', '#0f3d5e')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('shop_color_button', '#0f3d5e')); ?>;">
                                        <span data-preview-button><?php echo esc_html((string) $core->get_option('shop_button_text', __('View course', 'woo-otec-moodle'))); ?></span>
                                    </button>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="cart">
                            <div class="pcc-appearance-head">
                                <h4><?php echo esc_html__('Cart', 'woo-otec-moodle'); ?></h4>
                                <p><?php echo esc_html__('Define the top message and the button color used to continue to checkout.', 'woo-otec-moodle'); ?></p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_intro_title"><?php echo esc_html__('Block title', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_cart_intro_title" name="woo_otec_moodle_cart_intro_title" value="<?php echo esc_attr((string) $core->get_option('cart_intro_title', __('Your training cart', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_intro_text"><?php echo esc_html__('Supporting text', 'woo-otec-moodle'); ?></label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_cart_intro_text" name="woo_otec_moodle_cart_intro_text"><?php echo esc_textarea((string) $core->get_option('cart_intro_text', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_color_bg"><?php echo esc_html__('Background color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_cart_color_bg" name="woo_otec_moodle_cart_color_bg" value="<?php echo esc_attr((string) $core->get_option('cart_color_bg', '#f5fbf8')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_color_title"><?php echo esc_html__('Title color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_cart_color_title" name="woo_otec_moodle_cart_color_title" value="<?php echo esc_attr((string) $core->get_option('cart_color_title', '#1d5a41')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_color_text"><?php echo esc_html__('Text color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_cart_color_text" name="woo_otec_moodle_cart_color_text" value="<?php echo esc_attr((string) $core->get_option('cart_color_text', '#355846')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_cart_color_button"><?php echo esc_html__('Button color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_cart_color_button" name="woo_otec_moodle_cart_color_button" value="<?php echo esc_attr((string) $core->get_option('cart_color_button', '#1f9d6f')); ?>"></td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-banner" data-appearance-cart-preview style="background: <?php echo esc_attr((string) $core->get_option('cart_color_bg', '#f5fbf8')); ?>;">
                                    <h5 style="color: <?php echo esc_attr((string) $core->get_option('cart_color_title', '#1d5a41')); ?>;" data-preview-title><?php echo esc_html((string) $core->get_option('cart_intro_title', __('Your training cart', 'woo-otec-moodle'))); ?></h5>
                                    <p style="color: <?php echo esc_attr((string) $core->get_option('cart_color_text', '#355846')); ?>;" data-preview-text><?php echo esc_html((string) $core->get_option('cart_intro_text', __('Review your courses before completing the payment.', 'woo-otec-moodle'))); ?></p>
                                    <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('cart_color_button', '#1f9d6f')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('cart_color_button', '#1f9d6f')); ?>;">
                                        <span data-preview-button><?php echo esc_html__('Continue to checkout', 'woo-otec-moodle'); ?></span>
                                    </button>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="checkout">
                            <div class="pcc-appearance-head">
                                <h4>Checkout</h4>
                                <p><?php echo esc_html__('Define the intro block and the final purchase button color.', 'woo-otec-moodle'); ?></p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_intro_title"><?php echo esc_html__('Block title', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_checkout_intro_title" name="woo_otec_moodle_checkout_intro_title" value="<?php echo esc_attr((string) $core->get_option('checkout_intro_title', __('Last step to activate your courses', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_intro_text"><?php echo esc_html__('Supporting text', 'woo-otec-moodle'); ?></label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_checkout_intro_text" name="woo_otec_moodle_checkout_intro_text"><?php echo esc_textarea((string) $core->get_option('checkout_intro_text', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_color_bg"><?php echo esc_html__('Background color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_checkout_color_bg" name="woo_otec_moodle_checkout_color_bg" value="<?php echo esc_attr((string) $core->get_option('checkout_color_bg', '#fff8f1')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_color_title"><?php echo esc_html__('Title color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_checkout_color_title" name="woo_otec_moodle_checkout_color_title" value="<?php echo esc_attr((string) $core->get_option('checkout_color_title', '#7b4b12')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_color_text"><?php echo esc_html__('Text color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_checkout_color_text" name="woo_otec_moodle_checkout_color_text" value="<?php echo esc_attr((string) $core->get_option('checkout_color_text', '#6f5a40')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_button_text"><?php echo esc_html__('Button text', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_checkout_button_text" name="woo_otec_moodle_checkout_button_text" value="<?php echo esc_attr((string) $core->get_option('checkout_button_text', __('Complete purchase', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_checkout_color_button"><?php echo esc_html__('Button color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_checkout_color_button" name="woo_otec_moodle_checkout_color_button" value="<?php echo esc_attr((string) $core->get_option('checkout_color_button', '#d9822b')); ?>"></td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-banner" data-appearance-checkout-preview style="background: <?php echo esc_attr((string) $core->get_option('checkout_color_bg', '#fff8f1')); ?>;">
                                    <h5 style="color: <?php echo esc_attr((string) $core->get_option('checkout_color_title', '#7b4b12')); ?>;" data-preview-title><?php echo esc_html((string) $core->get_option('checkout_intro_title', __('Last step to activate your courses', 'woo-otec-moodle'))); ?></h5>
                                    <p style="color: <?php echo esc_attr((string) $core->get_option('checkout_color_text', '#6f5a40')); ?>;" data-preview-text><?php echo esc_html((string) $core->get_option('checkout_intro_text', __('Complete your information to activate immediate access to your courses.', 'woo-otec-moodle'))); ?></p>
                                    <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('checkout_color_button', '#d9822b')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('checkout_color_button', '#d9822b')); ?>;">
                                        <span data-preview-button><?php echo esc_html((string) $core->get_option('checkout_button_text', __('Complete purchase', 'woo-otec-moodle'))); ?></span>
                                    </button>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="portal">
                            <div class="pcc-appearance-head">
                                <h4><?php echo esc_html__('Portal / My courses', 'woo-otec-moodle'); ?></h4>
                                <p><?php echo esc_html__('Configure the shortcode block and course access button independently.', 'woo-otec-moodle'); ?></p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_portal_title"><?php echo esc_html__('Portal title', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_portal_title" name="woo_otec_moodle_portal_title" value="<?php echo esc_attr((string) $core->get_option('portal_title', __('My courses', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_portal_intro_text"><?php echo esc_html__('Supporting text', 'woo-otec-moodle'); ?></label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_portal_intro_text" name="woo_otec_moodle_portal_intro_text"><?php echo esc_textarea((string) $core->get_option('portal_intro_text', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_portal_button_text"><?php echo esc_html__('Button text', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_portal_button_text" name="woo_otec_moodle_portal_button_text" value="<?php echo esc_attr((string) $core->get_option('portal_button_text', __('Enter course', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_portal_color_bg"><?php echo esc_html__('Background color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_portal_color_bg" name="woo_otec_moodle_portal_color_bg" value="<?php echo esc_attr((string) $core->get_option('portal_color_bg', '#f7fbff')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_portal_color_title"><?php echo esc_html__('Title color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_portal_color_title" name="woo_otec_moodle_portal_color_title" value="<?php echo esc_attr((string) $core->get_option('portal_color_title', '#173246')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_portal_color_text"><?php echo esc_html__('Text color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_portal_color_text" name="woo_otec_moodle_portal_color_text" value="<?php echo esc_attr((string) $core->get_option('portal_color_text', '#567187')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_portal_color_button"><?php echo esc_html__('Button color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_portal_color_button" name="woo_otec_moodle_portal_color_button" value="<?php echo esc_attr((string) $core->get_option('portal_color_button', '#0f3d5e')); ?>"></td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-banner" data-appearance-portal-preview style="background: <?php echo esc_attr((string) $core->get_option('portal_color_bg', '#f7fbff')); ?>;">
                                    <h5 style="color: <?php echo esc_attr((string) $core->get_option('portal_color_title', '#173246')); ?>;" data-preview-title><?php echo esc_html((string) $core->get_option('portal_title', __('My courses', 'woo-otec-moodle'))); ?></h5>
                                    <p style="color: <?php echo esc_attr((string) $core->get_option('portal_color_text', '#567187')); ?>;" data-preview-text><?php echo esc_html((string) $core->get_option('portal_intro_text', __('From here you can enter each purchased course directly.', 'woo-otec-moodle'))); ?></p>
                                    <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('portal_color_button', '#0f3d5e')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('portal_color_button', '#0f3d5e')); ?>;">
                                        <span data-preview-button><?php echo esc_html((string) $core->get_option('portal_button_text', __('Enter course', 'woo-otec-moodle'))); ?></span>
                                    </button>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="pcc-appearance-group" data-appearance-group="email">
                            <div class="pcc-appearance-head">
                                <h4>Email</h4>
                                <p><?php echo esc_html__('Edit the visual email content here without touching HTML.', 'woo-otec-moodle'); ?></p>
                            </div>
                            <div class="pcc-appearance-layout">
                            <div class="pcc-appearance-controls">
                            <table class="form-table pcc-form-table-compact">
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Friendly editor', 'woo-otec-moodle'); ?></th>
                                    <td>
                                        <input type="checkbox" id="woo_otec_moodle_email_builder_enabled" name="woo_otec_moodle_email_builder_enabled" value="yes" <?php checked($core->get_option('email_builder_enabled', 'yes'), 'yes'); ?>>
                                        <label for="woo_otec_moodle_email_builder_enabled"><?php echo esc_html__('Use the visual builder without HTML', 'woo-otec-moodle'); ?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_heading_appearance"><?php echo esc_html__('Main title', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_email_builder_heading_appearance" name="woo_otec_moodle_email_builder_heading" value="<?php echo esc_attr((string) $core->get_option('email_builder_heading', __('Your access details are ready', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_intro_appearance"><?php echo esc_html__('Main text', 'woo-otec-moodle'); ?></label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_email_builder_intro_appearance" name="woo_otec_moodle_email_builder_intro"><?php echo esc_textarea((string) $core->get_option('email_builder_intro', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_button_text_appearance"><?php echo esc_html__('Button text', 'woo-otec-moodle'); ?></label></th>
                                    <td><input class="regular-text" type="text" id="woo_otec_moodle_email_builder_button_text_appearance" name="woo_otec_moodle_email_builder_button_text" value="<?php echo esc_attr((string) $core->get_option('email_builder_button_text', __('Access my courses', 'woo-otec-moodle'))); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_builder_footer_appearance"><?php echo esc_html__('Closing text', 'woo-otec-moodle'); ?></label></th>
                                    <td><textarea class="large-text" rows="3" id="woo_otec_moodle_email_builder_footer_appearance" name="woo_otec_moodle_email_builder_footer"><?php echo esc_textarea((string) $core->get_option('email_builder_footer', '')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Email logo', 'woo-otec-moodle'); ?></th>
                                    <td>
                                        <?php
                                        $email_logo_id = (int) $core->get_option('email_logo_id', 0);
                                        $theme_logo_id = (int) get_theme_mod('custom_logo');
                                        $effective_logo_id = $email_logo_id > 0 ? $email_logo_id : $theme_logo_id;
                                        $email_logo_url = $effective_logo_id > 0 ? wp_get_attachment_image_url($effective_logo_id, 'medium') : '';
                                        ?>
                                        <input type="hidden" id="woo_otec_moodle_email_logo_id" name="woo_otec_moodle_email_logo_id" value="<?php echo esc_attr((string) $email_logo_id); ?>">
                                        <button type="button" class="button pcc-media-picker" data-target="#woo_otec_moodle_email_logo_id" data-preview="#pcc-email-logo-preview"><?php echo esc_html__('Select logo', 'woo-otec-moodle'); ?></button>
                                        <p class="pcc-field-help"><?php echo esc_html__('If you do not select one, the logo configured in your theme will be used automatically.', 'woo-otec-moodle'); ?></p>
                                        <div class="pcc-image-preview-wrap">
                                            <img id="pcc-email-logo-preview" src="<?php echo esc_url((string) $email_logo_url); ?>" alt="" class="pcc-image-preview<?php echo $email_logo_url === '' ? ' is-hidden' : ''; ?>">
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_color_primary_appearance"><?php echo esc_html__('Header color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_email_color_primary_appearance" name="woo_otec_moodle_email_color_primary" value="<?php echo esc_attr((string) $core->get_option('email_color_primary', '#0f3d5e')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_color_accent_appearance"><?php echo esc_html__('Button color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_email_color_accent_appearance" name="woo_otec_moodle_email_color_accent" value="<?php echo esc_attr((string) $core->get_option('email_color_accent', '#1f9d6f')); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="woo_otec_moodle_email_color_bg_appearance"><?php echo esc_html__('Background color', 'woo-otec-moodle'); ?></label></th>
                                    <td><input type="color" id="woo_otec_moodle_email_color_bg_appearance" name="woo_otec_moodle_email_color_bg" value="<?php echo esc_attr((string) $core->get_option('email_color_bg', '#f3f8fc')); ?>"></td>
                                </tr>
                            </table>
                            </div>
                            <div class="pcc-appearance-preview">
                                <div class="pcc-appearance-mini-email" data-appearance-email-preview style="background: <?php echo esc_attr((string) $core->get_option('email_color_bg', '#f3f8fc')); ?>;">
                                    <div class="pcc-appearance-mini-email__head" style="background: <?php echo esc_attr((string) $core->get_option('email_color_primary', '#0f3d5e')); ?>;">
                                        <strong data-preview-title><?php echo esc_html((string) $core->get_option('email_builder_heading', __('Your access details are ready', 'woo-otec-moodle'))); ?></strong>
                                    </div>
                                    <div class="pcc-appearance-mini-email__body">
                                        <p data-preview-text><?php echo esc_html((string) $core->get_option('email_builder_intro', __('Your purchase has been confirmed successfully. Here are your access details for the platform.', 'woo-otec-moodle'))); ?></p>
                                        <button type="button" class="button button-primary" style="background: <?php echo esc_attr((string) $core->get_option('email_color_accent', '#1f9d6f')); ?>; border-color: <?php echo esc_attr((string) $core->get_option('email_color_accent', '#1f9d6f')); ?>;">
                                            <span data-preview-button><?php echo esc_html((string) $core->get_option('email_builder_button_text', __('Access my courses', 'woo-otec-moodle'))); ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </section>

                    <section class="pcc-tab-panel <?php echo $active_tab === 'logs' ? 'is-active' : ''; ?>" id="pcc-panel-logs" data-panel="logs" role="tabpanel">
                        <h3><?php echo esc_html__('System logs', 'woo-otec-moodle'); ?></h3>
                        <div class="pcc-log-viewer-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <strong><?php echo esc_html__('Latest synchronization events:', 'woo-otec-moodle'); ?></strong>
                            <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=woo_otec_moodle_export_logs&nonce=' . wp_create_nonce('woo_otec_moodle_export_logs'))); ?>" class="button button-secondary"><?php echo esc_html__('Export all logs', 'woo-otec-moodle'); ?></a>
                        </div>
                        <div class="pcc-log-viewer" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 11px; max-height: 400px; overflow-y: auto; margin-bottom: 20px; border-left: 4px solid #1f9d6f;">
                            <?php 
                            if (!empty($sync_log)) {
                                foreach (array_reverse($sync_log) as $line) {
                                    echo esc_html($line) . '<br>';
                                }
                            } else {
                                echo esc_html__('There are no recent synchronization entries.', 'woo-otec-moodle');
                            }
                            ?>
                        </div>

                        <strong><?php echo esc_html__('Latest critical errors:', 'woo-otec-moodle'); ?></strong>
                        <div class="pcc-log-viewer" style="background: #1e1e1e; color: #f4b0b0; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 11px; max-height: 300px; overflow-y: auto; margin-top: 10px; border-left: 4px solid #d63638;">
                            <?php 
                            if (!empty($error_log)) {
                                foreach (array_reverse($error_log) as $line) {
                                    echo esc_html($line) . '<br>';
                                }
                            } else {
                                echo esc_html__('There are no recent error entries.', 'woo-otec-moodle');
                            }
                            ?>
                        </div>
                    </section>
                </div>

                <!-- Submit Button -->
                <div class="pcc-settings-actions" style="margin-top: 20px;">
                    <?php submit_button(__('Save configuration', 'woo-otec-moodle'), 'primary', 'submit', false); ?>
                </div>
            </main>
        </div>
    </form>

    <div class="pcc-admin-signature" style="margin-top: 40px; text-align: center; font-size: 12px; color: #888;">
        <span>www.pccurico.cl</span> | <span><?php echo esc_html__('developed by JCares', 'woo-otec-moodle'); ?></span>
    </div>

    <div id="pcc-admin-notices-bottom" class="pcc-admin-notices-bottom"></div>
</div>
