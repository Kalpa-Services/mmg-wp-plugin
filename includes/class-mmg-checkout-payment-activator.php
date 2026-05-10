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
		self::create_subscription_table();
	}

	/**
	 * Create custom table for native subscriptions.
	 */
	public static function create_subscription_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'mmg_subscriptions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) NOT NULL,
			order_id bigint(20) NOT NULL,
			product_id bigint(20) NOT NULL,
			status varchar(20) DEFAULT 'active' NOT NULL,
			billing_interval int(11) DEFAULT 1 NOT NULL,
			billing_period varchar(20) DEFAULT 'month' NOT NULL,
			next_payment_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			payment_token text NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
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

		if ( ! wp_next_scheduled( 'mmg_send_telemetry' ) ) {
			wp_schedule_event( time(), 'daily', 'mmg_send_telemetry' );
		}

		// Fire immediately upon activation so we don't have to wait for the first cron tick.
		if ( class_exists( 'MMG_Telemetry' ) ) {
			MMG_Telemetry::send_telemetry();
		}
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
