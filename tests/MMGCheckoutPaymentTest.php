<?php

namespace MMG_Checkout_Payment;

use PHPUnit\Framework\TestCase;
use MMG_Checkout\MMG_Checkout_Payment;

class MMGCheckoutPaymentTest extends TestCase {
    private $mmg_checkout;

    protected function setUp(): void {
        parent::setUp();
        $this->mmg_checkout = $this->getMockBuilder(MMG_Checkout_Payment::class)
            ->setMethods(['mmgcp_validate_public_key', 'mmgcp_encrypt', 'mmgcp_url_safe_base64_encode', 'mmgcp_get_checkout_url', 'mmgcp_generate_checkout_url'])
            ->getMock();
        
        $this->mmg_checkout->method('mmgcp_validate_public_key')->willReturn(true);
        $this->mmg_checkout->method('mmgcp_encrypt')->willReturn('encrypted_data');
        $this->mmg_checkout->method('mmgcp_url_safe_base64_encode')->willReturn('encoded_token');
        $this->mmg_checkout->method('mmgcp_get_checkout_url')->willReturn('https://example.com/checkout');

        $this->mmg_checkout->method('mmgcp_generate_checkout_url')->willReturnCallback(function() {
            $checkout_url = 'https://example.com/checkout?token=encoded_token&merchantId=test_merchant_id&X-Client-ID=test_client_id';
            echo json_encode(['success' => true, 'data' => ['checkout_url' => $checkout_url]]);
        });

        $this->setup_global_function_mocks();
    }

    public function test_generate_checkout_url() {
        $_POST['order_id'] = 123;
        $_REQUEST['nonce'] = 'valid_nonce';

        ob_start();
        $this->mmg_checkout->mmgcp_generate_checkout_url();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('checkout_url', $response['data']);
        $this->assertStringContainsString('token=encoded_token', $response['data']['checkout_url']);
        $this->assertStringContainsString('merchantId=test_merchant_id', $response['data']['checkout_url']);
        $this->assertStringContainsString('X-Client-ID=test_client_id', $response['data']['checkout_url']);
    }

    private function setup_global_function_mocks() {
        global $wp_verify_nonce, $wc_get_order, $get_option, $add_query_arg;

        $wp_verify_nonce = function($nonce, $action) {
            return $nonce === 'valid_nonce' && $action === 'mmg_checkout_nonce';
        };

        $wc_get_order = function($order_id) {
            return new class {
                public function get_id() { return 123; }
                public function get_total() { return 100.00; }
                public function get_order_number() { return '123'; }
                public function update_meta_data($key, $value) {}
                public function save() {}
            };
        };

        $get_option = function($option, $default = false) {
            $options = [
                'mmg_secret_key' => 'test_secret_key',
                'mmg_merchant_id' => 'test_merchant_id',
                'mmg_merchant_name' => 'Test Merchant',
                'mmg_client_id' => 'test_client_id',
            ];
            return $options[$option] ?? $default;
        };

        $add_query_arg = function($args, $url) {
            return $url . '?' . http_build_query($args);
        };
    }

    protected function tearDown(): void {
        $this->teardown_global_function_mocks();
        parent::tearDown();
    }

    private function teardown_global_function_mocks() {
        global $wp_verify_nonce, $wc_get_order, $get_option, $add_query_arg;
        unset($wp_verify_nonce, $wc_get_order, $get_option, $add_query_arg);
    }
}