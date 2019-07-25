<?php
/**
 * @package KopoKopo For WooCommerce
 * @subpackage Plugin File
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.19.04
 *
 * Plugin Name: KopoKopo for WooCommerce
 * Plugin URI:  https://kopokopo.org
 * Description: WordPress Plugin to integrate KopoKopo Payments
 * Version:     0.19.04
 * Author:      Osen Concepts
 * Author URI:  https://osen.co.ke/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: osen
 * Domain Path: /languages
 */

// Exit if accessed directly.
if (!defined('ABSPATH')){
	exit;
}

// Define plugin constants
define('KP_VER', '1.19.0');
if (!defined('KP_PLUGIN_FILE')) {
	define('KP_PLUGIN_FILE', __FILE__);
}

// Deactivate plugin if WooCommerce is not active
register_activation_hook(__FILE__, 'wc_kopokopo_activation_check');
function wc_kopokopo_activation_check() 
{
	if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
		deactivate_plugins(plugin_basename(__FILE__));
		exit('Please Install/Activate WooCommerce for the KopoKopo extension to work');
	}

	if (!is_plugin_active('woocommerce/woocommerce.php')){
		deactivate_plugins(plugin_basename(__FILE__));
	}
}

// Redirect to configuration page when activated
add_action('activated_plugin', 'wc_kopokopo_detect_plugin_activation', 10, 2);
function wc_kopokopo_detect_plugin_activation($plugin, $network_activation) {
	if($plugin == 'osen-wc-kopokopo/osen-wc-kopokopo.php'){
		exit(wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=kopokopo')));
	}
}

// Deactivate plugin when WooCommerce is deactivated
add_action('deactivated_plugin', 'wc_kopokopo_detect_woocommerce_deactivation', 10, 2);
function wc_kopokopo_detect_woocommerce_deactivation($plugin, $network_activation)
{
	if ($plugin == 'woocommerce/woocommerce.php'){
		deactivate_plugins(plugin_basename(__FILE__));
	}
}

// Flush Permalinks to avail IPN Endpoint /
register_activation_hook(__FILE__, function (){ flush_rewrite_rules(); });

// Add plugi links for Configuration, API Docs
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'kopokopo_action_links');
function kopokopo_action_links($links)
{
	return array_merge(
		$links, 
		array(
			'<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=kopokopo').'">&nbsp;Configure</a>', 
			'<a href="https://app.kopokopo.com/push_api">&nbsp;API Docs</a>' 
		)
	);
}

/*
 * Register our gateway with woocommerce
 */
add_filter('woocommerce_payment_gateways', 'kopokopo_add_to_gateways');
function kopokopo_add_to_gateways($gateways)
{
	$gateways[] = 'WC_Kopokopo_Gateway';
	return $gateways;
}

// Define Gateway Class
add_action('plugins_loaded', 'kopokopo_init', 0);
function kopokopo_init() {
	class WC_Kopokopo_Gateway extends WC_Payment_Gateway {
		public $shortcode;

		function __construct() {

			// global ID
			$this->id = "kopokopo";

			// Show Title
			$this->method_title = __("KopoKopo Lipa Na MPESA", 'kopokopo');

			// Show Description
			$this->method_description = __('<p>Log into your <a href="https://app.kopokopo.com" target="_blank">KopoKopo Account</a> and configure as follows:</p>
            <ol>
                <li>Go to Settings > API Settings</li>
                <li>Make sure that "Transaction Push" block is set to "HTTP POST"</li>
                <li>Configure "HTTP(S) POST Configuration"</li>
                <ol>
                    <li>Set API version to "v3"</li>
                    <li>Set Notification URL to <code><b>'. home_url("kopokopo_reconcile") .'</b></code></li>
                </ol>
                <li>Make sure everything is saved</li>
            </ol>
			<p>Copy your API key and paste it below</p>', 'kopokopo');

			// vertical tab title
			$this->title = __("Lipa Na MPESA", 'kopokopo');

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
		public function init_form_fields() {
			$shipping_methods = array();

			foreach (WC()->shipping()->load_shipping_methods() as $method){
				$shipping_methods[ $method->id ] = $method->get_method_title();
			}

			$this->form_fields = array(
				'enabled' 			=> array(
					'title'			=> __('Enable / Disable', 'kopokopo'),
					'label'			=> __('Enable this payment gateway', 'kopokopo'),
					'type'			=> 'checkbox',
					'default'		=> 'no',
				),
				'title' 			=> array(
					'title'			=> __('Method Title', 'kopokopo'),
					'type'			=> 'text',
					'desc_tip'		=> __('Payment title of checkout process.', 'kopokopo'),
					'default'		=> __('Lipa Na MPESA(KopoKopo)', 'kopokopo'),
				),
				'shortcode' 		=> array(
					'title'			=> __('KopoKopo Shortcode', 'kopokopo'),
					'type'			=> 'text',
					'desc_tip'		=> __('This is the Shortcode provided by KopoKopo when you signed up for an account.', 'kopokopo'),
					'default' 		=> '123456'
				),
				'api_key' 			=> array(
					'title'			=> __('KopoKopo API Key', 'kopokopo'),
					'type'			=> 'text',
					'desc_tip'		=> __('This is the API Key provided by KopoKopo when you signed up for an account.', 'kopokopo'),
					'default' 		=> '05a9907dec40e9a24b693a53f04a77e83329048e'
				),
				'enable_for_methods' => array(
					'title'             => __('Enable for shipping methods', 'woocommerce'),
					'type'              => 'multiselect',
					'class'             => 'wc-enhanced-select',
					'css'               => 'width: 400px;',
					'default'           => '',
					'description'       => __('If MPesa is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce'),
					'options'           => $shipping_methods,
					'desc_tip'          => true,
					'custom_attributes' => array(
						'data-placeholder' => __('Select shipping methods', 'woocommerce'),
					),
				),
                'enable_for_virtual' => array(
                    'title' => __('Accept for virtual orders', 'woocommerce'),
                    'label' => __('Accept KopoKopo Lipa na MPESA if the order is virtual', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'yes'
                ),
				'instructions' 		=> array(
					'title'       	=> __('Thank You Instructions', 'woocommerce'),
					'type'       	=> 'textarea',
					'description'	=> __('Instructions that will be added to the thank you page.', 'woocommerce'),
					'default'     	=> __('Thank you for buying from us. You will receive a confirmation message from us shortly.', 'woocommerce'),
					'desc_tip'    	=> true,
				),
			);		
		}
		
		// Response handled for payment gateway
		public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order->update_status('pending', __('Waiting to verify MPESA payment.', 'woocommerce'));
            $order->reduce_order_stock();
            WC()->cart->empty_cart();
            $order->add_order_note("Awaiting payment confirmation from " . $_POST['mpesa_phone']);
            // Insert the payment into the database
	        
	        $post_id = wp_insert_post( 
	            array(
	                'post_title'    => 'Order '.time(),
	                'post_status'   => 'publish',
	                'post_type'     => 'kopokopo_ipn',
	                'post_author'   => is_user_logged_in() ? get_current_user_id() : 1,
	            ) 
	        );

            update_post_meta($post_id, '_order_id', $order_id );
			update_post_meta($post_id, '_transaction', $order_id );
            update_post_meta($post_id, '_reference', $_POST['reference']);
            update_post_meta($post_id, '_amount', round($amount));

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
		}

		public function payment_fields() { ?>
			<p class="form-row form-row-wide">
					On your Safaricom phone go the M-PESA menu.<br>
					Select Lipa Na M-PESA and then select Buy Goods and Services<br>
					Enter the Till Number <b><?php echo $this->shortcode; ?></b><br>
					Enter exactly <b><?php echo round(WC()->cart->total); ?></b> as the amount due<br>
					Follow subsequent prompts to complete the transaction.<br>
					You will receive a confirmation SMS from M-PESA with a Confirmation Code.<br>
					Please input the confirmation code below.<br><br>

				<input class="input-text" required="required" name="reference" type="text" autocomplete="off" placeholder="Enter Code e.g NCE6UUNJS6">
			</p><?php
		}
		
		// Validate OTP
		public function validate_fields() {

			if(empty($_POST[ 'reference' ])) {
				wc_add_notice( 'Confirmation Code is required!', 'error');
				return false;
			}

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