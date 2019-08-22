<?php
/**
 * @package KopoKopo For WooCommerce
 * @subpackage Plugin Menus
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

// Add admin menus for plugin actions
add_action('admin_menu', 'kopokopo_transactions_menu');
function kopokopo_transactions_menu()
{
    add_submenu_page(
        'edit.php?post_type=kopokopo_ipn', 
        __('About this Plugin', 'woocommerce'), 
        __('About Plugin', 'woocommerce'), 
        'manage_options',
        'kopokopo_about', 
        'kopokopo_transactions_menu_about' 
   );

    add_submenu_page(
        'edit.php?post_type=kopokopo_ipn', 
        'KopoKopo Configuration',
        'Configuration', 
        'manage_options', 
        'kopokopo_options',
        'kopokopo_transactions_menu_pref'
   );
}

// About plugin
function kopokopo_transactions_menu_about()
{ ?>
    <div class="wrap">
        <h1><?php _e('About KopoKopo for WooCommerce', 'woocommerce'); ?></h1>

        <img src="<?php echo apply_filters('woocommerce_mpesa_icon', plugins_url('KopoKopo.png', __FILE__)); ?>" width="100px">

        <h3><?php _e('The Plugin', 'woocommerce'); ?></h3>
        <article>
            <p><?php _e('This plugin aims to provide a simple plug-n-play implementation for integrating M-PESA Payments processed by KopoKopo into online stores built with WooCommerce and WordPress.', 'woocommerce'); ?></p>
        </article>

        <h3><?php _e('Integration', 'woocommerce'); ?></h3>
        <article>
            <p><?php echo __('Log into your <a href="https://app.kopokopo.com" target="_blank">KopoKopo Account</a> and configure as follows:', 'woocommerce'); ?></p>
            <ol>
                <li><?php _e('Go to Settings > API Settings', 'woocommerce'); ?></li>
                <li><?php _e('Make sure that "Transaction Push" block is set to "HTTP POST"', 'woocommerce'); ?></li>
                <li><?php _e('Configure "HTTP(S) POST Configuration"', 'woocommerce'); ?></li>
                <ol>
                    <li><?php _e('Set API version to "v3"', 'woocommerce'); ?></li>
                    <li><?php _e('Set Notification URL to', 'woocommerce'); ?> <code><b id="kopokopo_ipn_url" data-clipboard-text="<?php echo home_url("kopokopo_reconcile"); ?>"><?php echo home_url("kopokopo_reconcile"); ?></b></code></li>
                </ol>
                <li><?php _e('Make sure everything is saved', 'woocommerce'); ?></li>
            </ol>
            <p><?php _e('Copy your API key and paste it on the <a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=kopokopo').'">settings page</a></p>', 'kopokopo'); ?>
            <p>Remember to <a title="<?php _e('Navigate to page and click Save Changes', 'woocommerce') ?>" href="<?php echo admin_url('options-permalink.php'); ?>"><?php _e('flush your rewrite rules', 'woocommerce'); ?></a>.</p>
        </article>

        <h3><?php _e('Contact', 'woocommerce'); ?></h3>
        <h4>Get in touch with us either via email (<a href="mail-to:hi@osen.co.ke">hi@osen.co.ke</a>) or via phone(<a href="tel:+254204404993">+254204404993</a>)</h4>
    </div><?php
}

// Redirect to plugin configuration page
function kopokopo_transactions_menu_pref()
{
    wp_redirect(
        admin_url(
            'admin.php?page=wc-settings&tab=checkout&section=kopokopo'
        )
    );
}
