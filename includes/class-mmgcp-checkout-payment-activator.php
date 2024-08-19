<?php
/**
 * MMG Checkout Payment Activator
 *
 * This file contains the MMGCP_Checkout_Payment_Activator class and activation function.
 *
 * @package MMG_Checkout_Payment
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
 * @package MMGCP_Checkout_Payment
 * @author Kalpa Services Inc. <info@kalpa.dev>
 */
class MMGCP_Checkout_Payment_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		self::mmgcp_activate();
	}

	/**
	 * Perform activation tasks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function mmgcp_activate() {
		self::mmgcp_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Add rewrite rules for MMG Checkout Payment callbacks.
	 *
	 * This function adds a rewrite rule to handle MMG Checkout Payment callbacks
	 * through a custom endpoint.
	 */
	private static function mmgcp_rewrite_rules() {
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
