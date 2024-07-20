<?php

class Test_MMG_Checkout_Payment extends WP_UnitTestCase {
    private $mmg_checkout;

    public function setUp() {
        parent::setUp();
        $this->mmg_checkout = new MMG_Checkout_Payment();
    }

    public function test_generate_checkout_url() {
        // Mock the necessary options
        update_option('mmg_mode', 'demo');
        update_option('mmg_secret_key', 'test_secret_key');
        update_option('mmg_merchant_id', 'test_merchant_id');
        update_option('mmg_client_id', 'test_client_id');
        update_option('mmg_rsa_public_key', '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAy8Dbv8prpJ/0kKhlGeJY\nozo2t60EG8L0561g13R29LvMR5hyvGZlGJpmn65+A4xHXInJYiPuKzrKUnApeLZ+\nvw1HocOAZtWK0z3r26uA8kQYOKX9Qt/DbCdvsF9wF8gRK0ptx9M6R13NvBxvVQAp\nfc9jB9nTzphOgM4JiEYvlV8FLhg9yZovMYd6Wwf3aoXK891VQxTr/kQYoq1Yp+68\ni6T4nNq7NWC+UNVjQHxNQMQMzU6lWCX8zyg3yH88OAQkUXIXKfQ+NkvYQ1cxaMoV\nPpY72+eVthKzpMeyHkBn7ciumk5qgLTEJAfWZpe4f4eFZj/Rc8Y8Jj2IS5kVPjUy\nwQIDAQAB\n-----END PUBLIC KEY-----');

        // Create a test order
        $order = wc_create_order();
        $order->set_total(100);

        // Set up the POST request
        $_POST['order_id'] = $order->get_id();

        // Capture the output
        ob_start();
        $this->mmg_checkout->generate_checkout_url();
        $output = ob_get_clean();

        // Decode the JSON response
        $response = json_decode($output, true);

        // Assert that the response is successful
        $this->assertTrue($response['success']);

        // Assert that a checkout URL is returned
        $this->assertArrayHasKey('checkout_url', $response['data']);

        // Assert that the checkout URL contains expected parameters
        $checkout_url = $response['data']['checkout_url'];
        $this->assertStringContainsString('token=', $checkout_url);
        $this->assertStringContainsString('merchantId=test_merchant_id', $checkout_url);
        $this->assertStringContainsString('X-Client-ID=test_client_id', $checkout_url);

        // Assert that the order meta is updated
        $this->assertEquals($order->get_id(), $order->get_meta('_mmg_transaction_id'));
    }
}