<?php
/**
 * Plugin Name:       Woo OTEC Moodle
 * Plugin URI:        https://github.com/jcares/woo-otec-moodle
 * Description:       Integración profesional entre Moodle y WooCommerce. Sincroniza cursos, inscribe alumnos y administra permisos automáticamente.
 * Version:           2.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            JCares
 * Author URI:        https://www.pccurico.cl
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

// Constantes base
define('WOO_OTEC_MOODLE_VERSION', '2.1.0');
define('WOO_OTEC_MOODLE_FILE', __FILE__);
define('WOO_OTEC_MOODLE_BASENAME', plugin_basename(__FILE__));
define('WOO_OTEC_MOODLE_PATH', plugin_dir_path(__FILE__));
define('WOO_OTEC_MOODLE_URL', plugin_dir_url(__FILE__));

// Core
require_once WOO_OTEC_MOODLE_PATH . 'includes/class-core.php';

// Hooks de activacion
register_activation_hook(WOO_OTEC_MOODLE_FILE, 'woo_otec_moodle_install');
register_deactivation_hook(WOO_OTEC_MOODLE_FILE, array('Woo_OTEC_Moodle_Core', 'deactivate'));

function woo_otec_moodle_install(): void {
    Woo_OTEC_Moodle_Core::activate();

    if (class_exists('Woo_OTEC_Moodle_Logger')) {
        Woo_OTEC_Moodle_Logger::get_directory();
    }
}

// Carga del nucleo del plugin
Woo_OTEC_Moodle_Core::instance()->boot();
