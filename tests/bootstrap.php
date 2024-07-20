<?php
/**
 * PHPUnit bootstrap file for MMG Checkout Payment plugin
 */

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load the WordPress test environment
$wordpress_tests_path = '/home/runner/wordpress-tests/wordpress-develop/tests/phpunit';
if (!file_exists($wordpress_tests_path . '/includes/functions.php')) {
    die('WordPress test environment not found at ' . $wordpress_tests_path);
}
require_once $wordpress_tests_path . '/includes/functions.php';

// Check if WP_PHPUNIT__DIR is set, if not set it
$wp_phpunit_dir = getenv('WP_PHPUNIT__DIR');
if (!$wp_phpunit_dir) {
    $wp_phpunit_dir = $wordpress_tests_path;
    putenv('WP_PHPUNIT__DIR=' . $wp_phpunit_dir);
}

// Check if the bootstrap file exists
$bootstrap_file = $wp_phpunit_dir . '/includes/bootstrap.php';
if (!file_exists($bootstrap_file)) {
    die('The bootstrap file was not found at ' . $bootstrap_file);
}

// Load WooCommerce
$woocommerce_path = dirname(__DIR__) . '/vendor/woocommerce/woocommerce/woocommerce.php';
if (file_exists($woocommerce_path)) {
    require_once $woocommerce_path;
} else {
    die('WooCommerce plugin not found. Make sure it is installed via Composer.');
}

// Start up the WP testing environment.
require $bootstrap_file;

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