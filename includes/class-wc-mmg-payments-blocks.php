<?php
/**
 * MMG Payments Blocks Integration
 *
 * Integrates MMG Checkout payment method with WooCommerce Blocks.
 *
 * @package MMG_Checkout
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WC_MMG_Payments_Blocks class.
 */
class WC_MMG_Payments_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'mmg_checkout';

	/**
	 * Initialize the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_mmg_checkout_settings', array() );
	}

	/**
	 * Check if the payment method is active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-mmg-payments-blocks',
			plugins_url( 'admin/js/mmg-checkout-blocks.js', __DIR__ ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			filemtime( plugin_dir_path( __DIR__ ) . 'admin/js/mmg-checkout-blocks.js' ),
			true
		);
		return array( 'wc-mmg-payments-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$currency       = get_woocommerce_currency();
		$rates          = get_option( 'mmg_currency_rates', array() );
		$rate           = 1;
		$has_conversion = false;

		if ( 'GYD' !== $currency && isset( $rates[ $currency ] ) && 'yes' === $rates[ $currency ]['enabled'] ) {
			$rate           = floatval( $rates[ $currency ]['rate'] );
			$has_conversion = true;
		}

		return array(
			'title'           => $this->settings['title'] ?? 'MMG Checkout',
			'description'     => $this->settings['description'] ?? '',
			'currency'        => $currency,
			'currency_symbol' => get_woocommerce_currency_symbol(),
			'rate'            => $rate,
			'has_conversion'  => $has_conversion,
			'supports'        => $this->get_supported_features(),
		);
	}
}
