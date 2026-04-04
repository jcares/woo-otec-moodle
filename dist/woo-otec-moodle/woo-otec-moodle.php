<?php
/**
 * Plugin Name:       Woo OTEC Moodle
 * Plugin URI:        https://github.com/jcares/woo-otec-moodle
 * Description:       Professional Moodle and WooCommerce integration for course sync, enrollment automation, and learner access management.
 * Version:           2.1.34
 * Requires at least: 6.4
 * Requires Plugins:  woocommerce
 * Requires PHP:      8.1
 * Author:            JCares
 * Author URI:        https://www.pccurico.cl
 * Developer:         JCares
 * Developer URI:     https://www.pccurico.cl
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-otec-moodle
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.8
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WOO_OTEC_MOODLE_VERSION', '2.1.34');
define('WOO_OTEC_MOODLE_FILE', __FILE__);
define('WOO_OTEC_MOODLE_BASENAME', plugin_basename(__FILE__));
define('WOO_OTEC_MOODLE_PATH', plugin_dir_path(__FILE__));
define('WOO_OTEC_MOODLE_URL', plugin_dir_url(__FILE__));

require_once WOO_OTEC_MOODLE_PATH . 'includes/class-core.php';

register_activation_hook(WOO_OTEC_MOODLE_FILE, 'woo_otec_moodle_install');
register_deactivation_hook(WOO_OTEC_MOODLE_FILE, array('Woo_OTEC_Moodle_Core', 'deactivate'));
add_filter('plugin_action_links_' . WOO_OTEC_MOODLE_BASENAME, 'woo_otec_moodle_plugin_action_links');

function woo_otec_moodle_install(): void {
    Woo_OTEC_Moodle_Core::activate();

    if (class_exists('Woo_OTEC_Moodle_Logger')) {
        Woo_OTEC_Moodle_Logger::get_directory();
    }
}

function woo_otec_moodle_plugin_action_links(array $links): array {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('admin.php?page=woo-otec-moodle')),
        esc_html__('Settings', 'woo-otec-moodle')
    );

    array_unshift($links, $settings_link);

    return $links;
}

Woo_OTEC_Moodle_Core::instance()->boot();
