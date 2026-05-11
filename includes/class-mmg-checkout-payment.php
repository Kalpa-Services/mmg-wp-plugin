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
	 * Singleton instance for access by WC_MMG_Gateway.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Return the active instance (set during construction).
	 *
	 * @return self|null
	 */
	public static function get_instance() {
		return self::$instance;
	}

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
		self::$instance = $this;

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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Data access layer.
		require_once __DIR__ . '/models/class-mmg-subscription-model.php';

		// Custom payment token — must be loaded before any WC token hydration occurs.
		require_once __DIR__ . '/class-wc-payment-token-mmg.php';
		add_filter( 'woocommerce_payment_token_class', array( $this, 'register_mmg_token_class' ), 10, 2 );

		// Initialize Action Scheduler Handler.
		require_once __DIR__ . '/class-mmg-action-scheduler-handler.php';
		new MMG_Action_Scheduler_Handler();

		// Initialize Native Subscriptions.
		require_once __DIR__ . '/class-wc-product-mmg-subscription.php';
		require_once __DIR__ . '/class-mmg-subscription-admin.php';
		require_once __DIR__ . '/class-mmg-subscription-manager.php';
		require_once __DIR__ . '/class-mmg-subscription-account.php';
		require_once __DIR__ . '/class-mmg-subscription-renewal-handler.php';
		require_once __DIR__ . '/class-mmg-subscription-reminder-scheduler.php';
		require_once __DIR__ . '/class-mmg-subscription-email.php';
		if ( is_admin() ) {
			new MMG_Subscription_Admin();
			require_once __DIR__ . '/class-mmg-subscription-email-settings.php';
			new MMG_Subscription_Email_Settings();
			if ( class_exists( 'WP_List_Table' ) ) {
				require_once __DIR__ . '/class-mmg-subscription-admin-list.php';
				add_action( 'admin_menu', array( 'MMG_Subscription_Admin_List', 'register_menu' ) );
			}
		}
		new MMG_Subscription_Manager();
		new MMG_Subscription_Account();
		add_filter( 'woocommerce_product_class', array( $this, 'get_subscription_product_class' ), 10, 2 );
		add_action( 'woocommerce_mmg_subscription_add_to_cart', 'woocommerce_simple_add_to_cart', 30 );

		$this->live_checkout_url = $this->get_checkout_url( 'live' );
		$this->demo_checkout_url = $this->get_checkout_url( 'demo' );
	}

	/**
	 * Map mmg_subscription product type to our custom class.
	 *
	 * @param string $classname    Class name.
	 * @param string $product_type Product type.
	 * @return string
	 */
	public function get_subscription_product_class( $classname, $product_type ) {
		if ( 'mmg_subscription' === $product_type ) {
			return 'WC_Product_MMG_Subscription';
		}
		return $classname;
	}

	/**
	 * Register WC_Payment_Token_MMG so WooCommerce can hydrate tokens of type 'mmg'.
	 *
	 * @param string $class Token class name WooCommerce resolved by convention.
	 * @param string $type  Token type stored in the database.
	 * @return string
	 */
	public function register_mmg_token_class( $class, $type ) {
		if ( 'mmg' === $type ) {
			return 'WC_Payment_Token_MMG';
		}
		return $class;
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
		// For blocks support.
		$gateway_settings = get_option( 'woocommerce_mmg_checkout_settings', array() );
		$description      = isset( $gateway_settings['description'] ) ? $gateway_settings['description'] : 'Use your MMG account to pay for your order.';

		// Check if the plugin is in sandbox mode and update the description.
		if ( 'demo' === $this->mode ) {
			$description .= ' (Sandbox mode: No payment will be processed)';
		}

		wp_localize_script(
			'wc-mmg-payments-blocks',
			'mmgCheckoutData',
			array(
				'title'       => isset( $gateway_settings['title'] ) ? $gateway_settings['title'] : 'MMG Checkout',
				'description' => $description,
				'supports'    => array( 'products', 'refunds' ),
				'isEnabled'   => isset( $gateway_settings['enabled'] ) ? $gateway_settings['enabled'] : 'no',
			)
		);

		wp_enqueue_style( 'mmg-public-style', plugin_dir_url( __FILE__ ) . '../public/css/main-style.css', array(), '1.0.0' );
	}
	/**
	 * Generate checkout URL.
	 *
	 * @throws Exception If there's an error generating the checkout URL.
	 */
	/**
	 * Build the MMG hosted-checkout URL for an order.
	 *
	 * Shared by process_payment (direct redirect) and the legacy AJAX handler.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return string The fully-qualified MMG checkout URL.
	 * @throws Exception If the RSA key is invalid or encryption fails.
	 */
	public function build_mmg_checkout_url( WC_Order $order ) {
		if ( ! $this->validate_public_key() ) {
			throw new Exception( 'Invalid RSA public key' );
		}

		$attempt_number = $order->get_meta( '_mmg_payment_attempt', true );
		$attempt_number = $attempt_number ? intval( $attempt_number ) + 1 : 1;
		$order->update_meta_data( '_mmg_payment_attempt', $attempt_number );
		$order->save();

		$currency = $order->get_currency();
		$total    = $order->get_total();
		$amount   = $total;
		$rate     = 1;

		if ( 'GYD' !== $currency ) {
			$rates = get_option( 'mmg_currency_rates', array() );
			if ( isset( $rates[ $currency ] ) && 'yes' === $rates[ $currency ]['enabled'] ) {
				$rate   = floatval( $rates[ $currency ]['rate'] );
				$amount = round( $total * $rate );
			}
		}

		$token_data = array(
			'secretKey'             => get_option( "mmg_{$this->mode}_secret_key" ),
			'amount'                => $amount,
			'merchantId'            => get_option( "mmg_{$this->mode}_merchant_id" ),
			'merchantTransactionId' => $order->get_id() . '-' . $attempt_number,
			'productDescription'    => 'Order #' . $order->get_order_number(),
			'requestInitiationTime' => time(),
			'merchantName'          => get_option( 'mmg_merchant_name', get_bloginfo( 'name' ) ),
		);

		// If this is a subscription order, signal MMG to tokenize the payment.
		$has_subscription = false;
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product && 'mmg_subscription' === $product->get_type() ) {
				$has_subscription = true;
				break;
			}
		}

		if ( $has_subscription || ( function_exists( 'wcs_is_subscription' ) && ( wcs_is_subscription( $order ) || wcs_order_contains_subscription( $order ) ) ) ) {
			$token_data['setupFutureUsage'] = 'on_session';
		}

		// Store conversion metadata for reference.
		$order->update_meta_data( '_mmg_conversion_rate', $rate );
		$order->update_meta_data( '_mmg_original_amount', $total );
		$order->update_meta_data( '_mmg_original_currency', $currency );
		$order->update_meta_data( '_mmg_converted_amount_gyd', $amount );
		$order->save();

		$encoded      = $this->url_safe_base64_encode( $this->encrypt( $token_data ) );
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

		return $checkout_url;
	}

	/**
	 * AJAX handler — kept for any legacy or fallback callers.
	 *
	 * @throws Exception If nonce verification fails or URL generation fails.
	 */
	public function generate_checkout_url() {
		try {
			if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'mmg_checkout_nonce' ) ) {
				throw new Exception( 'Invalid security token' );
			}

			$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				wp_send_json_error( 'Invalid order' );
			}

			wp_send_json_success( array( 'checkout_url' => $this->build_mmg_checkout_url( $order ) ) );
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
	protected function encrypt( $checkout_object ) {
		$json_object = wp_json_encode( $checkout_object, JSON_UNESCAPED_SLASHES );

		// Message to bytes.
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$json_bytes = mb_convert_encoding( $json_object, 'ISO-8859-1', 'UTF-8' );
		} else {
			// Fallback method.
			$json_bytes = mb_convert_encoding( $json_object, 'ISO-8859-1', 'UTF-8' );
		}

		// Load the public key.
		try {
			$public_key = \phpseclib3\Crypt\PublicKeyLoader::load( get_option( 'mmg_' . $this->mode . '_rsa_public_key' ) );
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
	protected function url_safe_base64_encode( $data ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Validate public key.
	 *
	 * @return bool
	 */
	protected function validate_public_key() {
		$public_key = get_option( 'mmg_' . $this->mode . '_rsa_public_key' );
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
	protected function decrypt( $encrypted_data ) {
		// Load the private key.
		try {
			$private_key = \phpseclib3\Crypt\PublicKeyLoader::load( get_option( 'mmg_' . $this->mode . '_rsa_private_key' ) );
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
	protected function url_safe_base64_decode( $data ) {
		// Validate input.
		if ( ! is_string( $data ) ) {
			throw new InvalidArgumentException( 'Input must be a string' );
		}

		// Replace URL-safe characters.
		$base64 = strtr( $data, '-_', '+/' );

		// Add padding if necessary.
		$base64 = str_pad( $base64, strlen( $base64 ) % 4, '=', STR_PAD_RIGHT );

		// Decode with strict mode.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
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
			$method    = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
			$path_info = isset( $_SERVER['PATH_INFO'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PATH_INFO'] ) ) : '';

			if ( 'POST' === $method ) {
				$this->handle_webhook_notification();
			} elseif ( strpos( $path_info, '/errorpayment' ) !== false ) {
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

		$order_id      = $this->extract_order_id( $error_data['merchantTransactionId'] );
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

		$order_id       = $this->extract_order_id( $payment_data['merchantTransactionId'] );
		$result_code    = isset( $payment_data['ResultCode'] ) ? intval( $payment_data['ResultCode'] ) : null;
		$result_message = isset( $payment_data['ResultMessage'] ) ? sanitize_text_field( $payment_data['ResultMessage'] ) : '';

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
	protected function verify_callback_key() {
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
	protected function get_checkout_url( $mode = null ) {
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
			? 'https://mmgpg.mymmg.gy/mmg-pg/web/payments'
			: 'https://mmgpg.mmgtest.net/mmg-pg/web/payments';

		return get_option( $option_name, $default_url );
	}

	/**
	 * Extract the original order ID from the merchantTransactionId.
	 *
	 * @param string $merchant_transaction_id The merchantTransactionId from the payment data.
	 * @return int The original order ID.
	 */
	protected function extract_order_id( $merchant_transaction_id ) {
		$parts = explode( '-', $merchant_transaction_id );
		return intval( $parts[0] );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		require_once __DIR__ . '/class-mmg-api-client.php';
		$auth = function () {
			// phpcs:ignore WordPress.WP.Capabilities.Unknown
			return current_user_can( 'manage_woocommerce' );
		};

		register_rest_route(
			'mmg/v1',
			'/balance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_balance' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			'mmg/v1',
			'/transactions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_transactions' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			'mmg/v1',
			'/transactions/(?P<id>[a-zA-Z0-9_\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_lookup_transaction' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			'mmg/v1',
			'/transactions/(?P<id>[a-zA-Z0-9_\-]+)/reversal',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_reversal' ),
				'permission_callback' => $auth,
			)
		);
	}

	/**
	 * REST get balance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get_balance() {
		try {
			return new WP_REST_Response( ( new MMG_API_Client() )->get_balance(), 200 );
		} catch ( Exception $e ) {
			return new WP_Error( 'mmg_error', $e->getMessage(), array( 'status' => $e->getCode() === 401 ? 401 : 502 ) );
		}
	}

	/**
	 * REST get transactions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get_transactions( $request ) {
		try {
			$params = $request->get_query_params();
			unset( $params['_locale'] );
			return new WP_REST_Response( ( new MMG_API_Client() )->get_transaction_history( $params ), 200 );
		} catch ( Exception $e ) {
			return new WP_Error( 'mmg_error', $e->getMessage(), array( 'status' => $e->getCode() === 401 ? 401 : 502 ) );
		}
	}

	/**
	 * REST lookup transaction.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_lookup_transaction( $request ) {
		try {
			return new WP_REST_Response( ( new MMG_API_Client() )->lookup_transaction( $request['id'] ), 200 );
		} catch ( Exception $e ) {
			return new WP_Error( 'mmg_error', $e->getMessage(), array( 'status' => $e->getCode() === 401 ? 401 : 502 ) );
		}
	}

	/**
	 * REST reversal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_reversal( $request ) {
		try {
			$mode = get_option( 'mmg_mode', 'demo' );
			$mid  = get_option( "mmg_{$mode}_merchant_id" );
			return new WP_REST_Response( ( new MMG_API_Client() )->reversal( $mid, $request['id'] ), 200 );
		} catch ( Exception $e ) {
			return new WP_Error( 'mmg_error', $e->getMessage(), array( 'status' => $e->getCode() === 401 ? 401 : 502 ) );
		}
	}

	/**
	 * Public proxy for handle_webhook_notification — used in tests.
	 */
	public function handle_webhook_notification_public() {
		$this->handle_webhook_notification();
	}

	/**
	 * Get raw POST body.
	 *
	 * @return string
	 */
	protected function get_raw_post_body() {
		return file_get_contents( 'php://input' );
	}

	/**
	 * Handle webhook notification from MMG on payment completion.
	 */
	/**
	 * Handle webhook notification from MMG on payment completion.
	 *
	 * Unified handler for asynchronous status updates and renewals.
	 */
	private function handle_webhook_notification() {
		$payload = $this->get_raw_post_body();
		$headers = getallheaders();

		MMG_Logger::info( 'Webhook received: ' . $payload, 'webhooks' );

		// 1. Verify Callback Key (Token in URL).
		if ( ! $this->verify_callback_key() ) {
			MMG_Logger::error( 'Invalid callback key for webhook.', 'webhooks' );
			wp_send_json_error( 'Invalid callback key', 403 );
		}

		// 2. Verify HMAC Signature.
		$signature = isset( $headers['X-MMG-Signature'] ) ? $headers['X-MMG-Signature'] : '';
		if ( ! $this->verify_signature( $payload, $signature ) ) {
			MMG_Logger::error( 'Invalid HMAC signature for webhook.', 'webhooks' );
			wp_send_json_error( 'Invalid signature', 403 );
		}

		$data = json_decode( $payload, true );
		if ( ! $data ) {
			MMG_Logger::error( 'Invalid webhook payload (not JSON).', 'webhooks' );
			wp_send_json_error( 'Invalid payload', 400 );
		}

		// 3. Extract Order Data from Token if present.
		if ( ! empty( $data['token'] ) ) {
			try {
				$decoded      = $this->url_safe_base64_decode( $data['token'] );
				$payment_data = $this->decrypt( $decoded );
				$data         = array_merge( $data, $payment_data );
			} catch ( Exception $e ) {
				MMG_Logger::error( 'Webhook decrypt error: ' . $e->getMessage(), 'webhooks' );
				wp_send_json_success(); // 200 to prevent retries.
				return;
			}
		}

		// 4. Check Idempotency.
		$event_id = isset( $data['event_id'] ) ? $data['event_id'] : ( isset( $data['merchantTransactionId'] ) ? $data['merchantTransactionId'] : '' );
		if ( ! empty( $event_id ) && $this->is_event_processed( $event_id ) ) {
			MMG_Logger::info( 'Webhook event already processed: ' . $event_id, 'webhooks' );
			wp_send_json_success( array( 'message' => 'Event already processed' ) );
		}

		// 5. Log event to database.
		$order_id   = ! empty( $data['order_id'] ) ? intval( $data['order_id'] ) : $this->extract_order_id( $data['merchantTransactionId'] ?? '' );
		$event_type = $data['event_type'] ?? 'payment.success';
		$this->log_event( $event_id, $event_type, $order_id );

		// Ensure these are in data for background processing.
		$data['order_id']   = $order_id;
		$data['event_type'] = $event_type;

		// 6. Push to Action Scheduler for background processing.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'mmg_process_webhook_event', array( 'data' => $data ) );
			MMG_Logger::info( "Webhook event queued for background processing: {$event_id}", 'webhooks' );
		} else {
			do_action( 'mmg_process_webhook_event', $data );
		}

		wp_send_json_success( array( 'message' => 'Webhook received and queued' ) );
	}

	/**
	 * Verify HMAC signature.
	 *
	 * @param string $payload   Request payload.
	 * @param string $signature Signature from header.
	 * @return bool
	 */
	protected function verify_signature( $payload, $signature ) {
		$secret_key = get_option( "mmg_{$this->mode}_secret_key" );

		if ( ! $secret_key || ! $signature ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $payload, $secret_key );
		return hash_equals( $expected, $signature );
	}

	/**
	 * Check if event has already been processed.
	 *
	 * @param string $event_id Event ID.
	 * @return bool
	 */
	protected function is_event_processed( $event_id ) {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					array(
						'key'     => '_mmg_processed_event_id',
						'value'   => $event_id,
						'compare' => '=',
					),
				),
			)
		);
		return ! empty( $orders );
	}

	/**
	 * Log event using order metadata.
	 *
	 * @param string $event_id   Event ID.
	 * @param string $event_type Event Type.
	 * @param int    $order_id   Order ID.
	 */
	protected function log_event( $event_id, $event_type, $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->add_meta_data( '_mmg_processed_event_id', $event_id, false );
			$order->add_meta_data( '_mmg_event_type_' . $event_id, $event_type, true );
			$order->save();
		}
	}
}
