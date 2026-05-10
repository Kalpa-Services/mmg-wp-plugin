<?php
namespace Automattic\WooCommerce\Blocks\Payments\Integrations {
	if ( ! class_exists( AbstractPaymentMethodType::class ) ) {
		abstract class AbstractPaymentMethodType {
			protected $name;
			protected $settings = [];
			public function get_supported_features() { return []; }
			abstract public function initialize();
			abstract public function is_active();
			abstract public function get_payment_method_script_handles();
			abstract public function get_payment_method_data();
		}
	}
}

namespace {
	if ( ! function_exists( 'get_woocommerce_currency' ) ) {
		function get_woocommerce_currency() {
			return $GLOBALS['mmg_test_currency'] ?? 'GYD';
		}
	}
	if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
		function get_woocommerce_currency_symbol( $currency = '' ) {
			return $GLOBALS['mmg_test_currency_symbol'] ?? 'G$';
		}
	}
	if ( ! function_exists( 'plugins_url' ) ) {
		function plugins_url( $path = '', $plugin = '' ) { return 'http://example.com/' . $path; }
	}
	if ( ! function_exists( 'plugin_dir_path' ) ) {
		function plugin_dir_path( $file ) { return '/tmp/'; }
	}
	if ( ! function_exists( 'wp_register_script' ) ) {
		function wp_register_script() {}
	}
	if ( ! function_exists( 'WC' ) ) {
		function WC() {
			static $wc = null;
			if ( ! $wc ) {
				$wc = new class {
					public $cart = null;
				};
			}
			return $wc;
		}
	}

	require_once dirname( __DIR__ ) . '/includes/class-wc-mmg-payments-blocks.php';

	class MMGPaymentsBlocksTest extends \PHPUnit\Framework\TestCase {

		protected function setUp(): void {
			parent::setUp();
			$GLOBALS['mmg_test_options']         = [];
			$GLOBALS['mmg_test_currency']        = 'USD';
			$GLOBALS['mmg_test_currency_symbol'] = '$';
		}

		private function makeBlocks( array $overrides = [] ): WC_MMG_Payments_Blocks {
			$GLOBALS['mmg_test_options']['woocommerce_mmg_checkout_settings'] = array_merge(
				[ 'enabled' => 'yes', 'title' => 'MMG Checkout', 'description' => 'Pay with MMG' ],
				$overrides
			);
			$blocks = new WC_MMG_Payments_Blocks();
			$blocks->initialize();
			return $blocks;
		}

		public function test_has_conversion_true_when_rate_configured() {
			$GLOBALS['mmg_test_currency']                        = 'USD';
			$GLOBALS['mmg_test_options']['mmg_currency_rates']   = [
				'USD' => [ 'rate' => '215', 'enabled' => 'yes' ],
			];
			$data = $this->makeBlocks()->get_payment_method_data();
			$this->assertTrue( $data['has_conversion'] );
			$this->assertEquals( 215.0, $data['rate'] );
		}

		public function test_has_conversion_false_for_gyd_currency() {
			$GLOBALS['mmg_test_currency'] = 'GYD';
			$data = $this->makeBlocks()->get_payment_method_data();
			$this->assertFalse( $data['has_conversion'] );
			$this->assertEquals( 1, $data['rate'] );
		}

		public function test_has_conversion_false_when_no_rate_exists() {
			$GLOBALS['mmg_test_currency']                      = 'USD';
			$GLOBALS['mmg_test_options']['mmg_currency_rates'] = [];
			$data = $this->makeBlocks()->get_payment_method_data();
			$this->assertFalse( $data['has_conversion'] );
		}

		public function test_has_conversion_false_when_rate_disabled() {
			$GLOBALS['mmg_test_currency']                      = 'USD';
			$GLOBALS['mmg_test_options']['mmg_currency_rates'] = [
				'USD' => [ 'rate' => '215', 'enabled' => 'no' ],
			];
			$data = $this->makeBlocks()->get_payment_method_data();
			$this->assertFalse( $data['has_conversion'] );
		}

		public function test_has_conversion_false_when_rate_is_zero() {
			$GLOBALS['mmg_test_currency']                      = 'USD';
			$GLOBALS['mmg_test_options']['mmg_currency_rates'] = [
				'USD' => [ 'rate' => '0', 'enabled' => 'yes' ],
			];
			$data = $this->makeBlocks()->get_payment_method_data();
			$this->assertFalse( $data['has_conversion'] );
		}

		public function test_currency_symbol_included_in_data() {
			$GLOBALS['mmg_test_currency_symbol'] = '$';
			$data = $this->makeBlocks()->get_payment_method_data();
			$this->assertArrayHasKey( 'currency_symbol', $data );
			$this->assertEquals( '$', $data['currency_symbol'] );
		}

		public function test_cart_computed_keys_absent_from_data() {
			$data = $this->makeBlocks()->get_payment_method_data();
			$this->assertArrayNotHasKey( 'total', $data );
			$this->assertArrayNotHasKey( 'converted_total', $data );
			$this->assertArrayNotHasKey( 'price_formatted', $data );
		}
	}
}
