<?php
function kopokopo_post_id_by_meta_key_and_value($key, $value)
{
    global $wpdb;
    $meta = $wpdb->get_results("SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key='" . $key . "' AND meta_value='" . $value . "'");
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

add_action('wp', function () {
    if (get_query_var('kopokopo_reconcile')) {
        $kopokopo_gateway = new WC_Kopokopo_Gateway();
        $shortcode        = $kopokopo_gateway->get_option('shortcode');
        $api_key          = $kopokopo_gateway->get_option('api_key');

        $response = array(
            "status" => "03", // invalid
        );

        // Get all the fields from the post request
        $input = file_get_contents('php://input');
        $data  = json_decode($input, true);
        $data  = !is_array($data) ? array() : $data;

        $signature = isset($data['signature']) ? $data['signature'] : '';
        unset($data['signature']);

        /**
         * Create a Base64 encoded signature using API_KEY as the secret key
         * The signature is a Base64 encoded HMAC(Hash Message Authentication Code)
         */
        ksort($data);

        $b = array();
        foreach ($data as $key => $value) {
            $b[] = $key . '=' . $value;
        }
        sort($b);

        $base_string       = implode('&', $b);
        $signature_created = base64_encode(hash_hmac("sha1", $base_string, $api_key, true));

        if ($signature_created == $signature) {
            $service_name            = $data['service_name'];
            $business_number         = $data['business_number'];
            $transaction_reference   = $data['transaction_reference'];
            $internal_transaction_id = $data['internal_transaction_id'];
            $transaction_timestamp   = $data['transaction_timestamp'];
            $transaction_type        = $data['transaction_type'];
            $amount                  = $data['amount'];
            $first_name              = $data['first_name'];
            $last_name               = $data['last_name'];
            $middle_name             = $data['middle_name'];
            $sender_phone            = $data['sender_phone'];
            $currency                = $data['currency'];
            $account_number          = $data['account_number'];

            // Get payment by reference and update details
            $post_id = kopokopo_post_id_by_meta_key_and_value('_reference', $transaction_reference);

            update_post_meta($post_id, '_transaction', $internal_transaction_id);
            update_post_meta($post_id, '_timestamp', $transaction_timestamp);
            update_post_meta($post_id, '_receipt', $transaction_reference);
            update_post_meta($post_id, '_amount', $amount);
            update_post_meta($post_id, '_customer', $first_name . ' ' . $middle_name . ' ' . $last_name);
            update_post_meta($post_id, '_phone', $sender_phone);
            update_post_meta($post_id, '_account_number', $account_number);

            $order_id = get_post_meta($post_id, 'order_id', true);
            $order    = wc_get_order($order_id);

            if ($transaction_reference == get_post_meta($post_id, '_reference', true)) {
                if ((int) $amount >= $order->get_total()) {
                    update_post_meta($post_id, '_order_status', 'complete');
                    $order->add_order_note(__("FULLY PAID: Payment of $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference confirmed by KopoKopo", 'woocommerce'));
                    $order->payment_complete();
                } else {
                    $order->add_order_note(__("PARTLY PAID: Received $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference", 'woocommerce'));
                }
            }

            $response = array(
                "status"             => "01",
                "description"        => "Accepted",
                "subscriber_message" => "Payment of {$currency} {$amount} for Order #{$order_id} to " . getbloginfo('name') . " received.",
            );
        } else {
            $response = array(
                "status"             => "02",
                "description"        => "Rejected",
                "subscriber_message" => "Account not found",
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

        exit(wp_send_json($response));
    }
});
