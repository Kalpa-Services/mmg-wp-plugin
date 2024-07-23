<?php
/**
 * Plugin Name: MMG Checkout Payment
 *
 * @package           MMG Checkout Payment
 * @author            Kalpa Services Inc.
 * @copyright         2024 Kalpa Services Inc.
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       MMG Checkout Payment
 * Plugin URI:        https://github.com/Kalpa-Services/mmg-wp-plugin
 * Description:       Enables MMG Checkout Payment flow for registered MMG Merchants to receive E-Commerce payments from MMG customers.
 * Version:           1.1.15
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Kalpa Services Inc.
 * Author URI:        https://kalpa.dev
 * Text Domain:       mmg-checkout-payment
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://github.com/Kalpa-Services/mmg-wp-plugin
 * Requires Plugins:  woocommerce
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin version constant
define('MMG_PLUGIN_VERSION', '1.1.8');

// Load composer autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
// Include the dependency checker
require_once plugin_dir_path(__FILE__) . 'includes/class-mmg-dependency-checker.php';
// Include the activator class
require_once plugin_dir_path(__FILE__) . 'includes/class-mmg-checkout-payment-activator.php';
// Include the deactivator class
require_once plugin_dir_path(__FILE__) . 'includes/class-mmg-checkout-payment-deactivator.php';

// Check dependencies before initializing the plugin
if (MMG_Dependency_Checker::check_dependencies()) {
    // Initialize the plugin
    function mmg_checkout_init() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-mmg-checkout-payment.php';
        new MMG_Checkout_Payment();
    }
    add_action('plugins_loaded', 'mmg_checkout_init');
}

add_action('woocommerce_blocks_loaded', 'mmg_checkout_register_block_support');

function mmg_checkout_register_block_support() {
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-mmg-payments-blocks.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_MMG_Payments_Blocks());
            }
        );
    }
}

function mmg_add_rewrite_rules() {
    add_rewrite_rule(
        '^wc-api/mmg-checkout/([^/]+)/?$',
        'index.php?mmg-checkout=1&callback_key=$matches[1]',
        'top'
    );
}
add_action('init', 'mmg_add_rewrite_rules');

function mmg_query_vars($vars) {
    $vars[] = 'mmg-checkout';
    $vars[] = 'callback_key';
    return $vars;
}
add_filter('query_vars', 'mmg_query_vars');

// Also, add this to ensure rules are flushed on plugin update
function mmg_plugin_updated() {
    $version = get_option('mmg_plugin_version', '0');
    if (version_compare(MMG_PLUGIN_VERSION, $version, '>')) {
        flush_rewrite_rules();
        update_option('mmg_plugin_version', MMG_PLUGIN_VERSION);
    }
}
add_action('plugins_loaded', 'mmg_plugin_updated');

function mmg_remove_gateway($gateways) {
    unset($gateways['mmg_checkout']);
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'mmg_remove_gateway', 20);

// Initialize WooCommerce API endpoint
add_action('init', function() {
    add_rewrite_endpoint('mmg-checkout', EP_ALL);
});

// Move this line to the end of the file
register_activation_hook(__FILE__, array('MMG_Checkout_Payment_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('MMG_Checkout_Payment_Deactivator', 'deactivate'));