<?php
function kopokopo_post_id_by_meta_key_and_value($key, $value)
{
    global $wpdb;
    $meta = $wpdb->get_results("SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key='" . $key . "' AND meta_value='" . $value . "' ORDER BY meta_id DESC");
    if (is_array($meta) && !empty($meta) && isset($meta[0])) {
        $meta = $meta[0];
    }

    if (is_object($meta)) {
        return $meta->post_id;
    } else {
        return false;
    }
}

add_action('init', function () {
    add_rewrite_rule('kopokopo_reconcile', 'index.php?kopokopo_reconcile=1', 'top');
});

add_filter('query_vars', function ($query_vars) {
    $query_vars[] = 'kopokopo_reconcile';
    return $query_vars;
});

/**
 * Create a Base64 encoded signature using API_KEY as the secret key
 * The signature is a Base64 encoded HMAC(Hash Message Authentication Code)
 */
add_action('template_redirect', function () {
    if (get_query_var('kopokopo_reconcile')) {
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: Application/json");

        $kp        = new WC_Kopokopo_Gateway();
        $shortcode = $kp->get_option('shortcode');
        $api_key   = $kp->get_option('api_key');

        // Get all the fields from the post request
        $input                   = file_get_contents('php://input');
        $data                    = json_decode($input, true);
        $data                    = !is_array($data) ? array() : $data;
        $service_name            = $data['service_name'];
        $business_number         = $data['business_number'];
        $transaction_reference   = $data['transaction_reference'];
        $internal_transaction_id = $data['internal_transaction_id'];
        $transaction_timestamp   = $data['transaction_timestamp'];
        $transaction_type        = $data['transaction_type'];
        $amount                  = $data['amount'];
        $amount                  = round($amount);
        $first_name              = $data['first_name'];
        $last_name               = $data['last_name'];
        $middle_name             = $data['middle_name'];
        $sender_phone            = $data['sender_phone'];
        $currency                = $data['currency'];
        $account_number          = $data['account_number'];

        $signature = isset($data['signature']) ? $data['signature'] : '';
        unset($data['signature']);
        ksort($data);

        $b = array();
        foreach ($data as $key => $value) {
            $b[] = $key . '=' . $value;
        }
        sort($b);

        $base_string       = implode('&', $b);
        $signature_created = base64_encode(hash_hmac("sha1", $base_string, $api_key, true));

        // Get payment by reference and update details
        if ($kp->get_option('input') == 'yes') {
            $post_id = kopokopo_post_id_by_meta_key_and_value('_reference', $transaction_reference);
        } else {
            $post_id = kopokopo_post_id_by_meta_key_and_value('_phone', $sender_phone);
        }

        if (!$post_id) {
            $post_id = wp_insert_post(
                array(
                    'post_title'  => 'Order ' . time(),
                    'post_status' => 'publish',
                    'post_type'   => 'kopokopo_ipn',
                    'post_author' => is_user_logged_in() ? get_current_user_id() : 1,
                )
            );

            update_post_meta($post_id, '_reference', $transaction_reference);
            update_post_meta($post_id, '_phone', $sender_phone);
        }

        update_post_meta($post_id, '_transaction', $internal_transaction_id);
        update_post_meta($post_id, '_customer', "{$first_name} {$middle_name} {$last_name}");
        update_post_meta($post_id, '_timestamp', $transaction_timestamp);
        update_post_meta($post_id, '_receipt', $transaction_reference);
        update_post_meta($post_id, '_account_number', $account_number);
        update_post_meta($post_id, '_phone', $sender_phone);
        update_post_meta($post_id, '_amount', $amount);

        try {
            if ($kp->get_option('input') == 'yes') {
                $order_id = empty(get_post_meta($post_id, '_order_id', true))
                ? kopokopo_post_id_by_meta_key_and_value('_mpesa_reference', $transaction_reference)
                : get_post_meta($post_id, '_order_id', true);
            } else {
                $order_id = empty(get_post_meta($post_id, '_order_id', true));
            }

            global $woocommerce;
            $order = new WC_Order($order_id);
            if ($order !== false) {
                if ((int) $amount >= $order->get_total()) {
                    $order->add_order_note(__("FULLY PAID: Payment of $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference confirmed by KopoKopo", 'woocommerce'));
                    $order->update_status('completed');
                } else {
                    $order->add_order_note(__("PARTLY PAID: Received $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference", 'woocommerce'));
                    $order->update_status('processing');
                }
            }

            $response = array(
                "status"             => "01",
                "description"        => "Accepted",
                "subscriber_message" => "Payment of {$currency} {$amount} to " . get_bloginfo('name') . " received.",
            );

            // if ($signature_created == $signature) {
            //     update_post_meta($post_id, '_transaction', $internal_transaction_id);
            //     update_post_meta($post_id, '_timestamp', $transaction_timestamp);
            //     update_post_meta($post_id, '_receipt', $transaction_reference);
            //     update_post_meta($post_id, '_amount', $amount);
            //     update_post_meta($post_id, '_customer', $first_name . ' ' . $middle_name . ' ' . $last_name);
            //     update_post_meta($post_id, '_phone', $sender_phone);
            //     update_post_meta($post_id, '_account_number', $account_number);

            //     $order_id = get_post_meta($post_id, '_order_id', true);
            //     $order    = wc_get_order($order_id);

            //     if ($transaction_reference == get_post_meta($post_id, '_reference', true)) {
            //         if ((int) $amount >= $order->get_total()) {
            //             update_post_meta($post_id, '_order_status', 'complete');
            //             $order->add_order_note(__("FULLY PAID: Payment of $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference confirmed by KopoKopo", 'woocommerce'));
            //             $order->payment_complete();
            //             $order->update_status('completed');
            //         } else {
            //             $order->add_order_note(__("PARTLY PAID: Received $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference", 'woocommerce'));
            //             $order->update_status('processing');
            //         }
            //     }

            //     $response = array(
            //         "status"             => "01",
            //         "description"        => "Reconciliation processed",
            //         "subscriber_message" => "Payment of {$currency} {$amount} for Order #{$order_id} to " . get_bloginfo('name') . " received.",
            //     );
            // } else {
            //     $response = array(
            //         "status"             => "02",
            //         "description"        => "Rejected",
            //         "subscriber_message" => "Account not found",
            //     );
            // }
        } catch (Exception $e) {
            $response = array(
                "status"             => "03",
                "description"        => "Invalid Payment",
                "subscriber_message" => $e->getMessage(),
            );
        }

        exit(wp_send_json($response));
    }
});

add_action('init', function () {
    if (isset($_GET['kopoipncheck'])) {
        $response = array('receipt' => '');
        if (!empty($_GET['order'])) {
            $post     = kopokopo_post_id_by_meta_key_and_value('_order_id', $_GET['order']);
            $response = array(
                'receipt' => get_post_meta($post, '_receipt', true),
            );
        }

        // $data = array(
        //     "service_name" => "MPESA",
        //     "business_number" => "892309",
        //     "transaction_reference" => "NHUIODUSAA",
        //     "internal_transaction_id" => 3222,
        //     "transaction_timestamp" => "2019-04-21T13:57:00Z",
        //     "transaction_type" => "Till",
        //     "account_number" => "445534",
        //     "sender_phone" => "0768904639",
        //     "first_name" => "John",
        //     "middle_name" => "K",
        //     "last_name" => "Doe",
        //     "amount" => 1,
        //     "currency" => "KES"
        // );
        // ksort($data);

        // $b = array();
        // foreach ($data as $key => $value) {
        // $b[] = $key . '=' . $value;
        // }
        // sort($b);

        // $base_string       = implode('&', $b);
        // echo base64_encode(hash_hmac("sha1", $base_string, '7bc21e95a0bab4e2f4765bc84bc2b1a050943691', true));

        exit(wp_send_json($response));
    }
});
