<?php
global $wpdb;

$table = $wpdb->prefix . 'bpm_price_history';

$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS $table (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_id VARCHAR(64) NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    old_price DECIMAL(10,2),
    new_price DECIMAL(10,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) $charset_collate;";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);
