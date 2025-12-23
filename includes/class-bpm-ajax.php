<?php
if (!defined('ABSPATH')) exit;

class BPM_Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_bpm_preview', [$this, 'preview']);
        add_action('wp_ajax_bpm_execute', [$this, 'execute']);
    }

    private function validate()
    {
        check_ajax_referer('bpm_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_die();
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

        BPM_Executor::run($_POST);
        wp_send_json_success('Bulk update completed safely.');
    }
}

new BPM_Ajax();
