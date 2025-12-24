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

    public function preview()
    {
        $this->validate();

        $ids = array_map('intval', explode(',', $_POST['ids']));
        $products = BPM_Query::get_products($ids);

        $simple = 0;
        $variation = 0;

        foreach ($products as $p) {
            if ($p->is_type('variation')) {
                $variation++;
            } else {
                $simple++;
            }
        }

        wp_send_json([
            'total'     => count($products),
            'simple'    => $simple,
            'variation' => $variation
        ]);
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
            wp_send_json_error('Product IDs are required');
        }

        if ($value === '' || !is_numeric($value)) {
            wp_send_json_error('Valid price value is required');
        }

        $op_id = BPM_Executor::run($_POST);

        wp_send_json_success([
            'message' => 'Bulk update completed successfully',
            'operation_id' => $op_id
        ]);

    }
}

new BPM_Ajax();
