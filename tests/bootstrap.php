<?php
/**
 * PHPUnit bootstrap file for MMG Checkout Payment plugin
 */

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load the WordPress test environment
$wordpress_tests_path = getenv('WP_TESTS_PATH');
if (!$wordpress_tests_path) {
    die('WP_TESTS_PATH environment variable is not set.');
}
require_once $wordpress_tests_path . '/includes/functions.php';

// Start up the WP testing environment.
require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';

// Activate WooCommerce
activate_plugin( 'woocommerce/woocommerce.php' );

// Load MMG Checkout Payment plugin dependencies
require_once dirname( __DIR__ ) . '/includes/class-mmg-dependency-checker.php';
require_once dirname( __DIR__ ) . '/includes/class-mmg-checkout-payment.php';
require_once dirname( __DIR__ ) . '/includes/class-mmg-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-mmg-gateway.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-mmg-payments-blocks.php';

// Initialize the plugin for testing
function _init_mmg_checkout_payment() {
    new MMG_Checkout_Payment();
}
tests_add_filter( 'plugins_loaded', '_init_mmg_checkout_payment' );