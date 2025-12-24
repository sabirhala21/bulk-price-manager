<?php
if (!defined('ABSPATH')) exit;

class BPM_Executor {

    public static function run($data) {
        global $wpdb;

        $ids = array_map('intval', explode(',', $data['ids']));
        // $action = $data['action'];
        $action = $data['action_type'];
        $type = $data['type'];
        $value = floatval($data['value']);
        $operation_id = uniqid('bpm_', true);
        $label = sanitize_text_field($data['operation_label']);
        $products = BPM_Query::get_products($ids);

        try {
            $wpdb->query('START TRANSACTION');

            foreach ($products as $product) {
                $old = (float) $product->get_regular_price();
                if ($old <= 0) continue;

                $new = ($type === 'percent')
                    ? $old * (1 + ($action === 'increase' ? $value : -$value) / 100)
                    : $old + ($action === 'increase' ? $value : -$value);

                $new = max(0, round($new, wc_get_price_decimals()));

                $wpdb->insert(
                    "{$wpdb->prefix}bpm_price_history",
                    [
                        'operation_id' => $operation_id,
                        'operation_label' => $label,
                        'product_id' => $product->get_id(),
                        'old_price' => $old,
                        'new_price' => $new
                    ]
                );

                $product->set_regular_price($new);
                $product->save();
            }

            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        return $operation_id;
    }
}
