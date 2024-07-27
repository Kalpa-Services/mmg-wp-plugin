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
	 * Test the generate_checkout_url method.
	 */
	public function test_generate_checkout_url() {
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
		// Create a test order.
		$order = wc_create_order();
		$order->set_total( 100 );

		// Set up the POST request.
		$_POST['order_id'] = $order->get_id();

		// Capture the output.
		ob_start();
		$this->mmg_checkout->generate_checkout_url();
		$output = ob_get_clean();

		// Decode the JSON response.
		$response = json_decode( $output, true );

		// Assert that the response is successful.
		$this->assertTrue( $response['success'], 'URL generation failed: ' . wp_json_encode( $response ) );

		// Assert that a checkout URL is returned.
		$this->assertArrayHasKey( 'checkout_url', $response['data'], 'Checkout URL not found in response.' );

		// Assert that the checkout URL contains expected parameters.
		$checkout_url = $response['data']['checkout_url'];
		$this->assertStringContainsString( 'token=', $checkout_url, 'Token not found in checkout URL.' );
		$this->assertStringContainsString( 'merchantId=test_merchant_id', $checkout_url, 'Merchant ID not found in checkout URL.' );
		$this->assertStringContainsString( 'X-Client-ID=test_client_id', $checkout_url, 'Client ID not found in checkout URL.' );

		// Assert that the order meta is updated.
		$this->assertEquals( $order->get_id(), $order->get_meta( '_mmg_transaction_id' ), 'Order meta not updated correctly.' );

		// Fail the test if the checkout URL is empty.
		$this->assertNotEmpty( $checkout_url, 'Checkout URL generation failed. URL is empty.' );
	}
}
