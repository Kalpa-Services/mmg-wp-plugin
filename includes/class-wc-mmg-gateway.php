<?php
/**
 * MMG Gateway for WooCommerce
 *
 * This class defines the MMG payment gateway for WooCommerce.
 *
 * @package MMG_Checkout
 */

/**
 * WC_MMG_Gateway class.
 */
class WC_MMG_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'mmg_checkout';
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = 'MMG Checkout';
		$this->method_description = 'Enables MMG Checkout Payment flow for WooCommerce';

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->supports = array(
			'products',
			'refunds',
			'checkout_block_support',
		);

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
	}

	/**
	 * Initialize gateway form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable MMG Checkout',
				'default' => 'yes',
			),
			'title'       => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'MMG Checkout',
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => 'Description',
				'type'        => 'textarea',
				'description' => 'This controls the description which the user sees during checkout.',
				'default'     => 'Pay with MMG Checkout',
			),
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );
		echo '<div id="mmg-checkout-container" style="width: 100%;">';
		echo '<button id="mmg-checkout-button" class="button alt" data-order-id="' . esc_attr( $order_id ) . '" style="background-color: #147047; color: white; display: flex; align-items: center; justify-content: center; padding: 15px 20px; width: 100%; font-size: 18px; border-radius: 6px; border: none;">';
		echo '<img src="' . esc_url( plugins_url( 'assets/mmg-logo-white.png', __DIR__ ) ) . '" alt="MMG Logo" style="height: 30px; margin-right: 10px;">';
		echo 'Pay with MMG</button>';
		echo '</div>';
	}
}
