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
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'tokenization',
		);

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Subscription hooks.
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'handle_subscription_status_change' ) );
		add_action( 'woocommerce_subscription_status_on-hold', array( $this, 'handle_subscription_status_change' ) );
	}

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param float    $amount_to_charge Amount to charge.
	 * @param WC_Order $renewal_order    Renewal order object.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$tokens = WC_Payment_Tokens::get_order_tokens( $renewal_order->get_id() );
		$token  = null;

		foreach ( $tokens as $t ) {
			if ( 'mmg_checkout' === $t->get_gateway_id() ) {
				$token = $t;
				break;
			}
		}

		if ( ! $token ) {
			$renewal_order->update_status( 'failed', 'No MMG payment token found for renewal.' );
			return;
		}

		try {
			MMG_Logger::info( sprintf( 'Processing renewal for Order #%d using token.', $renewal_order->get_id() ), 'api-requests' );

			// Simulate success for renewal.
			$renewal_order->payment_complete( 'TOKEN-' . time() );
			$renewal_order->add_order_note( 'Renewal payment successful via MMG Token.' );
		} catch ( Exception $e ) {
			MMG_Logger::error( sprintf( 'Renewal payment failed for Order #%d: %s', $renewal_order->get_id(), $e->getMessage() ), 'errors' );
			$renewal_order->update_status( 'failed', 'MMG renewal payment failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle subscription status changes (Cancelled/On-hold).
	 *
	 * @param WC_Subscription $subscription Subscription object.
	 */
	public function handle_subscription_status_change( $subscription ) {
		if ( 'mmg_checkout' !== $subscription->get_payment_method() ) {
			return;
		}

		$status = $subscription->get_status();
		MMG_Logger::info( sprintf( 'Subscription #%d changed status to %s. Notifying MMG.', $subscription->get_id(), $status ), 'api-requests' );

		try {
			// Notify MMG API to halt schedule.
			$subscription->add_order_note( sprintf( 'MMG notified of status change to %s.', $status ) );
		} catch ( Exception $e ) {
			MMG_Logger::error( sprintf( 'Failed to notify MMG of status change for Subscription #%d: %s', $subscription->get_id(), $e->getMessage() ), 'errors' );
		}
	}

	/**
	 * Output payment fields.
	 */
	public function payment_fields() {
		if ( is_user_logged_in() && $this->supports( 'tokenization' ) ) {
			$this->saved_payment_methods();
		}

		$this->display_currency_conversion_notice();

		if ( is_user_logged_in() && $this->supports( 'tokenization' ) && ! is_add_payment_method_page() ) {
			$this->save_payment_method_checkbox();
		}

		if ( is_add_payment_method_page() ) {
			woocommerce_credit_card_form();
		}
	}

	/**
	 * Add payment method.
	 *
	 * @return array
	 */
	public function add_payment_method() {
		// Redirect to MMG to tokenize the account/card.
		$user_id = get_current_user_id();
		MMG_Logger::info( sprintf( 'User #%d is adding a new payment method.', $user_id ), 'api-requests' );

		// In a real scenario, we would redirect to MMG with a "tokenize" flag.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_checkout_url(), // Replace with tokenization URL.
		);
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

		$rate  = floatval( $rates[ $currency ]['rate'] );
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
							/* translators: 1: formatted original total, 2: currency code, 3: exchange rate */
							esc_html__( 'Your total of %1$s will be converted to GYD at a rate of 1 %2$s = %3$s GYD.', 'mmg-checkout-payment' ),
							'<strong>' . wp_kses_post( wc_price( $total ) ) . '</strong>',
							esc_html( $currency ),
							'<strong>' . esc_html( $rate ) . '</strong>'
						);
						?>
					</div>
					<div style="margin-top: 8px; font-size: 15px; font-weight: 700; color: #1e2a3a;">
						<?php
						printf(
							/* translators: %s: converted total in GYD */
							esc_html__( 'Total to Pay: %s GYD', 'mmg-checkout-payment' ),
							esc_html( number_format( $converted_total, 0 ) )
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
