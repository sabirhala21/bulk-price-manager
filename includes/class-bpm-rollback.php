<?php
if (!defined('ABSPATH')) exit;

class BPM_Rollback {

    public static function rollback($operation_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'bpm_price_history';

        // ❌ Prevent double rollback
        $already = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE operation_id = %s AND rolled_back = 1",
                $operation_id
            )
        );

        if ($already > 0) {
            throw new Exception('This operation has already been rolled back.');
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, old_price
                 FROM {$table}
                 WHERE operation_id = %s",
                $operation_id
            )
        );

        if (empty($rows)) {
            throw new Exception('No records found for this operation');
        }

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($rows as $row) {
                $product = wc_get_product($row->product_id);
                if (!$product) continue;

                $product->set_regular_price($row->old_price);
                $product->save();
            }

            // ✅ Mark as rolled back
            $wpdb->update(
                $table,
                ['rolled_back' => 1],
                ['operation_id' => $operation_id]
            );

            $wpdb->query('COMMIT');

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}

