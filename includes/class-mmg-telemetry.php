<?php
/**
 * MMG Telemetry Class
 *
 * @package MMG_Checkout_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles sending periodic telemetry data to the central server.
 */
class MMG_Telemetry {

	/**
	 * The URL to send telemetry data to.
	 *
	 * @var string
	 */
	const TELEMETRY_URL = 'https://updates.kalpa.dev/';

	/**
	 * Initialize the telemetry hooks.
	 */
	public static function init() {
		add_action( 'mmg_send_telemetry', array( __CLASS__, 'send_telemetry' ) );
	}

	/**
	 * Send the telemetry data via POST request.
	 */
	public static function send_telemetry() {
		$gateway_settings = get_option( 'woocommerce_mmg_checkout_settings', array() );
		$is_enabled       = isset( $gateway_settings['enabled'] ) && 'yes' === $gateway_settings['enabled'];
		$mode             = get_option( 'mmg_mode', 'demo' );
		$error_count      = 0;
		if ( class_exists( 'MMG_Logger' ) ) {
			$logs      = MMG_Logger::get_logs();
			$yesterday = time() - DAY_IN_SECONDS;
			foreach ( $logs as $entry ) {
				if ( 'error' === $entry['lvl'] && $entry['ts'] > $yesterday ) {
					++$error_count;
				}
			}
		}

		$theme   = wp_get_theme();
		$payload = array(
			'site_url'         => home_url(),
			'plugin_version'   => MMG_PLUGIN_VERSION,
			'php_version'      => phpversion(),
			'wp_version'       => get_bloginfo( 'version' ),
			'wc_version'       => defined( 'WC_VERSION' ) ? WC_VERSION : ( class_exists( 'WooCommerce' ) ? WC()->version : 'Not Installed' ),
			'gateway_enabled'  => $is_enabled,
			'environment_mode' => $mode,
			'site_language'    => get_locale(),
			'active_theme'     => $theme->get( 'Name' ),
			'error_count_24h'  => $error_count,
		);

		wp_remote_post(
			self::TELEMETRY_URL,
			array(
				'method'      => 'POST',
				'timeout'     => 15,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Content-Type' => 'application/json',
				),
				'body'        => wp_json_encode( $payload ),
			)
		);
	}
}
