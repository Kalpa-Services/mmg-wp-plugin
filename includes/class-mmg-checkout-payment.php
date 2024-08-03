<?php
/**
 * MMG Checkout Payment Class
 *
 * This class handles the payment processing for MMG Checkout.
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * MMG_Checkout_Payment class.
 */
class MMG_Checkout_Payment {
	/**
	 * Client ID.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Merchant ID.
	 *
	 * @var string
	 */
	private $merchant_id;

	/**
	 * Secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * RSA public key.
	 *
	 * @var string
	 */
	private $rsa_public_key;

	/**
	 * Mode (live or demo).
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Live checkout URL.
	 *
	 * @var string
	 */
	private $live_checkout_url;

	/**
	 * Demo checkout URL.
	 *
	 * @var string
	 */
	private $demo_checkout_url;

	/**
	 * Callback URL.
	 *
	 * @var string
	 */
	private $callback_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialize plugin.
		$this->mode = get_option( 'mmg_mode', 'demo' ); // Default mode set to 'demo'.

		// Generate or retrieve unique callback URL.
		$this->callback_url = $this->generate_unique_callback_url();

		// Load settings.
		require_once __DIR__ . '/class-mmg-checkout-settings.php';
		new MMG_Checkout_Settings();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_generate_checkout_url', array( $this, 'generate_checkout_url' ) );
		add_action( 'wp_ajax_nopriv_generate_checkout_url', array( $this, 'generate_checkout_url' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway_class' ) );
		add_action( 'plugins_loaded', array( $this, 'init_gateway_class' ), 11 );
		add_action( 'parse_request', array( $this, 'parse_api_request' ) );

		$this->live_checkout_url = $this->get_checkout_url( 'live' );
		$this->demo_checkout_url = $this->get_checkout_url( 'demo' );
	}

	/**
	 * Generate a unique callback URL.
	 *
	 * @return string
	 */
	private function generate_unique_callback_url() {
		$callback_key = get_option( 'mmg_callback_key' );
		if ( ! $callback_key ) {
			$callback_key = wp_generate_password( 32, false );
			update_option( 'mmg_callback_key', $callback_key );
		}
		return home_url( "wc-api/mmg-checkout/{$callback_key}" );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		if ( is_checkout_pay_page() ) {
			wp_enqueue_script( 'mmg-checkout', plugin_dir_url( __DIR__ ) . 'js/mmg-checkout.js', array( 'jquery' ), '3.0', true );
			wp_localize_script(
				'mmg-checkout',
				'mmg_checkout_params',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'mmg_checkout_nonce' ),
				)
			);
		}

		// For blocks support.
		$gateway_settings = get_option( 'woocommerce_mmg_checkout_settings', array() );
		wp_localize_script(
			'wc-mmg-payments-blocks',
			'mmgCheckoutData',
			array(
				'title'       => isset( $gateway_settings['title'] ) ? $gateway_settings['title'] : 'MMG Checkout',
				'description' => isset( $gateway_settings['description'] ) ? $gateway_settings['description'] : 'Pay with MMG Checkout',
				'supports'    => array( 'products', 'refunds' ),
				'isEnabled'   => isset( $gateway_settings['enabled'] ) ? $gateway_settings['enabled'] : 'no',
			)
		);
	}

	/**
	 * Generate checkout URL.
	 *
	 * @throws Exception If there's an error generating the checkout URL.
	 */
	public function generate_checkout_url() {
		try {
			// Ensure 'nonce' is present in the request.
			if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'mmg_checkout_nonce' ) ) {
				throw new Exception( 'Invalid security token' );
			}
			if ( ! $this->validate_public_key() ) {
				throw new Exception( 'Invalid RSA public key' );
			}

			$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				wp_send_json_error( 'Invalid order' );
			}

			$amount      = $order->get_total();
			$description = 'Order #' . $order->get_order_number();

			$token_data = array(
				'secretKey'             => get_option( "mmg_{$this->mode}_secret_key" ),
				'amount'                => $amount,
				'merchantId'            => get_option( "mmg_{$this->mode}_merchant_id" ),
				'merchantTransactionId' => $order->get_id(), // Use order ID instead of order number.
				'productDescription'    => $description,
				'requestInitiationTime' => (string) round( microtime( true ) * 1000 ),
				'merchantName'          => get_option( 'mmg_merchant_name', get_bloginfo( 'name' ) ),
			);

			$encrypted    = $this->encrypt( $token_data );
			$encoded      = $this->url_safe_base64_encode( $encrypted );
			$checkout_url = add_query_arg(
				array(
					'token'       => $encoded,
					'merchantId'  => get_option( "mmg_{$this->mode}_merchant_id" ),
					'X-Client-ID' => get_option( "mmg_{$this->mode}_client_id" ),
				),
				$this->get_checkout_url()
			);

			$order->update_meta_data( '_mmg_transaction_id', $token_data['merchantTransactionId'] );
			$order->save();

			wp_send_json_success( array( 'checkout_url' => $checkout_url ) );
		} catch ( Exception $e ) {
			wp_send_json_error( 'Error generating checkout URL: ' . esc_html( $e->getMessage() ) );
		}
	}
	/**
	 * Encrypt checkout object.
	 *
	 * @param array $checkout_object Checkout object to encrypt.
	 * @return string
	 * @throws Exception If encryption fails.
	 */
	private function encrypt( $checkout_object ) {
		$json_object = wp_json_encode( $checkout_object, JSON_PRETTY_PRINT );

		// Message to bytes.
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$json_bytes = mb_convert_encoding( $json_object, 'ISO-8859-1', 'UTF-8' );
		} else {
			// Fallback method.
			$json_bytes = utf8_decode( $json_object );
		}

		// Load the public key.
		try {
			$public_key = \phpseclib3\Crypt\PublicKeyLoader::load( get_option( 'mmg_rsa_public_key' ) );
		} catch ( Exception $e ) {
			throw new Exception( 'Failed to load RSA public key' );
		}

		// Configure RSA encryption.
		$rsa = $public_key->withPadding( \phpseclib3\Crypt\RSA::ENCRYPTION_OAEP )
							->withHash( 'sha256' )
							->withMGFHash( 'sha256' );

		// Encrypt the data.
		try {
			$ciphertext = $rsa->encrypt( $json_bytes );
		} catch ( Exception $e ) {
			throw new Exception( 'Failed to encrypt data' );
		}

		return $ciphertext;
	}

	/**
	 * URL-safe base64 encode.
	 *
	 * @param string $data Data to encode.
	 * @return string
	 */
	private function url_safe_base64_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Validate public key.
	 *
	 * @return bool
	 */
	private function validate_public_key() {
		$public_key = get_option( 'mmg_rsa_public_key' );
		if ( ! $public_key ) {
			return false;
		}

		$key_resource = openssl_pkey_get_public( $public_key );
		if ( ! $key_resource ) {
			return false;
		}

		return true;
	}

	/**
	 * Add gateway class.
	 *
	 * @param array $gateways WooCommerce payment gateways.
	 * @return array
	 */
	public function add_gateway_class( $gateways ) {
		$gateways[] = 'WC_MMG_Gateway';
		return $gateways;
	}

	/**
	 * Initialize gateway class.
	 */
	public function init_gateway_class() {
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once __DIR__ . '/class-wc-mmg-gateway.php';
		}
	}

	/**
	 * Decrypt data.
	 *
	 * @param string $encrypted_data Encrypted data.
	 * @return array
	 * @throws Exception If decryption fails.
	 */
	private function decrypt( $encrypted_data ) {
		// Load the private key.
		try {
			$private_key = \phpseclib3\Crypt\PublicKeyLoader::load( get_option( 'mmg_rsa_private_key' ) );
		} catch ( Exception $e ) {
			throw new Exception( 'Failed to load RSA private key' );
		}

		// Configure RSA decryption.
		$rsa = $private_key->withPadding( \phpseclib3\Crypt\RSA::ENCRYPTION_OAEP )
							->withHash( 'sha256' )
							->withMGFHash( 'sha256' );

		// Decrypt the data.
		try {
			$decrypted = $rsa->decrypt( $encrypted_data );
		} catch ( Exception $e ) {
			throw new Exception( 'Failed to decrypt data' );
		}

		$decoded = json_decode( $decrypted, true );

		if ( ! is_array( $decoded ) ) {
			throw new Exception( 'Decrypted data is not a valid JSON array' );
		}

		return $decoded;
	}

	/**
	 * URL-safe base64 decode.
	 *
	 * @param string $data Data to decode.
	 * @return string
	 * @throws InvalidArgumentException If input is invalid.
	 */
	private function url_safe_base64_decode( $data ) {
		// Validate input.
		if ( ! is_string( $data ) ) {
			throw new InvalidArgumentException( 'Input must be a string' );
		}

		// Replace URL-safe characters.
		$base64 = strtr( $data, '-_', '+/' );

		// Add padding if necessary.
		$base64 = str_pad( $base64, strlen( $base64 ) % 4, '=', STR_PAD_RIGHT );

		// Decode with strict mode.
		$decoded = base64_decode( $base64, true );

		if ( false === $decoded ) {
			throw new InvalidArgumentException( 'Invalid base64 encoding' );
		}

		return $decoded;
	}

	/**
	 * Parse API request.
	 */
	public function parse_api_request() {
		global $wp;
		if ( isset( $wp->query_vars['mmg-checkout'] ) ) {
			$path_info = isset( $_SERVER['PATH_INFO'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PATH_INFO'] ) ) : '';
			if ( strpos( $path_info, '/errorpayment' ) !== false ) {
				$this->handle_error_payment();
			} else {
				$this->handle_payment_confirmation();
			}
			exit;
		}
	}

	/**
	 * Handle error payment.
	 */
	public function handle_error_payment() {
		// Verify the callback key first.
		if ( ! $this->verify_callback_key() ) {
			wp_die( 'Invalid callback', 'MMG Checkout Error', array( 'response' => 403 ) );
		}

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( empty( $token ) ) {
			wp_die( 'Invalid token', 'MMG Checkout Error', array( 'response' => 400 ) );
		}

		try {
			$decoded_token = $this->url_safe_base64_decode( $token );
			$error_data    = $this->decrypt( $decoded_token );
		} catch ( Exception $e ) {
			wp_die( 'Error decrypting token: ' . esc_html( $e->getMessage() ), 'MMG Checkout Error', array( 'response' => 400 ) );
		}

		$order_id      = isset( $error_data['merchantTransactionId'] ) ? intval( $error_data['merchantTransactionId'] ) : 0;
		$error_code    = isset( $error_data['errorCode'] ) ? intval( $error_data['errorCode'] ) : null;
		$error_message = isset( $error_data['errorMessage'] ) ? sanitize_text_field( $error_data['errorMessage'] ) : '';

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_die( 'Invalid order', 'MMG Checkout Error', array( 'response' => 400 ) );
		}

		$order->update_status( 'failed', "Payment failed. Error Code: {$error_code}, Message: {$error_message}" );

		// Redirect to the order page with an error message.
		wc_add_notice( __( 'Payment failed. Please try again or contact support.', 'mmg-checkout' ), 'error' );
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	/**
	 * Handle payment confirmation.
	 */
	public function handle_payment_confirmation() {
		// Verify the callback key first.
		if ( ! $this->verify_callback_key() ) {
			wp_die( 'Invalid callback', 'MMG Checkout Error', array( 'response' => 403 ) );
		}

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( empty( $token ) ) {
			wp_die( 'Invalid token', 'MMG Checkout Error', array( 'response' => 400 ) );
		}

		try {
			$decoded_token = $this->url_safe_base64_decode( $token );
			$payment_data  = $this->decrypt( $decoded_token );
		} catch ( Exception $e ) {
			wp_die( 'Error decrypting token: ' . esc_html( $e->getMessage() ), 'MMG Checkout Error', array( 'response' => 400 ) );
		}

		$order_id       = isset( $payment_data['merchantTransactionId'] ) ? intval( $payment_data['merchantTransactionId'] ) : 0;
		$result_code    = isset( $payment_data['resultCode'] ) ? intval( $payment_data['resultCode'] ) : null;
		$result_message = isset( $payment_data['resultMessage'] ) ? sanitize_text_field( $payment_data['resultMessage'] ) : '';

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_die( 'Invalid order', 'MMG Checkout Error', array( 'response' => 400 ) );
		}

		$payment_verified = ( 0 === $result_code ); // 0 indicates a successful transaction

		if ( $payment_verified ) {
			$order->payment_complete();
			$order->add_order_note( "Payment completed via MMG Checkout. Transaction ID: {$payment_data['transactionId']}" );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
		} else {
			$status_messages = array(
				1 => array(
					'status'  => 'failed',
					'message' => 'Agent Not Registered.',
				),
				2 => array(
					'status'  => 'failed',
					'message' => 'Payment Failed.',
				),
				3 => array(
					'status'  => 'failed',
					'message' => 'Invalid Secret Key.',
				),
				4 => array(
					'status'  => 'failed',
					'message' => 'Merchant ID Mismatch.',
				),
				5 => array(
					'status'  => 'failed',
					'message' => 'Token Decryption Failed.',
				),
				6 => array(
					'status'  => 'cancelled',
					'message' => 'Payment cancelled by user.',
				),
				7 => array(
					'status'  => 'failed',
					'message' => 'Request Timed Out.',
				),
			);

			if ( isset( $status_messages[ $result_code ] ) ) {
				$order->update_status( $status_messages[ $result_code ]['status'], "Payment failed. Reason: {$status_messages[$result_code]['message']}" );
			} else {
				$order->update_status( 'failed', "Payment failed. Result Code: {$result_code}, Message: {$result_message}" );
			}

			wp_safe_redirect( $order->get_checkout_payment_url() );
		}
		exit;
	}

	/**
	 * Verify the callback key from the URL.
	 *
	 * @return bool
	 */
	private function verify_callback_key() {
		$parsed_url = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : array();
		$path       = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
		$uri_parts  = explode( '/', trim( $path, '/' ) );

		$mmg_checkout_index = array_search( 'mmg-checkout', $uri_parts, true );
		$callback_key       = '';

		if ( false !== $mmg_checkout_index && isset( $uri_parts[ $mmg_checkout_index + 1 ] ) ) {
			$raw_callback_key = $uri_parts[ $mmg_checkout_index + 1 ];
			$callback_key     = preg_replace( '/[^a-zA-Z0-9-]/', '', $raw_callback_key );

			if ( empty( $callback_key ) || strlen( $callback_key ) > 64 ) {
				return false;
			}
		}

		$stored_callback_key = get_option( 'mmg_callback_key' );

		return ! empty( $callback_key ) && $callback_key === $stored_callback_key;
	}

	/**
	 * Get checkout URL based on mode.
	 *
	 * @param string $mode 'live' or 'demo'.
	 * @return string
	 */
	private function get_checkout_url( $mode = null ) {
		// If no mode is provided, use the current mode.
		if ( null === $mode ) {
			$mode = $this->mode;
		}

		$constant_name = 'MMG_' . strtoupper( $mode ) . '_CHECKOUT_URL';
		if ( defined( $constant_name ) ) {
			return constant( $constant_name );
		}

		$option_name = 'mmg_' . $mode . '_checkout_url';
		$default_url = 'live' === $mode
			? 'https://gtt-checkout.qpass.com:8743/checkout-endpoint/home'
			: 'https://gtt-uat-checkout.qpass.com:8743/checkout-endpoint/home';

			return get_option( $option_name, $default_url );
	}
}
