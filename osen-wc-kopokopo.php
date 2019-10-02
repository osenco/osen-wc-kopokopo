<?php
/**
 * @package KopoKopo For WooCommerce
 * @subpackage Plugin File
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.19.08
 *
 * Plugin Name: KopoKopo for WooCommerce
 * Plugin URI:  https://kopokopo.org
 * Description: This plugin extends WordPress and WooCommerce functionality to integrate Lipa Na M-PESA by Kopokopo for making and receiving online payments.
 * Version:     0.19.10
 * Author:      Osen Concepts
 * Author URI:  https://osen.co.ke/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: osen
 * Domain Path: /languages
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 3.6.5
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KP_VER', '1.19.08');
if (!defined('KP_PLUGIN_FILE')) {
    define('KP_PLUGIN_FILE', __FILE__);
}

// Deactivate plugin if WooCommerce is not active
register_activation_hook(__FILE__, 'wc_kopokopo_activation_check');
function wc_kopokopo_activation_check()
{
    if (!get_option('wc_kopokopo_flush_rewrite_rules_flag')) {
        add_option('wc_kopokopo_flush_rewrite_rules_flag', true);
    }

    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        deactivate_plugins(plugin_basename(__FILE__));
        exit('Please Install/Activate WooCommerce for the KopoKopo extension to work');
    }
}

add_action('init', 'wc_kopokopo_flush_rewrite_rules_maybe', 20);
function wc_kopokopo_flush_rewrite_rules_maybe()
{
    if (get_option('wc_kopokopo_flush_rewrite_rules_flag')) {
        flush_rewrite_rules();
        delete_option('wc_kopokopo_flush_rewrite_rules_flag');
    }
}

// Redirect to configuration page when activated
add_action('activated_plugin', 'wc_kopokopo_detect_plugin_activation', 10, 2);
function wc_kopokopo_detect_plugin_activation($plugin, $network_activation)
{
    if ($plugin == 'osen-wc-kopokopo/osen-wc-kopokopo.php') {
        exit(wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=kopokopo')));
    }
}

// Deactivate plugin when WooCommerce is deactivated
add_action('deactivated_plugin', 'wc_kopokopo_detect_woocommerce_deactivation', 10, 2);
function wc_kopokopo_detect_woocommerce_deactivation($plugin, $network_activation)
{
    if ($plugin == 'woocommerce/woocommerce.php') {
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

// Flush Permalinks to avail IPN Endpoint /
register_activation_hook(__FILE__, function () {flush_rewrite_rules();});

// Add plugi links for Configuration, API Docs
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'kopokopo_action_links');
function kopokopo_action_links($links)
{
    return array_merge(
        $links,
        array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=kopokopo') . '">&nbsp;Configure</a>',
            '<a href="https://app.kopokopo.com/push_api">&nbsp;API Docs</a>',
        )
    );
}

add_action('admin_footer', function () {
    ?>
	<script src="https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js"></script>
	<script>
		var copy = document.getElementById('kopokopo_ipn_url');
    	var clipboard = new ClipboardJS(copy);

		clipboard.on('success', function(e) {
			jQuery('#kopokopo_ipn_url').after('<span style="color: green; padding-left: 2px;">Copied!</span>');
		});

		clipboard.on('error', function(e) {
			console.log(e);
		});
	</script>
	<?php
});

/*
 * Register our gateway with woocommerce
 */
add_filter('woocommerce_payment_gateways', 'kopokopo_add_to_gateways');
function kopokopo_add_to_gateways($gateways)
{
    $gateways[] = 'WC_Kopokopo_Gateway';
    return $gateways;
}

if (!function_exists('kopo_post_id_by_meta_key_and_value')) {
    function kopo_post_id_by_meta_key_and_value($key, $value)
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
}

// Define Gateway Class
add_action('plugins_loaded', 'kopokopo_init', 0);
function kopokopo_init()
{
    class WC_Kopokopo_Gateway extends WC_Payment_Gateway
    {
        public $shortcode;

        function __construct()
        {
            // global ID
            $this->id                 = "kopokopo";
            $this->method_title       = __("Lipa Na M-PESA via KopoKopo", 'woocommerce');
            $this->method_description = ($this->get_option('enabled') == 'yes')
            ? 'Receive payments using your Kopokopo Till Number'
            : __('<p>Log into your <a href="https://app.kopokopo.com" target="_blank">KopoKopo Account</a> and configure as follows:</p>
            <ol>
                <li>Go to Settings > API Settings</li>
                <li>Make sure that "Transaction Push" block is set to "HTTP POST"</li>
                <li>Configure "HTTP(S) POST Configuration"</li>
                <ol>
                    <li>Set API version to "v3"</li>
                    <li>Set Notification URL to <code title="Click to copy"><b id="kopokopo_ipn_url" data-clipboard-text="' . home_url("kopokopo_reconcile") . '">' . home_url("kopokopo_reconcile") . '</b></code></li>
                </ol>
                <li>Make sure everything is saved</li>
            </ol>
			<p>Copy your API key and paste it below</p>
			<p>Remember to <a title="' . __('Navigate to page and click Save Changes', 'woocommerce') . '" href="' . admin_url('options-permalink.php') . '">flush your rewrite rules</a>.</p>', 'woocommerce');

            // vertical tab title
            $this->title = __("Lipa Na M-PESA", 'woocommerce');

            // Add Gateway Icon
            $this->icon = apply_filters('woocommerce_mpesa_icon', plugins_url('inc/KopoKopo.png', __FILE__));

            // Set for extra checkout fields
            $this->has_fields = true;

            // Load time variable setting
            $this->init_settings();

            // Init Form fields
            $this->init_form_fields();

            // Turn these settings into variables we can use
            foreach ($this->settings as $setting_key => $value) {
                $this->$setting_key = $value;
            }

            $this->shortcode = $this->get_option('shortcode', '123456');

            // Save settings
            if (is_admin()) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            }
        }

        // Administration option fields for this Gateway
        public function init_form_fields()
        {
            $shipping_methods = array();

            foreach (WC()->shipping()->load_shipping_methods() as $method) {
                $shipping_methods[$method->id] = $method->get_method_title();
            }

            $this->form_fields = array(
                'enabled'            => array(
                    'title'   => __('Enable/Disable', 'woocommerce'),
                    'label'   => __('Enable this payment gateway', 'woocommerce'),
                    'type'    => 'checkbox',
                    'default' => 'no',
                ),
                'title'              => array(
                    'title'    => __('Method Title', 'woocommerce'),
                    'type'     => 'text',
                    'desc_tip' => __('Payment title of checkout process.', 'woocommerce'),
                    'default'  => __('Lipa Na M-PESA', 'woocommerce'),
                ),
                'shortcode'          => array(
                    'title'    => __('KopoKopo Till Number', 'woocommerce'),
                    'type'     => 'text',
                    'desc_tip' => __('This is the Till number provided by KopoKopo when you signed up for an account.', 'woocommerce'),
                    'default'  => '',
                ),
                'api_key'            => array(
                    'title'    => __('KopoKopo API Key', 'woocommerce'),
                    'type'     => 'text',
                    'desc_tip' => __('This is the API Key provided by KopoKopo from your account dashboard.', 'woocommerce'),
                    'default'  => '',
                ),
                'enable_for_methods' => array(
                    'title'             => __('Enable for shipping methods', 'woocommerce'),
                    'type'              => 'multiselect',
                    'class'             => 'wc-enhanced-select',
                    'css'               => 'width: 400px;',
                    'default'           => '',
                    'description'       => __('If M-PESA is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce'),
                    'options'           => $shipping_methods,
                    'desc_tip'          => true,
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select shipping methods', 'woocommerce'),
                    ),
                ),
                'input'              => array(
                    'title'   => __('Enable User Input', 'woocommerce'),
                    'label'   => __('Have customers manually enter transaction code during checkout.', 'woocommerce'),
                    'type'    => 'checkbox',
                    'default' => 'no',
                ),
                'enable_for_virtual' => array(
                    'title'   => __('Accept for virtual orders', 'woocommerce'),
                    'label'   => __('Accept Lipa na M-PESA if the order is virtual', 'woocommerce'),
                    'type'    => 'checkbox',
                    'default' => 'yes',
                ),
                'instructions'       => array(
                    'title'       => __('Thank You Instructions', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page.', 'woocommerce'),
                    'default'     => __('Thank you for buying from us. You will receive a confirmation message from us shortly.', 'woocommerce'),
                    'desc_tip'    => true,
                ),
            );
        }

        // Response handled for payment gateway
        public function process_payment($order_id)
        {
            $currency = get_woocommerce_currency_symbol();
            $order    = wc_get_order($order_id);
            $phone    = $order->get_billing_phone();
            $phone    = preg_replace('/^0/', '254', str_replace("+", "", $phone));
            $phone    = "+{$phone}";

            $reference = isset($_POST['reference']) ? strip_tags(trim($_POST['reference'])) : '';

            $order->update_status('pending', __('Waiting to verify M-PESA payment.', 'woocommerce'));
            $order->wc_reduce_stock_levels();
            WC()->cart->empty_cart();
            $order->add_order_note("Awaiting payment confirmation from Kopokopo");

            // Insert the payment into the database
            if ($this->get_option('input') == 'yes') {
                $post_id = kopo_post_id_by_meta_key_and_value('_reference', trim($reference));
            } else {
                $post_id = kopo_post_id_by_meta_key_and_value('_phone', trim($phone));
            }

            // global $woocommerce;
            // $order = new WC_Order($order_id);
            // if ($order !== false) {
            //     if ((int) $amount >= $order->get_total()) {
            //         $order->add_order_note(__("FULLY PAID: Payment of $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference confirmed by KopoKopo", 'woocommerce'));
            //         $order->update_status('completed');
            //     } else {
            //         $order->add_order_note(__("PARTLY PAID: Received $currency $amount from $first_name $middle_name $last_name, phone number $sender_phone and MPESA reference $transaction_reference", 'woocommerce'));
            //         $order->update_status('processing');
            //     }
            // }

            if (!$post_id) {
                $post_id = wp_insert_post(
                    array(
                        'post_title'  => 'Order ' . time(),
                        'post_status' => 'publish',
                        'post_type'   => 'kopokopo_ipn',
                        'post_author' => is_user_logged_in() ? get_current_user_id() : 1,
                    )
                );

                update_post_meta($post_id, '_order_id', $order_id);
                update_post_meta($post_id, '_phone', $phone);
                update_post_meta($post_id, '_transaction', $order_id);
                update_post_meta($post_id, '_reference', $reference);
                update_post_meta($order_id, '_mpesa_reference', $reference);
                update_post_meta($post_id, '_amount', round($amount));
                update_post_meta($post_id, '_order_status', 'on-hold');
            } else {
                update_post_meta($post_id, '_order_id', $order_id);
                $amount                = get_post_meta($post_id, '_amount', true);
                $transaction_reference = get_post_meta($post_id, '_reference', true);
                if ((int) $amount >= $order->get_total()) {
                    $order->add_order_note(__("FULLY PAID: Payment of $currency $amount from " . strip_tags(trim($_POST['phone'])) . " and MPESA reference $transaction_reference confirmed by KopoKopo", 'woocommerce'));
                    $order->update_status('completed');
                } else {
                    $order->add_order_note(__("PARTLY PAID: Received $currency $amount from " . strip_tags(trim($_POST['phone'])) . " and MPESA reference $transaction_reference", 'woocommerce'));
                    $order->update_status('processing');
                }
            }

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        public function payment_fields()
        {?>
			<p class="form-row form-row-wide">
				<?php _e('On your Safaricom phone go the M-PESA menu.', 'woocommerce');?><br>
				<?php _e('Select Lipa Na M-PESA and then Buy Goods and Services', 'woocommerce');?><br>
				<?php _e('Enter the Till Number <b>' . $this->shortcode . '</b>', 'woocommerce');?><br>
				<?php _e('Enter exactly <b>' . round(WC()->cart->total) . '</b> as the amount due', 'woocommerce');?><br>
				<?php _e('Follow subsequent prompts to complete the transaction.', 'woocommerce');?><br>
                <?php if ($this->get_option('input', 'no') == 'yes'): ?>
                    <?php _e('You will receive an SMS from M-PESA with a Confirmation Code.', 'woocommerce');?><br>
                    <?php _e('Please input the Confirmation Code below.', 'woocommerce');?><br><br>

                    <input class="input-text form-control" required="required" name="reference" type="text" autocomplete="off" placeholder="Enter Code e.g NCE6UUNJS6">
                <?php endif;?>
			</p><?php
}

        // Validate OTP
        public function validate_fields()
        {
            // if (empty($_POST['reference'])) {
            //     wc_add_notice('Confirmation Code is required!', 'error');
            //     return false;
            // }

            return true;
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page()
        {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

    }
}

/**
 * Load Extra Plugin Functions
 */
foreach (glob(plugin_dir_path(__FILE__) . 'inc/*.php') as $filename) {
    require_once $filename;
}

/**
 * Load Custom Post Type (KopoKopo Payments) Functionality
 */
foreach (glob(plugin_dir_path(__FILE__) . 'cpt/*.php') as $filename) {
    require_once $filename;
}
