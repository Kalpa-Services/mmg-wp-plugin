<?php
/**
 *  MMG Checkout for WooCommerce Deactivator
 *
 * This file contains the MMG_Checkout_Payment_Deactivator class and deactivation function.
 *
 * @package Kalpa_MMG_Checkout_Payment
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation
 *
 * @since      1.0.0
 * @package     MMG Checkout for WooCommerce
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
