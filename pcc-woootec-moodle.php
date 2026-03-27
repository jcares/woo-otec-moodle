<?php
/**
 * Plugin Name: PCC WooOTEC Moodle
 * Description: Integracion Moodle + WooCommerce
 * Version: 2.1.0
 * Author: JCares
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * Text Domain: pcc-woootec-moodle
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 9.8
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes base
define('PCC_WOOOTEC_VERSION', '2.1.0');
define('PCC_WOOOTEC_PRO_VERSION', PCC_WOOOTEC_VERSION);
define('PCC_WOOOTEC_PRO_FILE', __FILE__);
define('PCC_WOOOTEC_PRO_BASENAME', plugin_basename(__FILE__));
define('PCC_WOOOTEC_PRO_PATH', plugin_dir_path(__FILE__));
define('PCC_WOOOTEC_PRO_URL', plugin_dir_url(__FILE__));

// Core
require_once PCC_WOOOTEC_PRO_PATH . 'includes/class-core.php';

// Hooks de activacion
register_activation_hook(PCC_WOOOTEC_PRO_FILE, 'pcc_woootec_install');
register_deactivation_hook(PCC_WOOOTEC_PRO_FILE, array('PCC_WooOTEC_Pro_Core', 'deactivate'));

function pcc_woootec_install(): void {
    PCC_WooOTEC_Pro_Core::activate();

    if (class_exists('PCC_WooOTEC_Pro_Logger')) {
        PCC_WooOTEC_Pro_Logger::get_directory();
    }
}

// Carga del nucleo del plugin
PCC_WooOTEC_Pro_Core::instance()->boot();
