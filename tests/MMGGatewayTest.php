<?php
require_once dirname(__DIR__) . '/includes/class-mmg-api-client.php';

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    class WC_Payment_Gateway {
        public $id; public $has_fields; public $method_title;
        public $method_description; public $title; public $description; public $supports = [];
        public $form_fields = [];
        public function init_form_fields() {}
        public function init_settings() {}
        public function get_option( $k, $d = '' ) { return $d; }
    }
}
if ( ! function_exists( 'plugins_url' ) ) { function plugins_url( $p, $b ) { return ''; } }
if ( ! function_exists( 'esc_attr' ) )    { function esc_attr( $s ) { return $s; } }
if ( ! function_exists( 'esc_url' ) )     { function esc_url( $u ) { return $u; } }

require_once dirname(__DIR__) . '/includes/class-wc-mmg-gateway.php';

class MMGGatewayTest extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mmg_test_options']    = ['mmg_mode' => 'demo', 'mmg_demo_merchant_id' => 'MID'];
        $GLOBALS['mmg_test_transients'] = [];
        $GLOBALS['mmg_test_orders']     = [];
    }

    public function test_process_refund_returns_true_on_success() {
        $order = new class { public function get_meta( $k ) { return 'TXN-123'; } };
        $GLOBALS['mmg_test_orders'][99] = $order;

        $api = $this->getMockBuilder( MMG_API_Client::class )->onlyMethods(['reversal'])->getMock();
        $api->method('reversal')->willReturn([]);

        $gateway = $this->getMockBuilder( WC_MMG_Gateway::class )->onlyMethods(['make_api_client'])->getMock();
        $gateway->method('make_api_client')->willReturn( $api );

        $this->assertTrue( $gateway->process_refund( 99 ) );
    }

    public function test_process_refund_returns_wp_error_on_failure() {
        $order = new class { public function get_meta( $k ) { return 'TXN-456'; } };
        $GLOBALS['mmg_test_orders'][88] = $order;

        $api = $this->getMockBuilder( MMG_API_Client::class )->onlyMethods(['reversal'])->getMock();
        $api->method('reversal')->willThrowException( new Exception('reversal failed') );

        $gateway = $this->getMockBuilder( WC_MMG_Gateway::class )->onlyMethods(['make_api_client'])->getMock();
        $gateway->method('make_api_client')->willReturn( $api );

        $result = $gateway->process_refund( 88 );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'reversal failed', $result->get_error_message() );
    }
}
