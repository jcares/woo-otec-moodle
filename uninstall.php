<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$defaults = require __DIR__ . '/config/defaults.php';

if (is_array($defaults)) {
    foreach ($defaults as $key => $value) {
        delete_option('woo_otec_moodle_' . $key);
    }
}

delete_transient('woo_otec_moodle_release');
