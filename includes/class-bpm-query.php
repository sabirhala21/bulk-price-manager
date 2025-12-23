<?php
if (!defined('ABSPATH')) exit;

class BPM_Query {

    public static function get_products($ids = []) {
        $products = [];

        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product) continue;

            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $variation_id) {
                    $products[] = wc_get_product($variation_id);
                }
            } else {
                $products[] = $product;
            }
        }

        return $products;
    }
}
