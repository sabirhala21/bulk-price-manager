<?php
if (!defined('ABSPATH')) exit;

class BPM_Rollback {

    public static function rollback($operation_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'bpm_price_history';
        $addons_table = $wpdb->prefix . 'yith_wapo_addons';

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
                "SELECT *
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
                //rollback products price
                if ($row->item_type !== 'product') continue;

                $product = wc_get_product($row->product_id);
                if (!$product) continue;

                $product->set_regular_price($row->old_price);
                $product->save();

            }
            
            //rollback products add-ons price
            $addons_cache = [];

            foreach ($rows as $row) {

                if ($row->item_type !== 'addon') {
                    continue;
                }

                if (!$row->addon_id || $row->option_index === null) {
                    continue;
                }

                // Load addon once (cache)
                if (!isset($addons_cache[$row->addon_id])) {

                    $addon = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT id, options
                             FROM {$addons_table}
                             WHERE id = %d",
                            $row->addon_id
                        )
                    );

                    if (!$addon) {
                        continue;
                    }

                    $addons_cache[$row->addon_id] = maybe_unserialize($addon->options);
                }

                $options = &$addons_cache[$row->addon_id];

                // Restore old price
                $options['price'][$row->option_index] = number_format(
                    (float) $row->old_price,
                    wc_get_price_decimals(),
                    '.',
                    ''
                );
            }

            // Save updated addons
            foreach ($addons_cache as $addon_id => $options) {
                $wpdb->update(
                    $addons_table,
                    ['options' => maybe_serialize($options)],
                    ['id' => $addon_id]
                );
            }


            // Mark as rolled back
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

