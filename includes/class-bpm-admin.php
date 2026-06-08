<?php
if (!defined('ABSPATH')) exit;

class BPM_Admin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu()
    {
        add_menu_page(
            'Bulk Price Manager',
            'Bulk Price Manager',
            'manage_woocommerce',
            'bulk-price-manager',
            [$this, 'page'],
            'dashicons-database',
            56
        );

        add_submenu_page(
            'bulk-price-manager',
            'Compatibility Check',
            'Compatibility Check',
            'manage_woocommerce',
            'bpm-compatibility',
            [$this, 'compatibility_page']
        );
    }

    public function assets($hook)
    {
//         echo '<pre>' . $hook . '</pre>';
// exit;
//         // if ($hook !== 'toplevel_page_bulk-price-manager') return;
//         if (
//             $hook !== 'toplevel_page_bulk-price-manager' &&
//             $hook !== 'bulk-price-manager_page_bpm-compatibility'
//         ) {
//             return;
//         }

if (strpos($hook, 'bulk-price-manager') === false) {
    return;
}

        // wp_enqueue_script('jquery');
        wp_enqueue_style('select2');
        wp_enqueue_script('select2');

        wp_enqueue_style(
            'datatables-bootstrap',
            'https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css',
            [],
            '1.10.19'
        );

        wp_enqueue_style(
            'bootstrap-4',
            'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.1/css/bootstrap.css',
            [],
            '4.1.1'
        );

        wp_enqueue_script(
            'datatables-core',
            'https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js',
            ['jquery'],
            '1.10.19',
            true
        );

        wp_enqueue_script(
            'datatables-bootstrap',
            'https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js',
            ['jquery', 'datatables-core'],
            '1.10.19',
            true
        );

        wp_enqueue_script(
            'bpm-admin',
            BPM_URL . 'assets/js/admin.js',
            ['jquery', 'select2', 'datatables-core'],
            time(),
            true
        );

        wp_enqueue_style(
            'bpm-admin',
            BPM_URL . 'assets/css/admin.css',
            ['datatables-bootstrap'],
            '1.0.0'
        );

        wp_localize_script('bpm-admin', 'BPM', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bpm_nonce')
        ]);
    }

    public function page()
    {
?>
        <div class="wrap bpm-wrap">

            <h1 class="bpm-title">Bulk Price Manager</h1>

            <!-- FILTERS -->
            <div class="bpm-card">
                <div id="bpm-toast"></div>
                <h2 class="bpm-card-title">Filters</h2>

                <div class="bpm-filters-grid">

                    <div class="bpm-field">
                        <label>Product IDs</label>
                        <input type="text" id="product_ids"
                            placeholder="Comma separated IDs">
                    </div>

                    <div class="bpm-field">
                        <label>Categories</label>
                        <select id="product_category" multiple>
                            <?php
                            $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                            foreach ($cats as $cat) {
                                echo "<option value='{$cat->term_id}'>{$cat->name}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="bpm-field">
                        <label>Tags</label>
                        <select id="product_tag" multiple>
                            <?php
                            $tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);
                            foreach ($tags as $tag) {
                                echo "<option value='{$tag->term_id}'>{$tag->name}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="bpm-field">
                        <label>Price Action</label>
                        <select id="price_action">
                            <option value="increase">Increase</option>
                            <option value="decrease">Decrease</option>
                        </select>
                    </div>

                    <div class="bpm-field">
                        <label>Calculation Type</label>
                        <select id="price_type">
                            <option value="fixed">Fixed</option>
                            <option value="percent">Percentage</option>
                        </select>
                    </div>

                    <div class="bpm-field">
                        <label>Value</label>
                        <input type="text" id="price_value"
                            placeholder="e.g. 10 or 5.5">
                    </div>

                </div>

                <div class="bpm-field bpm-full">
                    <label>Operation Label</label>
                    <input type="text" id="operation_label"
                        placeholder="e.g. 10% Increase – Chairs – Sept 2025">
                </div>

                <div class="bpm-actions">
                    <button class="button" id="bpm-load-products">
                        Load Products
                    </button>
                    <button class="button button-secondary" id="bpm-preview">
                        Trial Run
                    </button>
                    <button class="button button-primary" id="bpm-execute">
                        Execute
                    </button>
                    <button class="button" id="bpm-view-history">
                        View Operations History
                    </button>
                </div>
            </div>

            <!-- PRODUCTS TABLE -->
            <div class="bpm-card" id="bpm-products-section" style="display:none;">
                <h2 class="bpm-card-title">Products to be Updated</h2>
                <div id="bpm-products-table"></div>
            </div>

            <!-- <table id="myTable" class="table table-condensed table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th><input type="checkbox" id="checkAll"></th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Tag Name</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table> -->

            <!-- RESULT / TOAST -->
            <div id="bpm-result"></div>

        </div>

        <!-- HISTORY MODAL -->
        <div id="bpm-history-modal" class="bpm-modal" style="display:none;">
            <div class="bpm-modal-content">
                <div id="bpm-history-toast"></div>
                <span class="bpm-close">&times;</span>
                <h5>Bulk Price Operations History</h5>
                <div id="bpm-history-content">
                    <p>Loading history…</p>
                </div>
            </div>
        </div>

        <!-- Trial Run Preview Modal -->
        <div id="bpm-preview-modal" class="bpm-modal" style="display:none;">
            <div class="bpm-modal-content bpm-modal-lg">
                <span class="bpm-close" data-close="preview">&times;</span>
                <h2>Trial Run – Price Preview</h2>

                <div id="bpm-preview-content">
                    <p>Loading preview…</p>
                </div>
            </div>
        </div>

        <!-- OVERLAY -->
        <div id="bpm-overlay" style="display:none;">
            <div class="bpm-overlay-content">
                <div class="bpm-spinner"></div>
                <p id="bpm-overlay-text">Processing, please wait…</p>
            </div>
        </div>
<?php
    }

    public function compatibility_page() {

        global $wp_version;

        $checks = [];

        // PHP
        $checks[] = [
            'label' => 'PHP Version (>= 7.4)',
            'value' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4', '>=')
        ];

        // WordPress
        $checks[] = [
            'label' => 'WordPress Version (>= 5.8)',
            'value' => $wp_version,
            'status' => version_compare($wp_version, '5.8', '>=')
        ];

        // WooCommerce
        $checks[] = [
            'label' => 'WooCommerce',
            'value' => class_exists('WooCommerce') ? 'Active' : 'Not Active',
            'status' => class_exists('WooCommerce')
        ];

        // YITH
        $yith_status = false;
        $yith_value = 'Not Installed';

        if (defined('YITH_WAPO_VERSION')) {
            $yith_value = YITH_WAPO_VERSION;

            if (version_compare(YITH_WAPO_VERSION, '4.17.0', '>=')) {
                $yith_status = true;
            }
        }

        $checks[] = [
            'label' => 'YITH Add-ons (>= 4.17.0)',
            'value' => $yith_value,
            'status' => $yith_status
        ];

        echo '<div class="wrap"><h1>Compatibility Check</h1>';
        echo '<div id="bpm-compatibility">';

        foreach ($checks as $index => $check) {
            echo '<div class="bpm-check" data-status="' . ($check['status'] ? '1' : '0') . '" data-index="'.$index.'">';
            
            echo '<div class="bpm-left">';
            echo '<strong>' . esc_html($check['label']) . '</strong>';
            echo '<span class="bpm-value">' . esc_html($check['value']) . '</span>';
            echo '</div>';

            echo '<div class="bpm-right">';
            echo '<span class="bpm-loader"></span>';
            echo '<span class="bpm-result"></span>';
            echo '</div>';

            echo '</div>';
        }

        echo '</div></div>';
    }

}

new BPM_Admin();
