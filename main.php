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
 * Plugin URI:        https://mmg-wp-plugin-docs.pages.dev
 * Description:       Enables MMG Checkout Payment flow for registered MMG Merchants to receive E-Commerce payments from MMG customers.
 * Version:           2.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kalpa Services Inc.
 * Author URI:        https://kalpa.dev
 * Text Domain:       mmg-checkout-payment
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MMGCP_PLUGIN_VERSION', '2.0.0' );
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mmgcp-dependency-checker.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mmgcp-checkout-payment-activator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mmgcp-checkout-payment-deactivator.php';

if ( MMGCP_Dependency_Checker::mmgcp_check_dependencies() ) {
	/**
	 * Initialize the MMG Checkout Payment functionality.
	 *
	 * This function is called when all plugins are loaded and dependencies are met.
	 * It includes the main plugin class and instantiates it.
	 */
	function mmgcp_checkout_init() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-mmgcp-checkout-payment.php';
		new MMGCP_Checkout_Payment();
	}
	add_action( 'plugins_loaded', 'mmgcp_checkout_init' );
}

/**
 * Register MMG Checkout Payment support for WooCommerce Blocks.
 *
 * This function checks if the WooCommerce Blocks abstract payment method class exists,
 * and if so, registers the MMG Payments Block support.
 */
function mmgcp_checkout_register_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-mmgcp-payments-blocks.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new MMGCP_Payments_Blocks() );
			}
		);
	}
}
add_action( 'woocommerce_blocks_loaded', 'mmgcp_checkout_register_block_support' );
/**
 * Add custom query variables for MMG Checkout Payment.
 *
 * @param array $vars The array of existing query variables.
 * @return array The updated array of query variables.
 */
function mmgcp_query_vars( $vars ) {
	$vars[] = 'mmg-checkout';
	$vars[] = 'callback_key';
	return $vars;
}
add_filter( 'query_vars', 'mmgcp_query_vars' );

/**
 * Handle plugin updates.
 *
 * This function checks if the plugin version has changed and performs
 * necessary actions like flushing rewrite rules and updating the version option.
 */
function mmgcp_plugin_updated() {
	$version = get_option( 'mmgcp_plugin_version', '0' );
	if ( version_compare( MMGCP_PLUGIN_VERSION, $version, '>' ) ) {
		flush_rewrite_rules();
		update_option( 'mmgcp_plugin_version', MMGCP_PLUGIN_VERSION );
	}
}
add_action( 'plugins_loaded', 'mmgcp_plugin_updated' );

/**
 * Remove the MMG Checkout Payment gateway from the list of available gateways.
 *
 * @param array $gateways The array of registered payment gateways.
 * @return array The updated array of payment gateways.
 */
function mmgcp_remove_gateway( $gateways ) {
	unset( $gateways['mmg_checkout'] );
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'mmgcp_remove_gateway', 20 );

add_action(
	'init',
	function () {
		add_rewrite_endpoint( 'mmg-checkout', EP_ALL );
	}
);

register_activation_hook( __FILE__, array( 'MMGCP_Checkout_Payment_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MMGCP_Checkout_Payment_Deactivator', 'deactivate' ) );
