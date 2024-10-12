<?php
/**
 *  MMG Checkout for WooCommerce Activator
 *
 * This file contains the Kalpa_MMG_Checkout_Activator class and activation function.
 *
 * @package Kalpa_MMG_Checkout_Main
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 1.0.0
 * @package Kalpa_MMG_Checkout_Main
 * @author Kalpa Services Inc. <info@kalpa.dev>
 */
class Kalpa_MMG_Checkout_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		self::mmg_activate();
		add_action( 'init', array( 'self', 'add_rewrite_rules' ) );
	}

	/**
	 * Perform activation tasks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function mmg_activate() {
		self::add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Add rewrite rules for  MMG Checkout for WooCommerce callbacks.
	 *
	 * This function adds a rewrite rule to handle  MMG Checkout for WooCommerce callbacks
	 * through a custom endpoint.
	 */
	private static function add_rewrite_rules() {
		add_rewrite_rule(
			'^wc-api/mmg-checkout/([^/]+)/?$',
			'index.php?mmg-checkout=1&callback_key=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^wc-api/mmg-checkout/([^/]+)/errorpayment/?$',
			'index.php?mmg-checkout=errorpayment&callback_key=$matches[1]',
			'top'
		);
		add_rewrite_tag( '%mmg-checkout%', '([^&]+)' );
	}
}
