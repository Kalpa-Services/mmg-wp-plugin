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
		flush_rewrite_rules();
	}
}
