<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_MMG_Payments_Blocks extends AbstractPaymentMethodType {
	protected $name = 'mmg_checkout';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_mmg_checkout_settings', array() );
	}

	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-mmg-payments-blocks',
			plugins_url( 'js/mmg-checkout-blocks.js', __DIR__ ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			filemtime( plugin_dir_path( __DIR__ ) . 'js/mmg-checkout-blocks.js' ),
			true
		);
		return array( 'wc-mmg-payments-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->settings['title'],
			'description' => $this->settings['description'],
		);
	}
}
