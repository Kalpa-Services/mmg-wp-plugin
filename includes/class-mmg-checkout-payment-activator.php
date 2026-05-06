<?php
/**
 * MMG Checkout Payment Activator
 *
 * This file contains the MMG_Checkout_Payment_Activator class and activation function.
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
 * @package MMG_Checkout_Payment
 * @author Kalpa Services Inc. <info@kalpa.dev>
 */
class MMG_Checkout_Payment_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		self::mmg_activate();
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
		// Signal admin_init to redirect to the settings page on next load.
		// Skipped during bulk activation (WordPress won't redirect in that case anyway,
		// but we guard here so the transient is not consumed on the wrong request).
		if ( ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			set_transient( 'mmg_activation_redirect', true, 30 );
		}
	}

	/**
	 * Add rewrite rules for MMG Checkout Payment callbacks.
	 *
	 * This function adds a rewrite rule to handle MMG Checkout Payment callbacks
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
