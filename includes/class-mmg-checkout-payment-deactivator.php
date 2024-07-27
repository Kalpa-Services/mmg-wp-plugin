<?php
/**
 * MMG Checkout Payment Deactivator
 *
 * This file contains the MMG_Checkout_Payment_Deactivator class and deactivation function.
 *
 * @package MMG_Checkout_Payment
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation
 *
 * @since      1.0.0
 * @package    MMG Checkout Payment
 * @author     Kalpa Services Inc. <info@kalpa.dev>
 */
class MMG_Checkout_Payment_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Flushes rewrite rules upon plugin deactivation.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
