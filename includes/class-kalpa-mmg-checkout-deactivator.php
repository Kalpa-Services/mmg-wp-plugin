<?php
/**
 * MMG Checkout for WooCommerce Deactivator
 *
 * This file contains the Kalpa_MMG_Checkout_Deactivator class and deactivation function.
 *
 * @package Kalpa_MMG_Checkout_Main
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation
 *
 * @since      1.0.0
 * @package    MMG Checkout for WooCommerce
 * @author     Kalpa Services Inc. <info@kalpa.dev>
 */
class Kalpa_MMG_Checkout_Deactivator {

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
