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
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'display_currency_conversion_notice' ) );
	}

	/**
	 * Display currency conversion notice on checkout.
	 */
	public function display_currency_conversion_notice() {
		$currency = get_woocommerce_currency();
		if ( 'GYD' === $currency ) {
			return;
		}

		$rates = get_option( 'mmg_currency_rates', array() );
		if ( ! isset( $rates[ $currency ] ) || 'yes' !== $rates[ $currency ]['enabled'] ) {
			return;
		}

		$rate = floatval( $rates[ $currency ]['rate'] );
		?>
		<div class="mmg-checkout-conversion-notice" style="margin-bottom: 20px; padding: 15px; background: #f8f9fb; border: 1px solid #e2e6ed; border-radius: 8px; font-size: 13px; color: #64748b; line-height: 1.5;">
			<span class="dashicons dashicons-info" style="font-size: 18px; color: #0f9b8e; margin-right: 8px; vertical-align: middle;"></span>
			<?php
			printf(
				esc_html__( 'Total will be converted to GYD at an exchange rate of 1 %s = %s GYD.', 'mmg-checkout-payment' ),
				esc_html( $currency ),
				esc_html( $rate )
			);
			?>
		</div>
		<?php
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
				'default' => 'no',
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
				'default'     => 'Use your MMG account to pay for your order.',
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

		try {
			$mmg          = MMG_Checkout_Payment::get_instance();
			$checkout_url = $mmg->build_mmg_checkout_url( $order );

			return array(
				'result'   => 'success',
				'redirect' => $checkout_url,
			);
		} catch ( Exception $e ) {
			wc_add_notice( __( 'Payment error: ', 'mmg-checkout-payment' ) . esc_html( $e->getMessage() ), 'error' );
			return array( 'result' => 'failure' );
		}
	}

	/**
	 * Create and return an MMG API client instance.
	 *
	 * @return MMG_API_Client
	 */
	protected function make_api_client() {
		require_once __DIR__ . '/class-mmg-api-client.php';
		return new MMG_API_Client();
	}

	/**
	 * Process a refund via the MMG reversal API.
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount   Refund amount (unused — full reversal only).
	 * @param string $reason   Refund reason (unused).
	 * @return true|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order  = wc_get_order( $order_id );
		$txn_id = $order->get_meta( '_mmg_transaction_id' );
		if ( empty( $txn_id ) ) {
			return new WP_Error( 'mmg_refund_error', 'No MMG transaction ID found for this order.' );
		}
		$mode = get_option( 'mmg_mode', 'demo' );
		$mid  = get_option( "mmg_{$mode}_merchant_id" );
		try {
			$this->make_api_client()->reversal( $mid, $txn_id );
			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'mmg_refund_error', $e->getMessage() );
		}
	}
}
