<?php
function kopokopo_post_id_by_meta_key_and_value($key, $value) {
    global $wpdb;
    $meta = $wpdb->get_results("SELECT * FROM `".$wpdb->postmeta."` WHERE meta_key='".$key."' AND meta_value='".$value."'");
    if (is_array($meta) && !empty($meta) && isset($meta[0])) {
        $meta = $meta[0];
    }

    if (is_object($meta)) {
        return $meta->post_id;
    } else {
        return false;
    }
}

add_action( 'init', function() {
  /** Add a custom path and set a custom query argument. */
  add_rewrite_rule( '^/kopokopo_reconcile', 'index.php?kopokopo_reconcile=1', 'top' );
} );

add_filter( 'query_vars', function( $query_vars ) {
    /** Make sure WordPress knows about this custom action. */
    $query_vars []= 'kopokopo_reconcile';
    return $query_vars;
} );

add_action( 'wp', function() {
    /** This is an call for our custom action. */
    if ( get_query_var( 'kopokopo_reconcile' ) ) {
        $kopokopo_gateway   = new WC_Kopokopo_Gateway();
        $shortcode          = $kopokopo_gateway->get_option('kopokopo_shortcode');
        $api_key            = $kopokopo_gateway->get_option('kopokopo_api_key');

        $response = array(
            "status" => "03" // invalid
        );

        // Get all the fields from the post request
        $input = file_get_contents('php://input');
        $data = json_decode($input, TRUE);
        $data = !is_array($data) ? array() : $data;

        $signature = isset($data['signature']) ? $data['signature'] : '';
        unset($data['signature']);

        // create a Base64 encoded signature using API_KEY as the secret key
        // the signature is a Base64 encoded HMAC(Hash Message Authentication Code)
        // Described well in the KopoKopo API documentation     
        ksort($data);

        $b = [];
        foreach ($data as $key => $value) {
            $b[] = $key.'='.$value;            
        }
        sort($b);
        $base_string = implode('&', $b);
        $signature_created = base64_encode(hash_hmac("sha1", $base_string, $api_key, true));

        if ($signature_created == $signature) {
            $service_name               = $data['service_name'];
            $business_number            = $data['business_number'];
            $transaction_reference      = $data['transaction_reference'];
            $internal_transaction_id    = $data['internal_transaction_id'];
            $transaction_timestamp      = $data['transaction_timestamp'];
            $transaction_type           = $data['transaction_type'];
            $amount                     = $data['amount'];
            $first_name                 = $data['first_name'];
            $last_name                  = $data['last_name'];
            $middle_name                = $data['middle_name'];
            $sender_phone               = $data['sender_phone'];
            $currency                   = $data['currency'];
            $account_number             = $data['account_number'];

            // Insert the payment into the database
            $post_id = kopokopo_post_id_by_meta_key_and_value('_reference', $transaction_reference);

            update_post_meta( $post_id, '_transaction', $internal_transaction_id);
            update_post_meta( $post_id, '_timestamp', $transaction_timestamp);
            update_post_meta( $post_id, '_amount', $amount);
            update_post_meta( $post_id, '_customer', $first_name.' '.$middle_name.' '.$last_name);
            update_post_meta( $post_id, '_phone', $sender_phone);
            update_post_meta( $post_id, '_account_number', $account_number);

            $this_order = wc_get_order(get_post_meta( $post_id, 'order_id', true ));
            if ($this_order->get_status() == "pending" || $this_order->get_status() == 'on-hold' || $this_order->get_status() == 'failed') {
                if ((int) $amount >= $this_order->get_total()) {
                    $this_order->add_order_note(__("FULLY PAID: Payment of $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference confirmed by KopoKopo", 'woocommerce'));
                    $this_order->payment_complete();
                } else {
                    $this_order->add_order_note(__("PARTLY PAID: Received $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference", 'woocommerce'));
                }

                $response = array(
                    "status" => "01",
                    "description" => "Accepted",
                    "subscriber_message" => "We have received your payment of " . $amount . " for Order No. " . $record->order_id
                );
            } else {
                $response = array(
                    "status" => "01",
                    "description" => "Accepted",
                    "subscriber_message" => ""
                );
            }
        } else {
            $response = array(
                "status" => "02", // Account not found
                "description" => "Rejected", 
                "subscriber_message" => "" 
            );
        }

        exit(wp_send_json($response));
    }
} );