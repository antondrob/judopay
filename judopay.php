<?php
/*
 * Plugin Name: JudoPay
 * Description: JudoPay payment gateway for WooCommerce
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Author:            OnePix
 * Author URI:        https://one-pix.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-judopay
 * Domain Path:       /languages
 */

namespace JudoPay;
class Init
{
    public static $plugin_path;

    public function __construct()
    {
        self::$plugin_path = plugin_dir_path(__FILE__);
        add_filter('woocommerce_payment_gateways', [$this, 'add_judopay_class']);
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            add_action('plugins_loaded', [$this, 'include_class']);
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);

        } else {
            add_action('admin_notices', [$this, 'judopay_notice']);
        }
    }

    public function add_settings_link($links)
    {
        $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=judopay');
        $settings_link = '<a href="' . $url . '">' . __('Settings', 'wc-judopay') . '</a>';
        $links[] = $settings_link;
        return $links;
    }

    public function add_judopay_class($gateways)
    {
        $gateways[] = 'WC_Judopay_Gateway';
        return $gateways;
    }

    public function include_class()
    {
        require_once('includes/class-wc-judopay.php');
    }

    public function judopay_notice()
    {
        ?>
        <div class="notice notice-error">
            <p>Judopay needs WooCommerce to be active.</p>
        </div>
        <?php
    }

}

new Init();