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
	}

	/**
	 * Output payment fields.
	 */
	public function payment_fields() {
		parent::payment_fields();
		$this->display_currency_conversion_notice();
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
		$total = 0;
		if ( is_object( WC()->cart ) ) {
			$total = floatval( WC()->cart->get_total( 'edit' ) );
		}

		$converted_total = round( $total * $rate );
		?>
		<div class="mmg-checkout-conversion-notice" style="margin-top: 15px; padding: 15px; background: #fffbeb; border: 1px solid #f59e0b; border-radius: 8px; font-size: 13px; color: #92400e; line-height: 1.6;">
			<div style="display: flex; align-items: flex-start; gap: 10px;">
				<span class="dashicons dashicons-info" style="font-size: 20px; color: #f59e0b; margin-top: 2px;"></span>
				<div>
					<div style="font-weight: 700; margin-bottom: 4px;"><?php esc_html_e( 'Currency Conversion', 'mmg-checkout-payment' ); ?></div>
					<div>
						<?php
						printf(
							esc_html__( 'Your total of %s will be converted to GYD at a rate of 1 %s = %s GYD.', 'mmg-checkout-payment' ),
							'<strong>' . wc_price( $total ) . '</strong>',
							esc_html( $currency ),
							'<strong>' . esc_html( $rate ) . '</strong>'
						);
						?>
					</div>
					<div style="margin-top: 8px; font-size: 15px; font-weight: 700; color: #1e2a3a;">
						<?php
						printf(
							esc_html__( 'Total to Pay: %s GYD', 'mmg-checkout-payment' ),
							number_format( $converted_total, 0 )
						);
						?>
					</div>
				</div>
			</div>
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
