<?php
// Stub WC_Payment_Gateway so the file can be loaded without WooCommerce.
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    class WC_Payment_Gateway {
        public $id; public $has_fields; public $method_title;
        public $method_description; public $title; public $description; public $supports = [];
        public function init_form_fields() {}
        public function init_settings() {}
        public function get_option( $k, $d = '' ) { return $d; }
    }
}
require_once dirname(__DIR__) . '/includes/class-mmg-checkout-payment.php';

class MMGWebhookTest extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mmg_test_options']    = ['mmg_callback_key' => 'secret-key-abc'];
        $GLOBALS['mmg_test_transients'] = [];
        $GLOBALS['mmg_test_orders']     = [];
        $_SERVER['REQUEST_URI']         = '/wc-api/mmg-checkout/secret-key-abc';
        $_SERVER['REQUEST_METHOD']      = 'POST';
    }

    protected function tearDown(): void {
        unset( $_POST['token'] );
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }

    public function test_webhook_completes_order_on_result_code_zero() {
        $order = new class {
            public $paid = false; public $notes = [];
            public function is_paid() { return $this->paid; }
            public function payment_complete() { $this->paid = true; }
            public function add_order_note( $n ) { $this->notes[] = $n; }
        };
        $GLOBALS['mmg_test_orders'][42] = $order;
        $_POST['token'] = 'encoded-token';

        $client = $this->getMockBuilder( MMG_Checkout_Payment::class )
            ->onlyMethods( ['verify_callback_key', 'get_raw_post_body', 'url_safe_base64_decode', 'decrypt', 'extract_order_id'] )
            ->getMock();
        $client->method( 'verify_callback_key' )->willReturn( true );
        $client->method( 'get_raw_post_body' )->willReturn( '' );
        $client->method( 'url_safe_base64_decode' )->willReturn( 'decoded' );
        $client->method( 'decrypt' )->willReturn( [
            'merchantTransactionId' => '42-1',
            'ResultCode'            => 0,
            'transactionId'         => 'TXN-OK',
        ] );
        $client->method( 'extract_order_id' )->willReturn( 42 );

        try { $client->handle_webhook_notification_public(); }
        catch ( MMGTestJsonException $e ) { $this->assertSame( 'success', $e->type ); }

        $this->assertTrue( $order->paid );
        $this->assertStringContainsString( 'TXN-OK', $order->notes[0] );
    }

    public function test_webhook_rejects_invalid_callback_key_with_403() {
        $client = $this->getMockBuilder( MMG_Checkout_Payment::class )
            ->onlyMethods( ['verify_callback_key'] )
            ->getMock();
        $client->method( 'verify_callback_key' )->willReturn( false );

        try { $client->handle_webhook_notification_public(); }
        catch ( MMGTestJsonException $e ) {
            $this->assertSame( 'error', $e->type );
            $this->assertSame( 403, $e->status );
        }
    }

    public function test_webhook_returns_200_on_decrypt_failure_to_prevent_retries() {
        $_POST['token'] = 'bad';
        $client = $this->getMockBuilder( MMG_Checkout_Payment::class )
            ->onlyMethods( ['verify_callback_key', 'get_raw_post_body', 'url_safe_base64_decode', 'decrypt'] )
            ->getMock();
        $client->method( 'verify_callback_key' )->willReturn( true );
        $client->method( 'get_raw_post_body' )->willReturn( '' );
        $client->method( 'url_safe_base64_decode' )->willReturn( 'x' );
        $client->method( 'decrypt' )->willThrowException( new Exception( 'bad key' ) );

        try { $client->handle_webhook_notification_public(); }
        catch ( MMGTestJsonException $e ) {
            $this->assertSame( 'success', $e->type, 'Must return 200 even on decrypt failure' );
        }
    }

    public function test_webhook_does_not_double_complete_already_paid_order() {
        $order = new class {
            public $completed_count = 0;
            public function is_paid() { return true; }
            public function payment_complete() { $this->completed_count++; }
            public function add_order_note( $n ) {}
        };
        $GLOBALS['mmg_test_orders'][55] = $order;
        $_POST['token'] = 'tok';

        $client = $this->getMockBuilder( MMG_Checkout_Payment::class )
            ->onlyMethods( ['verify_callback_key', 'get_raw_post_body', 'url_safe_base64_decode', 'decrypt', 'extract_order_id'] )
            ->getMock();
        $client->method( 'verify_callback_key' )->willReturn( true );
        $client->method( 'get_raw_post_body' )->willReturn( '' );
        $client->method( 'url_safe_base64_decode' )->willReturn( 'd' );
        $client->method( 'decrypt' )->willReturn( ['merchantTransactionId' => '55-1', 'ResultCode' => 0, 'transactionId' => 'T'] );
        $client->method( 'extract_order_id' )->willReturn( 55 );

        try { $client->handle_webhook_notification_public(); } catch ( MMGTestJsonException $e ) {}

        $this->assertSame( 0, $order->completed_count );
    }
}
