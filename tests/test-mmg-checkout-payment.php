<?php
/**
 * Test cases for MMG_Checkout_Payment class.
 *
 * @package MMG_Checkout_Payment
 */

use MMG\CheckoutPayment\MMG_Checkout_Payment;

/**
 * Class Test_MMG_Checkout_Payment
 *
 * This class contains unit tests for the MMG_Checkout_Payment class.
 */
class Test_MMG_Checkout_Payment extends \WP_UnitTestCase {
	/**
	 * The MMG_Checkout_Payment instance for testing.
	 *
	 * @var MMG_Checkout_Payment
	 */
	private $mmg_checkout;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->mmg_checkout = new MMG_Checkout_Payment();
	}

	/**
	 * Test successful generation of checkout URL.
	 */
	public function test_successful_generate_checkout_url() {
		// Mock necessary functions and methods.
		$this->mock_wp_verify_nonce( true );
		$this->mock_method( $this->mmg_checkout, 'validate_public_key', true );
		$this->mock_method( $this->mmg_checkout, 'encrypt', 'encrypted_data' );
		$this->mock_method( $this->mmg_checkout, 'url_safe_base64_encode', 'encoded_data' );

		// Create a test order.
		$order = wc_create_order();
		$order->set_total( 100 );

		// Set up POST data and options.
		$_POST['order_id'] = $order->get_id();
		$_REQUEST['nonce'] = 'valid_nonce';
		update_option( 'mmg_secret_key', 'test_secret_key' );
		update_option( 'mmg_merchant_id', 'test_merchant_id' );
		update_option( 'mmg_client_id', 'test_client_id' );

		// Capture the output.
		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		// Assert the response.
		$response = wp_json_decode( $output, true );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'checkout_url', $response['data'] );
		$this->assertStringContainsString( 'token=encoded_data', $response['data']['checkout_url'] );
	}

	/**
	 * Test generation of checkout URL with invalid nonce.
	 */
	public function test_invalid_nonce_generate_checkout_url() {
		$this->mock_wp_verify_nonce( false );
		$_REQUEST['nonce'] = 'invalid_nonce';

		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		$response = wp_json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid security token', $response['data'] );
	}

	/**
	 * Test generation of checkout URL with invalid public key.
	 */
	public function test_invalid_public_key_generate_checkout_url() {
		$this->mock_wp_verify_nonce( true );
		$this->mock_method( $this->mmg_checkout, 'validate_public_key', false );
		$_REQUEST['nonce'] = 'valid_nonce';

		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		$response = wp_json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid RSA public key', $response['data'] );
	}

	/**
	 * Test generation of checkout URL with invalid order.
	 */
	public function test_invalid_order_generate_checkout_url() {
		$this->mock_wp_verify_nonce( true );
		$this->mock_method( $this->mmg_checkout, 'validate_public_key', true );
		$_POST['order_id'] = 999999; // Non-existent order ID.
		$_REQUEST['nonce'] = 'valid_nonce';

		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		$response = wp_json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Invalid order', $response['data'] );
	}

	/**
	 * Test generation of checkout URL with encryption failure.
	 */
	public function test_encryption_failure_generate_checkout_url() {
		$this->mock_wp_verify_nonce( true );
		$this->mock_method( $this->mmg_checkout, 'validate_public_key', true );
		$this->mock_method( $this->mmg_checkout, 'encrypt', null, new Exception( 'Encryption failed' ) );

		$order             = wc_create_order();
		$_POST['order_id'] = $order->get_id();
		$_REQUEST['nonce'] = 'valid_nonce';

		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		$response = wp_json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Error generating checkout URL: Encryption failed', $response['data'] );
	}

	/**
	 * Test handling of payment confirmation.
	 */
	public function test_handle_payment_confirmation() {
		$mock_payment = $this->createMock( MMG_Checkout_Payment::class );

		// Test case 1: Successful payment confirmation.
		$mock_payment = $this->getMockBuilder('MMG_Checkout_Payment')
		                     ->setMethods(['verify_callback_key', 'decrypt'])
		                     ->getMock();
		$mock_payment->method('verify_callback_key')->willReturn(true);
		$mock_payment->method('decrypt')->willReturn(wp_json_encode([
			'transaction_id' => '123456',
			'result_code'    => '0',
			'result_message' => 'Success',
		]));
		$_GET['token'] = 'valid_token';
		$order         = wc_create_order();
		$order->update_meta_data( '_mmg_transaction_id', '123456' );
		$order->save();

		ob_start();
		$mock_payment->handle_payment_confirmation();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Success', $output );
		$this->assertEquals( 'completed', $order->get_status() );

		// Test case 2: Invalid callback key.
		$mock_payment = $this->getMockBuilder('MMG_Checkout_Payment')
		                     ->setMethods(['verify_callback_key', 'decrypt'])
		                     ->getMock();
		$mock_payment->method('verify_callback_key')->willReturn(false);

		ob_start();
		$mock_payment->handle_payment_confirmation();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Invalid callback key', $output );
	}

	/**
	 * Mock the wp_verify_nonce function.
	 *
	 * @param bool $return_value The value to return from the mocked function.
	 */
	private function mock_wp_verify_nonce( $return_value = true ) {
		global $wp_filter;
		$wp_filter['wp_verify_nonce'] = new \WP_Hook();
		$wp_filter['wp_verify_nonce']->add_filter(
			'wp_verify_nonce',
			function () use ( $return_value ) {
				return $return_value;
			},
			10,
			2
		);
	}

	/**
	 * Mock a method on an object.
	 *
	 * @param object         $instance The object to mock.
	 * @param string         $method The method to mock.
	 * @param mixed          $return_value The value to return from the mocked method.
	 * @param Exception|null $exception The exception to throw, if any.
	 */
	private function mock_method( $instance, $method, $return_value, $exception = null ) {
		$instance->expects( $this->any() )
				->method( $method )
				->will( $exception ? $this->throwException( $exception ) : $this->returnValue( $return_value ) );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wc_webhooks" );
		wp_cache_flush();
		$GLOBALS['wpdb']->queries = array();
	}
}