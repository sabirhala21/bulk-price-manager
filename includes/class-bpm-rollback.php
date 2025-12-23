<?php
if (!defined('ABSPATH')) exit;

class BPM_Rollback {

    public static function rollback($operation_id) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bpm_price_history WHERE operation_id = %s",
                $operation_id
            )
        );

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($rows as $row) {
                $product = wc_get_product($row->product_id);
                if (!$product) continue;

                $product->set_regular_price($row->old_price);
                $product->save();
            }

            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
        }
    }
}
