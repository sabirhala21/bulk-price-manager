<?php
/**
 * Plugin Name: Bulk Price Manager for WooCommerce
 * Description: Safe bulk price updates with dry-run, rollback, and transaction support.
 * Version: 1.0.0
 * Author: Open Source Community
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

define('BPM_PATH', plugin_dir_path(__FILE__));
define('BPM_URL', plugin_dir_url(__FILE__));

require_once BPM_PATH . 'includes/class-bpm-admin.php';
require_once BPM_PATH . 'includes/class-bpm-ajax.php';
require_once BPM_PATH . 'includes/class-bpm-query.php';
require_once BPM_PATH . 'includes/class-bpm-executor.php';
require_once BPM_PATH . 'includes/class-bpm-rollback.php';

register_activation_hook(__FILE__, function () {
    require_once BPM_PATH . 'database/schema.sql';
});
