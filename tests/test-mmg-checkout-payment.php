<?php
/**
 * Tests for MMG Checkout Payment functionality.
 *
 * @package MMG_Checkout_Payment
 */

/**
 * Test case for MMG_Checkout_Payment class.
 */
class Test_MMG_Checkout_Payment extends WP_UnitTestCase {

	/**
	 * The MMG_Checkout_Payment instance.
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
	 * Test case 1: Successful generation of checkout URL.
	 */
	public function test_successful_generate_checkout_url() {
		// Mock necessary functions and methods.
		$this->mock_wp_verify_nonce( true );
		$this->mmg_checkout->method( 'validate_public_key' )->willReturn( true );
		$this->mmg_checkout->method( 'encrypt' )->willReturn( 'encrypted_data' );
		$this->mmg_checkout->method( 'url_safe_base64_encode' )->willReturn( 'encoded_data' );

		// Create a test order.
		$order = wc_create_order();
		$order->set_total( 100 );

		// Set up POST data.
		$_POST['order_id'] = $order->get_id();
		$_REQUEST['nonce'] = 'valid_nonce';

		// Set up options.
		update_option( 'mmg_secret_key', 'test_secret_key' );
		update_option( 'mmg_merchant_id', 'test_merchant_id' );
		update_option( 'mmg_client_id', 'test_client_id' );

		// Capture the output.
		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		// Decode the JSON response.
		$response = json_decode( $output, true );

		// Assert the response is successful and contains a checkout URL.
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'checkout_url', $response['data'] );
		$this->assertStringContainsString( 'token=encoded_data', $response['data']['checkout_url'] );
	}

	/**
	 * Test case 2: Invalid nonce.
	 */
	public function test_invalid_nonce_generate_checkout_url() {
		$this->mock_wp_verify_nonce( false );

		$_REQUEST['nonce'] = 'invalid_nonce';

		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid security token', $response['data'] );
	}

	/**
	 * Test case 3: Invalid public key.
	 */
	public function test_invalid_public_key_generate_checkout_url() {
		$this->mock_wp_verify_nonce( true );
		$this->mmg_checkout->method( 'validate_public_key' )->willReturn( false );

		$_REQUEST['nonce'] = 'valid_nonce';

		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid RSA public key', $response['data'] );
	}

	/**
	 * Test case 4: Invalid order.
	 */
	public function test_invalid_order_generate_checkout_url() {
		$this->mock_wp_verify_nonce( true );
		$this->mmg_checkout->method( 'validate_public_key' )->willReturn( true );

		$_POST['order_id'] = 999999; // Non-existent order ID.
		$_REQUEST['nonce'] = 'valid_nonce';

		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Invalid order', $response['data'] );
	}

	/**
	 * Test case 5: Encryption failure.
	 */
	public function test_encryption_failure_generate_checkout_url() {
		$this->mock_wp_verify_nonce( true );
		$this->mmg_checkout->method( 'validate_public_key' )->willReturn( true );
		$this->mmg_checkout->method( 'encrypt' )->willThrowException( new Exception( 'Encryption failed' ) );

		$order             = wc_create_order();
		$_POST['order_id'] = $order->get_id();
		$_REQUEST['nonce'] = 'valid_nonce';

		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Error generating checkout URL: Encryption failed', $response['data'] );
	}

	/**
	 * Mock the verify_callback_key method of the payment object.
	 *
	 * @param MMG_Checkout_Payment $mock_payment The mocked payment object.
	 * @param bool                 $return_value The value to return from the mocked method.
	 */
	protected function mock_verify_callback_key( $mock_payment, $return_value = true ) {
		$mock_payment->method( 'verify_callback_key' )
					->willReturn( $return_value );
	}

	/**
	 * Mock the wp_verify_nonce function.
	 *
	 * @param bool $return_value The value to return from the mocked function.
	 */
	protected function mock_wp_verify_nonce( $return_value = true ) {
		global $wp_filter;
		$wp_filter['wp_verify_nonce'] = new WP_Hook();
		$wp_filter['wp_verify_nonce']->add_filter(
			'wp_verify_nonce',
			function () use ( $return_value ) {
				return $return_value;
			}
		);
	}

	/**
	 * Test the handle_payment_confirmation method.
	 */
	public function test_handle_payment_confirmation() {
		$mock_payment = $this->getMockBuilder( MMG_Checkout_Payment::class )
			->setMethods( array( 'decrypt', 'url_safe_base64_decode', 'verify_callback_key' ) )
			->getMock();

		// Test case 1: Successful payment confirmation.
		$this->mock_verify_callback_key( $mock_payment, true );
		$mock_payment->method( 'decrypt' )->willReturn(
			wp_json_encode(
				array(
					'transaction_id' => '123456',
					'result_code'    => '0',
					'result_message' => 'Success',
				)
			)
		);
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
		$this->mock_verify_callback_key( $mock_payment, false );

		ob_start();
		$mock_payment->handle_payment_confirmation();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Invalid callback key', $output );

		// Test case 3: Decryption failure.
		$this->mock_verify_callback_key( $mock_payment, true );
		$mock_payment->method( 'decrypt' )->willReturn( false );

		ob_start();
		$mock_payment->handle_payment_confirmation();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Decryption failed', $output );

		// Test case 4: Payment failure.
		$mock_payment->method( 'decrypt' )->willReturn(
			wp_json_encode(
				array(
					'transaction_id' => '123456',
					'result_code'    => '2',
					'result_message' => 'Payment Failed',
				)
			)
		);

		ob_start();
		$mock_payment->handle_payment_confirmation();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Payment failed', $output );
		$this->assertEquals( 'failed', $order->get_status() );

		// Test case 5: Order not found.
		$mock_payment->method( 'decrypt' )->willReturn(
			wp_json_encode(
				array(
					'transaction_id' => 'non_existent',
					'result_code'    => '0',
					'result_message' => 'Success',
				)
			)
		);

		ob_start();
		$mock_payment->handle_payment_confirmation();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Order not found', $output );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();
		global $wp_filter;
		unset( $wp_filter['wp_verify_nonce'] );
	}
}
