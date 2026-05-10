<?php
/**
 * MMG Action Scheduler Handler
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MMG_Action_Scheduler_Handler class.
 */
class MMG_Action_Scheduler_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'mmg_process_webhook_event', array( $this, 'process_event' ) );
	}

	/**
	 * Process a queued webhook event.
	 *
	 * @param array $data Event data.
	 */
	public function process_event( $data ) {
		$event_type = isset( $data['event_type'] ) ? $data['event_type'] : '';
		$order_id   = isset( $data['order_id'] ) ? intval( $data['order_id'] ) : 0;

		MMG_Logger::info( "Processing background event: {$event_type} for Order #{$order_id}", 'webhooks' );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			MMG_Logger::error( "Order #{$order_id} not found for webhook event.", 'webhooks' );
			return;
		}

		switch ( $event_type ) {
			case 'payment.success':
				$this->handle_payment_success( $order, $data );
				break;
			case 'payment.failed':
				$this->handle_payment_failed( $order, $data );
				break;
			case 'subscription.cancelled':
				$this->handle_subscription_cancelled( $order );
				break;
			default:
				MMG_Logger::warning( "Unknown event type: {$event_type}", 'webhooks' );
				break;
		}
	}

	/**
	 * Handle successful payment event.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Event data.
	 */
	protected function handle_payment_success( $order, $data ) {
		if ( ! $order->is_paid() ) {
			$txn_id = isset( $data['transaction_id'] ) ? $data['transaction_id'] : '';
			$order->payment_complete( $txn_id );
			$order->add_order_note( sprintf( 'Payment confirmed via webhook. Transaction ID: %s', $txn_id ) );

			// Handle tokenization if present.
			if ( ! empty( $data['payment_token'] ) ) {
				$this->save_payment_token( $order, $data['payment_token'] );
			}
		}
	}

	/**
	 * Handle failed payment event.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Event data.
	 */
	protected function handle_payment_failed( $order, $data ) {
		$reason = isset( $data['failure_reason'] ) ? $data['failure_reason'] : 'Unknown reason';
		$order->update_status( 'failed', sprintf( 'Payment failed via webhook. Reason: %s', $reason ) );
	}

	/**
	 * Handle subscription cancelled event.
	 *
	 * @param WC_Order $order Order object.
	 */
	protected function handle_subscription_cancelled( $order ) {
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order );
			foreach ( $subscriptions as $subscription ) {
				$subscription->update_status( 'cancelled', 'Subscription cancelled via MMG webhook.' );
			}
		}
	}

	/**
	 * Save payment token for recurring payments.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $token Payment token.
	 */
	protected function save_payment_token( $order, $token ) {
		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		$token_obj = new WC_Payment_Token_CC();
		$token_obj->set_token( $token );
		$token_obj->set_gateway_id( 'mmg_checkout' );
		$token_obj->set_user_id( $user_id );

		// In a real scenario, MMG might provide last4 and card type.
		$token_obj->set_last4( 'MMG' );
		$token_obj->set_card_type( 'mWallet' );
		$token_obj->set_expiry_month( '12' );
		$token_obj->set_expiry_year( gmdate( 'Y' ) + 10 );

		$token_obj->save();

		$order->add_payment_token( $token_obj );
		MMG_Logger::info( "Payment token saved for User #{$user_id}", 'api-requests' );
	}
}
