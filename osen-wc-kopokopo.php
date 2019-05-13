<?php
/**
 * @package KopoKopo For WooCommerce
 * @subpackage Plugin File
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.19.04
 *
 * Plugin Name: KopoKopo for WordPress
 * Plugin URI:  https://kopokopo.org
 * Description: WordPress Plugin to integrate KopoKopo Payments
 * Version:     0.19.04
 * Author:      Mauko Maunde
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
			'<a href="https://kopokopo.herokuapp.com/kopokopo/swagger-ui.html">&nbsp;API Docs</a>' 
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
            </ol>', 'kopokopo');

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

			$this->shortcode = $this->get_option('shortcode');
			
			// Save settings
			if (is_admin()) {
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			}		
		} 

		// Administration option fields for this Gateway
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' 			=> array(
					'title'			=> __('Enable / Disable', 'kopokopo'),
					'label'			=> __('Enable this payment gateway', 'kopokopo'),
					'type'			=> 'checkbox',
					'default'		=> 'no',
				),
				'title' 			=> array(
					'title'			=> __('Title', 'kopokopo'),
					'type'			=> 'text',
					'desc_tip'		=> __('Payment title of checkout process.', 'kopokopo'),
					'default'		=> __('Lipa Na MPESA', 'kopokopo'),
				),
				'shortcode' 		=> array(
					'title'			=> __('KopoKopo Shortcode', 'kopokopo'),
					'type'			=> 'text',
					'desc_tip'		=> __('This is the Shortcode provided by KopoKopo when you signed up for an account.', 'kopokopo'),
					'default' 		=> '06166'
				),
				'apiKey' 			=> array(
					'title'			=> __('KopoKopo API Key', 'kopokopo'),
					'type'			=> 'text',
					'desc_tip'		=> __('This is the API Key provided by KopoKopo when you signed up for an account.', 'kopokopo'),
					'default' 		=> 'CANN4N9UGFHH1E7CX41T'
				),
				'description' 		=> array(
					'title'			=> __('Checkout Instructions', 'kopokopo'),
					'type'			=> 'textarea',
					'desc_tip'		=> __('Payment method description that the customer will see on your checkout.', 'kopokopo'),
					'default'		=> '<p>
		        ' . __('On your Safaricom phone go the M-PESA menu', 'kopokopo') . '</br>
		        ' . __('Select Lipa Na M-PESA and then select Buy Goods and Services', 'kopokopo') . '</br>
		        ' . __('Enter the Till Number', 'kopokopo') . ' <strong>' . $this->shortcode . '</strong> </br>
		        ' . __('Enter exactly the amount due', 'kopokopo') . '</br>
		        ' . __('Follow subsequent prompts to complete the transaction.', 'kopokopo') . ' </br>
		        ' . __('You will receive a confirmation SMS from M-PESA with a Confirmation Code.', 'kopokopo') . ' </br>
		        ' . __('After you receive the confirmation code, please input your phone number and the confirmation code that you received from M-PESA below.', 'kopokopo') . '</br></p>', 'kopokopo',
					'css'			=> 'max-width:100%; height: 200px;'
				),
				'instructions' 		=> array(
					'title'       	=> __('Thank You Instructions', 'woocommerce'),
					'type'       	=> 'textarea',
					'description'	=> __('Instructions that will be added to the thank you page.', 'woocommerce'),
					'default'     	=> __('Thank you for buying from us. You will receive a confirmation message from KopoKopo shortly.', 'woocommerce'),
					'desc_tip'    	=> true,
				)
			);		
		}
		
		// Response handled for payment gateway
		public function process_payment($order_id) {
			global $woocommerce;


		}

		public function payment_fields() {

			// ok, let's display some description before the payment form
			if ($this->description) { 
				echo 'Till Number: <code>'. $this->get_option('shortcode'); ?></code></br><?php
				echo wpautop(wp_kses_post($this->description)); ?>
				<div class="form-row form-row-full">
					<input required="required" id="misha_expdate" name="reference" type="text" autocomplete="off" placeholder="Enter Code e.g NCE6UUNJS6">
				</div>
				<div class="clear"></div>
				<?php
			}
		}
		
		// Validate OTP
		public function validate_fields() {

			if(empty($_POST[ 'reference' ])) {
				wc_add_notice( 'Transaction Code is required!', 'error');
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