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
        $processed_addons = [];
        try {
            $wpdb->query('START TRANSACTION');

            //PRODUCT PRICE UPDATE

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
                        'new_price' => $new,
                        'item_type' => 'product',
                    ]
                );

                $product->set_regular_price($new);
                $product->save();
            }

            // YITH ADD-ON PRICE UPDATE
            BPM_Yith_Addons::update_prices(
                $ids,
                $action,
                $type,
                $value,
                $operation_id,
                $label,
                $processed_addons
            );
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        return $operation_id;
    }
}

class BPM_Yith_Addons {

    public static function update_prices(
        array $product_ids,
        string $action,
        string $type,
        float $value,
        string $operation_id,
        string $label,
        array &$processed_addons
    ) {
        global $wpdb;

        $assoc_table  = "{$wpdb->prefix}yith_wapo_blocks_assoc";
        $addons_table = "{$wpdb->prefix}yith_wapo_addons";
        $history_table = "{$wpdb->prefix}bpm_price_history";

        foreach ($product_ids as $product_id) {

            // 1️⃣ Find blocks attached to product
            $block_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT rule_id FROM {$assoc_table}
                     WHERE object = %d AND type = 'product'",
                    $product_id
                )
            );

            if (!$block_ids) continue;

            foreach ($block_ids as $block_id) {

                // 2️⃣ Get addons for block
                $addons = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, options FROM {$addons_table}
                         WHERE block_id = %d",
                        $block_id
                    )
                );

                foreach ($addons as $addon) {

                    // ✅ Prevent double update
                    if (isset($processed_addons[$addon->id])) {
                        continue;
                    }

                    $options = maybe_unserialize($addon->options);
                    if (!is_array($options) || empty($options['price'])) {
                        continue;
                    }

                    $updated = false;

                    foreach ($options['price'] as $index => $price) {

                        if ($price === '' || !is_numeric($price)) {
                            continue;
                        }

                        if (
                            isset($options['price_method'][$index]) &&
                            $options['price_method'][$index] === 'free'
                        ) {
                            continue;
                        }

                        $old = (float) $price;

                        $new = ($type === 'percent')
                            ? $old * (1 + ($action === 'increase' ? $value : -$value) / 100)
                            : $old + ($action === 'increase' ? $value : -$value);

                        $new = round(max(0, $new), 2);

                        if ($new !== $old) {
                            // $options['price'][$index] = (string) $new;
                            $options['price'][$index] = number_format(
                                $new,
                                wc_get_price_decimals(),
                                '.',
                                ''
                            );
                            $updated = true;

                            // Log addon price change
                            $wpdb->insert($history_table, [
                                'operation_id' => $operation_id,
                                'operation_label' => $label,
                                'product_id'   => null,
                                'addon_id'     => $addon->id,
                                'option_index' => $index,
                                'old_price'    => $old,
                                'new_price'    => $new,
                                'item_type'    => 'addon',
                            ]);
                        }
                    }

                    if ($updated) {
                        $wpdb->update(
                            $addons_table,
                            ['options' => maybe_serialize($options)],
                            ['id' => $addon->id]
                        );
                    }

                    // ✅ Mark as processed
                    $processed_addons[$addon->id] = true;
                }
            }
        }
    }
}

