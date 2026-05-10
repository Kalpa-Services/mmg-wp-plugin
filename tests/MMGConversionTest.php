<?php
require_once dirname(__DIR__) . '/includes/class-mmg-checkout-payment.php';

class MMGConversionTest extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mmg_test_options']    = [];
        $GLOBALS['mmg_test_transients'] = [];
    }

    public function test_conversion_usd_to_gyd() {
        $GLOBALS['mmg_test_options']['mmg_mode'] = 'demo';
        $GLOBALS['mmg_test_options']['mmg_currency_rates'] = [
            'USD' => ['rate' => 215, 'enabled' => 'yes']
        ];
        
        // Generate a dummy key for validate_public_key to pass.
        $res = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($res);
        $GLOBALS['mmg_test_options']['mmg_demo_rsa_public_key'] = $details['key'];

        $order = $this->createMock( \WC_Order::class );
        $order->method( 'get_currency' )->willReturn( 'USD' );
        $order->method( 'get_total' )->willReturn( 100.00 );
        $order->method( 'get_id' )->willReturn( 123 );
        $order->method( 'get_order_number' )->willReturn( '123' );
        $order->method( 'get_meta' )->willReturn( '' );

        $payment_mock = $this->getMockBuilder( MMG_Checkout_Payment::class )
            ->onlyMethods( ['encrypt', 'get_checkout_url', 'url_safe_base64_encode'] )
            ->getMock();
        
        $payment_mock->method( 'get_checkout_url' )->willReturn( 'https://test.url' );
        $payment_mock->method( 'url_safe_base64_encode' )->willReturn( 'token' );
        
        $captured_data = null;
        $payment_mock->method( 'encrypt' )->willReturnCallback( function($data) use (&$captured_data) {
            $captured_data = $data;
            return 'encrypted';
        } );

        $payment_mock->build_mmg_checkout_url( $order );

        $this->assertEquals( 21500, $captured_data['amount'] );
    }

    public function test_no_conversion_for_gyd() {
        $GLOBALS['mmg_test_options']['mmg_mode'] = 'demo';
        $GLOBALS['mmg_test_options']['mmg_currency_rates'] = [
            'USD' => ['rate' => 215, 'enabled' => 'yes']
        ];
        
        $res = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($res);
        $GLOBALS['mmg_test_options']['mmg_demo_rsa_public_key'] = $details['key'];

        $order = $this->createMock( \WC_Order::class );
        $order->method( 'get_currency' )->willReturn( 'GYD' );
        $order->method( 'get_total' )->willReturn( 5000.00 );
        $order->method( 'get_id' )->willReturn( 456 );
        $order->method( 'get_order_number' )->willReturn( '456' );
        $order->method( 'get_meta' )->willReturn( '' );

        $payment_mock = $this->getMockBuilder( MMG_Checkout_Payment::class )
            ->onlyMethods( ['encrypt', 'get_checkout_url', 'url_safe_base64_encode'] )
            ->getMock();
        
        $payment_mock->method( 'get_checkout_url' )->willReturn( 'https://test.url' );
        $payment_mock->method( 'url_safe_base64_encode' )->willReturn( 'token' );
        
        $captured_data = null;
        $payment_mock->method( 'encrypt' )->willReturnCallback( function($data) use (&$captured_data) {
            $captured_data = $data;
            return 'encrypted';
        } );

        $payment_mock->build_mmg_checkout_url( $order );

        $this->assertEquals( 5000.00, $captured_data['amount'] );
    }
}
