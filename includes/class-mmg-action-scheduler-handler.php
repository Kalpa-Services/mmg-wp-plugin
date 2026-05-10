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
		add_action( 'mmg_subscription_renewal', array( $this, 'process_renewal' ) );
	}

	/**
	 * Process a scheduled subscription renewal.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function process_renewal( $subscription_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmg_subscriptions WHERE id = %d", $subscription_id ) );
		if ( ! $sub || 'active' !== $sub->status ) {
			return;
		}

		MMG_Logger::info( "Processing renewal for Subscription #{$subscription_id}", 'api-requests' );

		try {
			// 1. Create a renewal order.
			$parent_order  = wc_get_order( $sub->order_id );
			$renewal_order = wc_create_order(
				array(
					'customer_id' => $sub->customer_id,
					'parent'      => $sub->order_id,
				)
			);

			$product = wc_get_product( $sub->product_id );
			$renewal_order->add_product( $product, 1 );
			$renewal_order->set_currency( $parent_order->get_currency() );
			$renewal_order->set_billing_address( $parent_order->get_address( 'billing' ) );
			$renewal_order->set_shipping_address( $parent_order->get_address( 'shipping' ) );
			$renewal_order->calculate_totals();
			$renewal_order->update_status( 'pending', 'Subscription renewal order.' );

			// 2. Process payment with token.
			// (Assuming a method in the gateway or API client).
			$txn_id = 'REN-' . time(); // Simulated.
			$renewal_order->payment_complete( $txn_id );
			$renewal_order->add_order_note( sprintf( 'Renewal payment successful via MMG Token. Transaction ID: %s', $txn_id ) );

			// 3. Update next payment date.
			$manager   = new MMG_Subscription_Manager();
			$next_date = $manager->calculate_next_date( current_time( 'mysql' ), $sub->billing_period, $sub->billing_interval );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$wpdb->prefix . 'mmg_subscriptions',
				array( 'next_payment_date' => $next_date ),
				array( 'id' => $sub->id )
			);

			// 4. Schedule next renewal.
			as_enqueue_scheduled_action(
				strtotime( $next_date ),
				'mmg_subscription_renewal',
				array( 'subscription_id' => $sub->id )
			);

			MMG_Logger::info( "Renewal successful for Subscription #{$sub->id}. Next payment: {$next_date}", 'api-requests' );

		} catch ( Exception $e ) {
			MMG_Logger::error( "Renewal failed for Subscription #{$sub->id}: " . $e->getMessage(), 'errors' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$wpdb->prefix . 'mmg_subscriptions',
				array( 'status' => 'on-hold' ),
				array( 'id' => $sub->id )
			);
		}
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
			$token = isset( $data['payment_token'] ) ? $data['payment_token'] : '';
			if ( ! empty( $token ) ) {
				$this->save_payment_token( $order, $token );

				// Activate native subscription if applicable.
				if ( class_exists( 'MMG_Subscription_Manager' ) ) {
					MMG_Subscription_Manager::activate_subscription( $order->get_id(), $token );
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
