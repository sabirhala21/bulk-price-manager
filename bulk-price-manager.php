<?php
/**
 * Plugin Name: Bulk Price Manager for WooCommerce
 * Description: Safe bulk price updates with dry-run, rollback, and transaction support.
 * Version: 1.0.0
 * Author: Sabir Hala
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

define('BPM_PATH', plugin_dir_path(__FILE__));
define('BPM_URL', plugin_dir_url(__FILE__));

require_once BPM_PATH . 'includes/class-bpm-database.php';
require_once BPM_PATH . 'includes/class-bpm-admin.php';
require_once BPM_PATH . 'includes/class-bpm-ajax.php';
require_once BPM_PATH . 'includes/class-bpm-query.php';
require_once BPM_PATH . 'includes/class-bpm-executor.php';
require_once BPM_PATH . 'includes/class-bpm-rollback.php';

register_activation_hook(__FILE__, 'bpm_activate_plugin');

// function bpm_activate_plugin() {
//     BPM_Database::create_tables();
//     update_option('bpm_db_version', '1.0');
// }

function bpm_activate_plugin() {

    $errors = [];

    // PHP
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $errors[] = 'PHP 7.4 or higher required. Current: ' . PHP_VERSION;
    }

    // WordPress
    global $wp_version;
    if (version_compare($wp_version, '5.8', '<')) {
        $errors[] = 'WordPress 5.8 or higher required. Current: ' . $wp_version;
    }

    // WooCommerce
    if (!class_exists('WooCommerce')) {
        $errors[] = 'WooCommerce must be installed and active.';
    }

    // YITH (optional but version check if exists)
    if (defined('YITH_WAPO_VERSION')) {
        if (version_compare(YITH_WAPO_VERSION, '4.17.0', '<')) {
            $errors[] = 'YITH Add-ons version 4.17.0+ required. Current: ' . YITH_WAPO_VERSION;
        }
    }

    if (!empty($errors)) {

        deactivate_plugins(plugin_basename(__FILE__));

        wp_die(
            '<h2>Bulk Price Manager - Requirements Not Met</h2>' .
            '<p>' . implode('<br>', $errors) . '</p>',
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }

    // Your existing logic
    BPM_Database::create_tables();
    update_option('bpm_db_version', '1.0');
}
