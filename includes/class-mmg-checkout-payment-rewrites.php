<?php
/**
 * MMG Checkout Payment Rewrites
 *
 * Handles rewrite rules for MMG Checkout payment callbacks.
 *
 * @package MMG_Checkout
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class MMG_Checkout_Payment_Rewrites {
	/**
	 * Initialize the rewrite rules.
	 *
	 * Hooks into WordPress init action to register the rewrite rules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
	}

	/**
	 * Add rewrite rules for MMG Checkout payment callbacks.
	 *
	 * Registers rewrite rules to handle payment callback URLs and adds a custom rewrite tag.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule(
			'^wc-api/mmg-checkout/([^/]+)/?',
			'index.php?mmg-checkout=1&callback_key=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^wc-api/mmg-checkout/([^/]+)/errorpayment/?',
			'index.php?mmg-checkout=errorpayment&callback_key=$matches[1]',
			'top'
		);
		add_rewrite_tag( '%mmg-checkout%', '([^&]+)' );
	}
}
