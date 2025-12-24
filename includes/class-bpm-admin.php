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

        wp_enqueue_style(
            'bpm-admin',
            BPM_URL . 'assets/css/admin.css',
            [],
            '1.0'
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

            <p>
                <label for="operation_label"><strong>Operation Label</strong></label><br>
                <input type="text" id="operation_label" style="width:300px"
                    placeholder="e.g. 10% Increase – Chairs – Sept 2025">
            </p>

            <br><br>
            <button class="button button-secondary" id="bpm-preview">Trial Run</button>
            <button class="button button-primary" id="bpm-execute">Execute</button>
            <button class="button" id="bpm-view-history">
                View Operations History
            </button>

            <div id="bpm-result"></div>
            <!-- <div id="bpm-history"></div> -->
            <div id="bpm-toast" style="
                display:none;
                position:fixed;
                top:50%;
                right:0;
                background:#2ecc71;
                color:#fff;
                padding:12px 18px;
                border-radius:4px;
                z-index:9999;
            "></div>
        </div>
        <!-- <div id="bpm-spinner" style="display:none;">
            <p><strong>Processing, please wait…</strong></p>
        </div> -->
        <div id="bpm-history-modal" class="bpm-modal" style="display:none;">
            <div class="bpm-modal-content">
                <span class="bpm-close">&times;</span>
                <h2>Bulk Price Operations History</h2>
                <div id="bpm-history-content">
                    <p>Loading history…</p>
                </div>
            </div>
        </div>
        <!-- BPM Overlay -->
        <div id="bpm-overlay" style="display:none;">
            <div class="bpm-overlay-content">
                <div class="bpm-spinner"></div>
                <p id="bpm-overlay-text">Processing, please wait…</p>
            </div>
        </div>

        <?php
    }
}

new BPM_Admin();
