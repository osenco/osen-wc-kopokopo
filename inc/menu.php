<?php
/**
 * @package KopoKopo For WooCommerce
 * @subpackage Plugin Menus
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

// Add admin menus for plugin actions
add_action( 'admin_menu', 'kopokopo_transactions_menu' );
function kopokopo_transactions_menu()
{

    add_submenu_page( 
        'edit.php?post_type=kopokopo_ipn', 
        'About this Plugin', 
        'About Plugin', 
        'manage_options',
        'kopokopo_about', 
        'kopokopo_transactions_menu_about' 
    );

    // add_submenu_page( 
    //     'kopokopokopokopo', 
    //     'KopoKopo Payments Analytics', 
    //     'Analytics', 
    //     'manage_options',
    //     'kopokopo_analytics', 
    //     'kopokopo_transactions_menu_analytics' 
    // );

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
        <h1>About KopoKopo for WooCommerce</h1>

        <img src="<?php echo apply_filters('woocommerce_mpesa_icon', plugins_url('KopoKopo.png', __FILE__)); ?>" width="400px">

        <h3>The Plugin</h3>
        <article>
            <p>This plugin aims to provide a simple plug-n-play implementation for integrating MPesa Payments processed by KopoKopo into online stores built with WooCommerce and WordPress.</p>
        </article>

        <h3>Integration</h3>
        <article>
            <?php echo __('<p>Log into your <a href="https://app.kopokopo.com" target="_blank">KopoKopo Account</a> and configure as follows:</p>
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
            <p>Copy your API key and paste it on the <a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=kopokopo').'">settings page</a></p>', 'kopokopo'); ?>
        </article>

        <h3>Contact</h3>
        <h4>Get in touch with us either via email ( <a href="mail-to:hi@osen.co.ke">hi@osen.co.ke</a> ) or via phone( <a href="tel:+254204404993">+254204404993</a> )</h4>
        </div><?php
    }

// Redirect to plugin configuration page
    function kopokopo_transactions_menu_pref()
    {
        wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=kopokopo' ) );
    }
