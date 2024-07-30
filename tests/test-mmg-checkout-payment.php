<?php

use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey;

class Test_MMG_Checkout_Payment extends TestCase {

    protected $mmg_checkout_payment;

    protected function setUp(): void {
        parent::setUp();
        Brain\Monkey\setUp();
        
        // Mock WordPress functions
        Brain\Monkey\Functions\stubs([
            'get_option',
            'update_option',
            'home_url',
            'wp_generate_password',
            'is_checkout_pay_page',
            'plugin_dir_url',
            'admin_url',
            'wp_create_nonce',
            'wp_verify_nonce',
            'wc_get_order',
            'wp_send_json_error',
            'wp_send_json_success',
            'add_query_arg',
            'wp_json_encode',
            'openssl_pkey_get_public',
            'wc_add_notice',
            'wp_safe_redirect',
            'wp_die',
        ]);

        $this->mmg_checkout_payment = Mockery::mock('MMG\CheckoutPayment\MMG_Checkout_Payment')->makePartial();
    }

    protected function tearDown(): void {
        Mockery::close();
        Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_generate_unique_callback_url() {
        $callback_key = 'test_key';
        Brain\Monkey\Functions\expect('get_option')
            ->with('mmg_callback_key')
            ->andReturn($callback_key);

        Brain\Monkey\Functions\expect('home_url')
            ->with("wc-api/mmg-checkout/{$callback_key}")
            ->andReturn("http://example.com/wc-api/mmg-checkout/{$callback_key}");

        $result = $this->invokeMethod($this->mmg_checkout_payment, 'generate_unique_callback_url');

        $this->assertEquals("http://example.com/wc-api/mmg-checkout/{$callback_key}", $result);
    }

    public function test_generate_checkout_url_success() {
        $_REQUEST['nonce'] = 'valid_nonce';
        $_POST['order_id'] = 123;

        Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->andReturn(true);

        $this->mmg_checkout_payment->shouldReceive('validate_public_key')
            ->andReturn(true);

        $mock_order = Mockery::mock('WC_Order');
        $mock_order->shouldReceive('get_total')->andReturn(100);
        $mock_order->shouldReceive('get_order_number')->andReturn('123');
        $mock_order->shouldReceive('get_id')->andReturn(123);
        $mock_order->shouldReceive('update_meta_data');
        $mock_order->shouldReceive('save');

        Brain\Monkey\Functions\expect('wc_get_order')
            ->with(123)
            ->andReturn($mock_order);

        $this->mmg_checkout_payment->shouldReceive('encrypt')
            ->andReturn('encrypted_data');

        $this->mmg_checkout_payment->shouldReceive('url_safe_base64_encode')
            ->andReturn('encoded_data');

        $this->mmg_checkout_payment->shouldReceive('get_checkout_url')
            ->andReturn('http://checkout.example.com');

        Brain\Monkey\Functions\expect('wp_send_json_success')
            ->once()
            ->with(Mockery::type('array'));

        $this->mmg_checkout_payment->generate_checkout_url();
    }

    public function test_generate_checkout_url_invalid_nonce() {
        $_REQUEST['nonce'] = 'invalid_nonce';

        Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->andReturn(false);

        Brain\Monkey\Functions\expect('wp_send_json_error')
            ->once()
            ->with(Mockery::type('string'));

        $this->mmg_checkout_payment->generate_checkout_url();
    }

    public function test_generate_checkout_url_invalid_public_key() {
        $_REQUEST['nonce'] = 'valid_nonce';

        Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->andReturn(true);

        $this->mmg_checkout_payment->shouldReceive('validate_public_key')
            ->andReturn(false);

        Brain\Monkey\Functions\expect('wp_send_json_error')
            ->once()
            ->with(Mockery::type('string'));

        $this->mmg_checkout_payment->generate_checkout_url();
    }

    public function test_handle_error_payment() {
        $_GET['token'] = 'valid_token';

        $this->mmg_checkout_payment->shouldReceive('verify_callback_key')
            ->andReturn(true);

        $this->mmg_checkout_payment->shouldReceive('url_safe_base64_decode')
            ->andReturn('decoded_token');

        $this->mmg_checkout_payment->shouldReceive('decrypt')
            ->andReturn([
                'merchantTransactionId' => 123,
                'errorCode' => 1,
                'errorMessage' => 'Test Error'
            ]);

        $mock_order = Mockery::mock('WC_Order');
        $mock_order->shouldReceive('update_status')
            ->with('failed', Mockery::type('string'));
        $mock_order->shouldReceive('get_checkout_payment_url')
            ->andReturn('http://example.com/checkout');

        Brain\Monkey\Functions\expect('wc_get_order')
            ->with(123)
            ->andReturn($mock_order);

        Brain\Monkey\Functions\expect('wc_add_notice')
            ->once();

        Brain\Monkey\Functions\expect('wp_safe_redirect')
            ->once()
            ->with('http://example.com/checkout');

        $this->expectOutputString('');
        $this->mmg_checkout_payment->handle_error_payment();
    }

    public function test_handle_payment_confirmation_success() {
        $_GET['token'] = 'valid_token';

        $this->mmg_checkout_payment->shouldReceive('verify_callback_key')
            ->andReturn(true);

        $this->mmg_checkout_payment->shouldReceive('url_safe_base64_decode')
            ->andReturn('decoded_token');

        $this->mmg_checkout_payment->shouldReceive('decrypt')
            ->andReturn([
                'merchantTransactionId' => 123,
                'resultCode' => 0,
                'transactionId' => 'txn_123'
            ]);

        $mock_order = Mockery::mock('WC_Order');
        $mock_order->shouldReceive('payment_complete');
        $mock_order->shouldReceive('add_order_note');
        $mock_order->shouldReceive('get_checkout_order_received_url')
            ->andReturn('http://example.com/order-received');

        Brain\Monkey\Functions\expect('wc_get_order')
            ->with(123)
            ->andReturn($mock_order);

        Brain\Monkey\Functions\expect('wp_safe_redirect')
            ->once()
            ->with('http://example.com/order-received');

        $this->expectOutputString('');
        $this->mmg_checkout_payment->handle_payment_confirmation();
    }

    public function test_handle_payment_confirmation_failure() {
        $_GET['token'] = 'valid_token';

        $this->mmg_checkout_payment->shouldReceive('verify_callback_key')
            ->andReturn(true);

        $this->mmg_checkout_payment->shouldReceive('url_safe_base64_decode')
            ->andReturn('decoded_token');

        $this->mmg_checkout_payment->shouldReceive('decrypt')
            ->andReturn([
                'merchantTransactionId' => 123,
                'resultCode' => 1,
                'resultMessage' => 'Payment Failed'
            ]);

        $mock_order = Mockery::mock('WC_Order');
        $mock_order->shouldReceive('update_status')
            ->with('failed', Mockery::type('string'));
        $mock_order->shouldReceive('get_checkout_payment_url')
            ->andReturn('http://example.com/checkout');

        Brain\Monkey\Functions\expect('wc_get_order')
            ->with(123)
            ->andReturn($mock_order);

        Brain\Monkey\Functions\expect('wp_safe_redirect')
            ->once()
            ->with('http://example.com/checkout');

        $this->expectOutputString('');
        $this->mmg_checkout_payment->handle_payment_confirmation();
    }

    private function invokeMethod(&$object, $methodName, array $parameters = array()) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
