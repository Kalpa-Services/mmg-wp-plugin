<?php
/*
Plugin Name: MMG Checkout Payment
Description: Enables MMG Checkout Payment flow for registered MMG Merchants to receive E-Commerce payments from MMG customers.
Version: 1.0
Author: Kalpa Services Inc.
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

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