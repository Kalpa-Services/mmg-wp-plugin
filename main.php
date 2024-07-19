<?php
/*
Plugin Name: MMG Checkout Payment
Description: Enables MMG Checkout Payment flow for registered MMG Merchants to receive E-Commerce payments from MMG customers.
Version: 1.1.7
Author: Kalpa Services Inc.
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
// Load composer autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
// Include the dependency checker
require_once plugin_dir_path(__FILE__) . 'includes/class-mmg-dependency-checker.php';

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