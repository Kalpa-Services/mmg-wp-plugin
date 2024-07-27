<?php
/**
 * Tests for MMG Checkout Payment Confirmation functionality.
 *
 * @package MMG_Checkout_Payment
 */

/**
 * Test case for MMG Checkout Payment Confirmation.
 */
class Test_MMG_Checkout_Payment_Confirmation extends WP_UnitTestCase {

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
	 * Test the handle_payment_confirmation method.
	 */
	public function test_handle_payment_confirmation() {
		// Mock the necessary options.
		update_option( 'mmg_mode', 'demo' );
		update_option( 'mmg_secret_key', 'test_secret_key' );
		update_option( 'mmg_merchant_id', 'test_merchant_id' );
		update_option( 'mmg_client_id', 'test_client_id' );
		update_option(
			'mmg_rsa_public_key',
			'-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA2gCt71gxSnxl2CJFVgoM
faRs2BMdTtenp+L+OGlq6kYYyu7FWtVUxZB/1sO6nmc+CrWR6hurOai4n/ncmSjT
5rDLEJWYJ3nVW5WTnLWKEBFc0/iMj0bkXdZGki8zasPyLU8FEf2LaROCAYewJoMw
EFAQW/ADmkj5KUSEG3yw1/mG9p6FpeM4XGUutKVd6iDyRbiw8f13Oi+cuEX633Gy
1t0ekjccA3KKTsjN/yAW9/AAlaE1V9TncxPMYjlFLAhiM6x1/0EVRxc/klrpb4hB
hTjjXstTjC1+DHUMOLvyVk16SBlRSseH7nl4vxnk3vXKK7fS8uaP0UYWFJmdQ72V
7IlFY9eBVnQ3/Cdt0hppn+kuLwdSajaVVMWrfpw2Udu+/21xtjov1X18/gCeRCl5
V87qSOrXFPpsRISp4R+bSHOAphtgzH51weulKc2S/+xxTkCwI/rpCaWCUf0261eS
WwbpO706Pg4yTm9b6H5vJaRkpKuFRz1ZIl8dMLFB6Gsk7w9GfTjDcXpOOliOVe/z
zlgdXkUZ5UFs2cHntRnhF9TY845t9eEYQQYtqjuPhWbAECnbPWVbMSsMrKYWncm8
U773Uj8gK3ThyDdsjSX05PWuT6+7clzBYIfoal78UazPN8PMKd7YqiW7sims6xyo
LZ1DV5QsoLWZiIjeidgsmOMCAwEAAQ==
-----END PUBLIC KEY-----'
		);
		update_option( 'mmg_callback_key', 'test_callback_key' );

		// Create a test order.
		$order = wc_create_order();
		$order->set_total( 100 );
		$order->save();

		// Set up the GET request.
		$_SERVER['REQUEST_URI'] = '/wc-api/mmg-checkout/test_callback_key';
		$_GET['token']          = 'test_token';

		// Mock the decrypt method to return expected payment data.
		$this->mmg_checkout = $this->getMockBuilder( MMG_Checkout_Payment::class )
			->setMethods( array( 'decrypt' ) )
			->getMock();

		$this->mmg_checkout->method( 'decrypt' )->willReturn(
			array(
				'merchantTransactionId' => $order->get_id(),
				'resultCode'            => 0,
				'resultMessage'         => 'Success',
				'transactionId'         => 'test_transaction_id',
			)
		);

		// Capture the output.
		ob_start();
		$this->mmg_checkout->handle_payment_confirmation();
		$output = ob_get_clean();

		// Assert that the order status is updated to completed.
		$order = wc_get_order( $order->get_id() );
		$this->assertEquals( 'completed', $order->get_status(), 'Order status not updated correctly.' );

		// Assert that the order note is added.
		$notes = $order->get_customer_order_notes();
		$this->assertNotEmpty( $notes, 'Order note not added.' );
		$this->assertStringContainsString( 'Payment completed via MMG Checkout. Transaction ID: test_transaction_id', $notes[0]->comment_content, 'Order note content incorrect.' );
	}
}
