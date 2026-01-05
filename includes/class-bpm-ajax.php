<?php
if (!defined('ABSPATH')) exit;

class BPM_Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_bpm_preview', [$this, 'preview']);
        add_action('wp_ajax_bpm_execute', [$this, 'execute']);
        add_action('wp_ajax_bpm_history',  [$this, 'history']);
        add_action('wp_ajax_bpm_rollback', [$this, 'rollback']);
        add_action('wp_ajax_bpm_operation_products', [$this, 'operation_products']);
        add_action('wp_ajax_bpm_load_products', [$this, 'load_products']);

    }

    private function validate()
    {
        check_ajax_referer('bpm_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_die();
    }

    public function history() {
        $this->validate();
        global $wpdb;

        $table = $wpdb->prefix . 'bpm_price_history';

        $rows = $wpdb->get_results("
            SELECT 
                operation_id,
                operation_label,
                COUNT(*) as total_items,
                MAX(created_at) as performed_at,
                MAX(rolled_back) AS rolled_back
            FROM {$table}
            GROUP BY operation_id, operation_label
            ORDER BY performed_at DESC
        ");

        wp_send_json($rows);
    }

    public function rollback() {
        $this->validate(); // nonce + capability check

        if (empty($_POST['operation_id'])) {
            wp_send_json_error('Operation ID is required');
        }

        $operation_id = sanitize_text_field($_POST['operation_id']);

        try {
            BPM_Rollback::rollback($operation_id);

            wp_send_json_success('Prices rolled back successfully');
        } catch (Exception $e) {
            wp_send_json_error('Rollback failed: ' . $e->getMessage());
        }
    }

    public function operation_products() {
        $this->validate();
        global $wpdb;

        $op = sanitize_text_field($_POST['operation_id']);
        $table = $wpdb->prefix . 'bpm_price_history';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, old_price, new_price
                FROM {$table}
                WHERE operation_id = %s",
                $op
            )
        );

        $data = [];
        foreach ($rows as $r) {
            $product = wc_get_product($r->product_id);
            if (!$product) continue;

            $data[] = [
                'name' => $product->get_name(),
                'old'  => wc_price($r->old_price),
                'new'  => wc_price($r->new_price)
            ];
        }

        wp_send_json($data);
    }

    public function load_products() {
        $this->validate();
        $ids = !empty($_POST['ids'])
            ? array_map('intval', explode(',', $_POST['ids']))
            : [];
        $tax_query = [];

        if (!empty($_POST['categories'])) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $_POST['categories']),
            ];
        }

        if (!empty($_POST['tags'])) {
            $tax_query[] = [
                'taxonomy' => 'product_tag',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $_POST['tags']),
            ];
        }
        $args = [
            'status' => ['publish'],
            'limit'  => -1,
            'type'   => ['simple', 'variable'],
        ];
        if (!empty($ids)) {
            $args['include'] = $ids;
        }
        if (!empty($tax_query)) {
            // If both category & tag are present → AND condition
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }
        $query = new WC_Product_Query($args);
        $products = $query->get_products();

        $data = [];
        foreach ($products as $product) {

            if ($product->is_type('variable')) {

                $children = [];

                foreach ($product->get_children() as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if (!$variation) {
                        continue;
                    }

                    $children[] = [
                        'id'    => $variation->get_id(),
                        'name'  => $variation->get_name(),
                        'price' => wc_price($variation->get_regular_price()),
                        'type'  => 'variation',
                    ];
                }
                if (!empty($children)) {
                    $data[] = [
                        'id'       => $product->get_id(),
                        'name'     => $product->get_name(),
                        'type'     => 'variable',
                        'children' => $children,
                    ];
                }

            } else {
                $data[] = [
                    'id'    => $product->get_id(),
                    'name'  => $product->get_name(),
                    'price' => wc_price($product->get_regular_price()),
                    'type'  => 'simple',
                ];
            }
        }
        wp_send_json($data);
    }



    // public function preview()
    // {
    //     $this->validate();

    //     $ids = array_map('intval', explode(',', $_POST['ids']));
    //     $products = BPM_Query::get_products($ids);

    //     $simple = 0;
    //     $variation = 0;

    //     foreach ($products as $p) {
    //         if ($p->is_type('variation')) {
    //             $variation++;
    //         } else {
    //             $simple++;
    //         }
    //     }

    //     wp_send_json([
    //         'total'     => count($products),
    //         'simple'    => $simple,
    //         'variation' => $variation
    //     ]);
    // }

    public function preview()
    {
        $this->validate();

        if (empty($_POST['ids'])) {
            wp_send_json_error('No products selected');
        }

        $ids   = array_map('intval', explode(',', $_POST['ids']));
        $type  = sanitize_text_field($_POST['type']);
        $action = sanitize_text_field($_POST['action_type']);
        $value = floatval($_POST['value']);

        $products = BPM_Query::get_products($ids);

        $data = [];

        foreach ($products as $product) {

            $old = (float) $product->get_regular_price();
            if ($old <= 0) continue;

            $new = ($type === 'percent')
                ? $old * (1 + ($action === 'increase' ? $value : -$value) / 100)
                : $old + ($action === 'increase' ? $value : -$value);

            $new = max(0, round($new, wc_get_price_decimals()));
            $diff = $new - $old;
            $data[] = [
                'id'   => $product->get_id(),
                'name' => $product->get_name(),
                'type' => $product->is_type('variation') ? 'Variation' : 'Simple',
                'old'  => wc_price($old),
                'new'  => wc_price($new),
                'diff' => wc_price($new - $old),
                'diff_raw' => $diff,
            ];
        }

        wp_send_json_success($data);
    }


    public function execute()
    {
        $this->validate();

        $ids   = isset($_POST['ids']) ? trim($_POST['ids']) : '';
        $value = isset($_POST['value']) ? trim($_POST['value']) : '';
        $label = isset($_POST['operation_label']) ? trim($_POST['operation_label']) : '';

        if ($label === '') {
            wp_send_json_error('Operation label is required');
        }
        
        if ($ids === '') {
            wp_send_json_error('No products selected for update');
        }

        if ($value === '' || !is_numeric($value)) {
            wp_send_json_error('Valid price value is required');
        }

        

        $op_id = BPM_Executor::run($_POST);

        wp_send_json_success([
            'message'      => 'Bulk update completed successfully',
            'operation_id' => $op_id
        ]);
    }

}

new BPM_Ajax();
