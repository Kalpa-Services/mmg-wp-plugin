<?php
/**
 * MMG API Client File
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MMG API Client Class
 */
class MMG_API_Client {

	/**
	 * Mode (demo or live)
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Base URL for the API
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->mode     = get_option( 'mmg_mode', 'demo' );
		$this->base_url = $this->resolve_base_url();
	}

	/**
	 * Resolve Base URL
	 *
	 * @return string
	 */
	protected function resolve_base_url() {
		if ( 'live' === $this->mode ) {
			return rtrim( get_option( 'mmg_live_mwallet_url', 'https://mwallet.mmgtest.net' ), '/' );
		}
		return 'https://mwallet.mmgtest.net';
	}

	/**
	 * Get Mode
	 *
	 * @return string
	 */
	public function get_mode() {
		return $this->mode; }

	/**
	 * Get Base URL
	 *
	 * @return string
	 */
	public function get_base_url() {
		return $this->base_url; }

	/**
	 * Ensure Token Public
	 *
	 * @return void
	 */
	public function ensure_token_public() {
		$this->ensure_token(); }

	/**
	 * Ensure Token
	 *
	 * @return void
	 */
	protected function ensure_token() {
		if ( get_transient( 'mmg_access_token_' . $this->mode ) ) {
			return;
		}
		if ( ! $this->refresh_token() ) {
			$this->do_login();
		}
	}

	/**
	 * Do Login
	 *
	 * @return void
	 * @throws Exception If login fails.
	 */
	public function do_login() {
		$merchant_id = get_option( "mmg_{$this->mode}_merchant_id" );
		$body        = http_build_query(
			array(
				'merchantId' => $merchant_id,
				'secretKey'  => get_option( "mmg_{$this->mode}_secret_key" ),
			)
		);
		$url      = $this->base_url . '/mwallet/v1/e-commerce-login/mer';
		$response = $this->http_post(
			$url,
			array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			$body
		);

		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[MMG] login POST %s | merchantId=%s | body=%s', $url, $merchant_id, $body ) );
		error_log( sprintf( '[MMG] login response HTTP %d | body=%s', $code, $response_body ) );
		// phpcs:enable

		if ( 200 !== $code ) {
			throw new Exception( esc_html( sprintf( 'Login failed with HTTP %d', $code ) ) );
		}
		$data = json_decode( $response_body, true );
		if ( empty( $data['access_token'] ) ) {
			throw new Exception( esc_html( 'Login response missing access_token' ) );
		}
		set_transient( 'mmg_access_token_' . $this->mode, $data['access_token'], 55 * MINUTE_IN_SECONDS );
		set_transient( 'mmg_refresh_token_' . $this->mode, $data['refresh_token'] ?? '', DAY_IN_SECONDS );
	}

	/**
	 * Refresh Token
	 *
	 * @return bool
	 */
	protected function refresh_token() {
		// Refresh endpoint not yet documented by MMG; always fall back to full login.
		return false;
	}

	/**
	 * Clear Tokens
	 *
	 * @return void
	 */
	public function clear_tokens() {
		delete_transient( 'mmg_access_token_' . $this->mode );
		delete_transient( 'mmg_refresh_token_' . $this->mode );
	}

	/**
	 * Reauthenticate
	 *
	 * @return void
	 */
	public function reauthenticate() {
		$this->clear_tokens();
		$this->do_login();
	}

	/**
	 * Get Auth Headers
	 *
	 * @return array
	 */
	protected function get_auth_headers() {
		$access_token  = get_transient( 'mmg_access_token_' . $this->mode );
		$refresh_token = get_transient( 'mmg_refresh_token_' . $this->mode );
		return array(
			'X-REQUEST-ID'    => wp_generate_uuid4(),
			'X-CHANNEL'       => 'ECommerce',
			'X-MVNO-ID'       => '1',
			'X-ACCESS-TOKEN'  => $access_token ? $access_token : '',
			'X-REFRESH-TOKEN' => $refresh_token ? $refresh_token : '',
		);
	}

	/**
	 * HTTP Get
	 *
	 * @param string $url URL.
	 * @param array  $headers Headers.
	 * @return array|WP_Error
	 */
	protected function http_get( $url, $headers = array() ) {
		return wp_remote_get( $url, array( 'headers' => $headers ) );
	}

	/**
	 * HTTP Post
	 *
	 * @param string $url URL.
	 * @param array  $headers Headers.
	 * @param string $body Body.
	 * @return array|WP_Error
	 */
	protected function http_post( $url, $headers = array(), $body = '' ) {
		return wp_remote_post(
			$url,
			array(
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => $body,
			)
		);
	}

	/**
	 * Parse Response
	 *
	 * @param array|WP_Error $response Response.
	 * @return array
	 * @throws Exception If API returns error.
	 */
	protected function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( $response->get_error_message() ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			throw new Exception( esc_html( sprintf( 'API error: HTTP %d', $code ) ), (int) $code );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true ) ?? array();
	}

	/**
	 * Authenticated Get
	 *
	 * @param string $path Path.
	 * @return array
	 */
	protected function authenticated_get( $path ) {
		$this->ensure_token();
		return $this->parse_response( $this->http_get( $this->base_url . $path, $this->get_auth_headers() ) );
	}

	/**
	 * Authenticated Post
	 *
	 * @param string $path Path.
	 * @return array
	 */
	protected function authenticated_post( $path ) {
		$this->ensure_token();
		return $this->parse_response( $this->http_post( $this->base_url . $path, $this->get_auth_headers() ) );
	}

	/**
	 * Get Balance
	 *
	 * @return array
	 */
	public function get_balance() {
		$mid = get_option( "mmg_{$this->mode}_merchant_id" );
		return $this->authenticated_get(
			'/mwallet/v1/e-merchant-initiated-transactions/balance?merchant_msisdn=' . rawurlencode( $mid )
		);
	}

	/**
	 * Get Transaction History
	 *
	 * @param array $params Params.
	 * @return array
	 */
	public function get_transaction_history( array $params = array() ) {
		$mid   = get_option( "mmg_{$this->mode}_merchant_id" );
		$query = http_build_query( array_merge( array( 'msisdn' => $mid ), $params ) );
		return $this->authenticated_get(
			'/mwallet/v1/e-merchant-initiated-transactions/txn-history?' . $query
		);
	}

	/**
	 * Lookup Transaction
	 *
	 * @param string $txn_id Transaction ID.
	 * @return array
	 */
	public function lookup_transaction( $txn_id ) {
		return $this->authenticated_get(
			'/mwallet/v1/e-merchant-initiated-transactions/lookup?transactionId=' . rawurlencode( $txn_id )
		);
	}

	/**
	 * Reversal
	 *
	 * @param string $merchant_mid Merchant MID.
	 * @param string $txn_id Transaction ID.
	 * @return array
	 */
	public function reversal( $merchant_mid, $txn_id ) {
		$query = 'merchant_msisdn=' . rawurlencode( $merchant_mid ) . '&transactionId=' . rawurlencode( $txn_id );
		return $this->authenticated_post(
			'/mwallet/v1/e-merchant-initiated-transactions/reversal?' . $query
		);
	}
}
