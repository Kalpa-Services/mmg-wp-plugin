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
		add_action( 'mmg_subscription_renewal',  array( $this, 'process_renewal' ) );
		add_action( 'mmg_subscription_reminder', array( $this, 'process_reminder' ) );
		add_action( 'mmg_mit_payment_confirmed', array( $this, 'on_mit_payment_confirmed' ) );
	}

	/**
	 * Process a scheduled subscription renewal — delegates to renewal handler.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function process_renewal( $subscription_id ) {
		( new MMG_Subscription_Renewal_Handler() )->process_renewal( (int) $subscription_id );
	}

	/**
	 * Process a scheduled reminder — sends reminder email and updates last_reminder_sent.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @param int $days_before     Days before renewal this reminder fires.
	 */
	public function process_reminder( $subscription_id, $days_before = 3 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sub = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}mmg_subscriptions WHERE id = %d AND status = 'active'",
			(int) $subscription_id
		) );

		if ( ! $sub ) {
			return;
		}

		$payment_url = MMG_Subscription_Account::generate_pay_token_url( (int) $sub->id );
		( new MMG_Subscription_Email() )->send_reminder( $sub, $payment_url );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'mmg_subscriptions',
			array( 'last_reminder_sent' => current_time( 'mysql' ) ),
			array( 'id' => (int) $sub->id )
		);

		MMG_Logger::info( "Reminder sent for subscription #{$subscription_id} ({$days_before}d before renewal).", 'api-requests' );
	}

	/**
	 * Handle MIT payment confirmed event.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function on_mit_payment_confirmed( $subscription_id ) {
		( new MMG_Subscription_Renewal_Handler() )->on_mit_payment_confirmed( (int) $subscription_id );
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
			$txn_id = isset( $data['transaction_id'] ) ? $data['transaction_id'] : ( $data['transactionId'] ?? '' );
			$order->payment_complete( $txn_id );
			$order->add_order_note( sprintf( 'Payment confirmed via webhook. Transaction ID: %s', $txn_id ) );

			// Handle tokenization if present.
			$token = isset( $data['payment_token'] ) ? $data['payment_token'] : '';
			if ( ! empty( $token ) ) {
				$this->save_payment_token( $order, $token );

				// Activate native subscription if applicable.
				if ( class_exists( 'MMG_Subscription_Manager' ) ) {
					MMG_Subscription_Manager::activate_subscription( $order->get_id(), $token );
				}
			}

			// If this is a renewal order (cycle ID present), fire confirmed hook.
			$cycle_id = $order->get_meta( '_mmg_payment_cycle_id', true );
			if ( ! empty( $cycle_id ) ) {
				$sub_id = (int) explode( '-', $cycle_id )[0];
				if ( $sub_id ) {
					do_action( 'mmg_mit_payment_confirmed', $sub_id );
				}
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
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'mmg_subscriptions',
			array( 'status' => 'cancelled' ),
			array( 'order_id' => $order->get_id() )
		);
		$order->add_order_note( 'Native subscription cancelled via webhook.' );
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
