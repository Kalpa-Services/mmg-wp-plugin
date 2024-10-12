<?php
/**
 * Plugin Name:  MMG Checkout for WooCommerce
 *
 * @package            MMG Checkout for WooCommerce
 * @author            Kalpa Services Inc.
 * @copyright         2024 Kalpa Services Inc.
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:        MMG Checkout for WooCommerce
 * Plugin URI:        https://mmg-plugin.kalpa.dev
 * Description:       Enables  MMG Checkout for WooCommerce flow for registered MMG Merchants to receive E-Commerce payments from MMG customers.
 * Version:           2.1.7
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kalpa Services Inc.
 * Author URI:        https://kalpa.dev
 * Text Domain:       kalpa-mmg-checkout
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MMG_PLUGIN_VERSION', '2.0.0' );
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-kalpa-mmg-checkout-dependency-checker.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-kalpa-mmg-checkout-activator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-kalpa-mmg-checkout-deactivator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-kalpa-mmg-checkout-deactivator.php';
// This is temporary until the plugin is uploaded to the WordPress repository.
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/Kalpa-Services/mmg-wp-plugin/',
	__FILE__,
	'kalpa-mmg-checkout'
);
$update_checker->getVcsApi()->enableReleaseAssets();

if ( Kalpa_MMG_Checkout_Dependency_Checker::check_dependencies() ) {
	/**
	 * Initialize the  MMG Checkout for WooCommerce functionality.
	 *
	 * This function is called when all plugins are loaded and dependencies are met.
	 * It includes the main plugin class and instantiates it.
	 */
	function mmg_checkout_init() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-kalpa-mmg-checkout-main.php';
		new Kalpa_MMG_Checkout_Main();
	}
	add_action( 'plugins_loaded', 'mmg_checkout_init' );
}

add_action( 'woocommerce_blocks_loaded', 'mmg_checkout_register_block_support' );

/**
 * Register  MMG Checkout for WooCommerce support for WooCommerce Blocks.
 *
 * This function checks if the WooCommerce Blocks abstract payment method class exists,
 * and if so, registers the MMG Payments Block support.
 */
function mmg_checkout_register_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-kalpa-mmg-checkout-payments-blocks.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new Kalpa_MMG_Checkout_Payments_Blocks() );
			}
		);
	}
}
/**
 * Add custom query variables for  MMG Checkout for WooCommerce.
 *
 * @param array $vars The array of existing query variables.
 * @return array The updated array of query variables.
 */
function mmg_query_vars( $vars ) {
	$vars[] = 'mmg-checkout';
	$vars[] = 'callback_key';
	return $vars;
}
add_filter( 'query_vars', 'mmg_query_vars' );

/**
 * Handle plugin updates.
 *
 * This function checks if the plugin version has changed and performs
 * necessary actions like flushing rewrite rules and updating the version option.
 */
function mmg_plugin_updated() {
	$version = get_option( 'mmg_plugin_version', '0' );
	if ( version_compare( MMG_PLUGIN_VERSION, $version, '>' ) ) {
		flush_rewrite_rules();
		update_option( 'mmg_plugin_version', MMG_PLUGIN_VERSION );
	}
}
add_action( 'plugins_loaded', 'mmg_plugin_updated' );

/**
 * Remove the  MMG Checkout for WooCommerce gateway from the list of available gateways.
 *
 * @param array $gateways The array of registered payment gateways.
 * @return array The updated array of payment gateways.
 */
function mmg_remove_gateway( $gateways ) {
	unset( $gateways['mmg_checkout'] );
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'mmg_remove_gateway', 20 );

add_action(
	'init',
	function () {
		add_rewrite_endpoint( 'mmg-checkout', EP_ALL );
	}
);

register_activation_hook( __FILE__, array( 'Kalpa_MMG_Checkout_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Kalpa_MMG_Checkout_Deactivator', 'deactivate' ) );
