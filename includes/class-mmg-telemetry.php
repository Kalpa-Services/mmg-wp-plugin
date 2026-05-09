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
		$payload = array(
			'site_url'       => home_url(),
			'plugin_version' => MMG_PLUGIN_VERSION,
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
