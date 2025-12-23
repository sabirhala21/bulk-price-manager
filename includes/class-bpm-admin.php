<?php
if (!defined('ABSPATH')) exit;

class BPM_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu() {
        add_menu_page(
            'Bulk Price Manager',
            'Bulk Price Manager',
            'manage_woocommerce',
            'bulk-price-manager',
            [$this, 'page'],
            'dashicons-database',
            56
        );
    }

    public function assets($hook) {
        if ($hook !== 'toplevel_page_bulk-price-manager') return;

        wp_enqueue_script(
            'bpm-admin',
            BPM_URL . 'assets/js/admin.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('bpm-admin', 'BPM', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bpm_nonce')
        ]);
    }

    public function page() {
        ?>
        <div class="wrap">
            <h1>Bulk Price Manager</h1>

            <h3>Filters</h3>
            <input type="text" id="product_ids" placeholder="Product IDs (comma separated)">
            <select id="price_action">
                <option value="increase">Increase</option>
                <option value="decrease">Decrease</option>
            </select>
            <select id="price_type">
                <option value="fixed">Fixed</option>
                <option value="percent">Percentage</option>
            </select>
            <input type="number" step="0.01" id="price_value" placeholder="Value">

            <br><br>
            <button class="button button-secondary" id="bpm-preview">Trial Run</button>
            <button class="button button-primary" id="bpm-execute">Execute</button>

            <div id="bpm-result"></div>
        </div>
        <?php
    }
}

new BPM_Admin();
