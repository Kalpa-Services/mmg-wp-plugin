<?php
/**
 * MMG Subscription Renewal Handler
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the recurring payment lifecycle: idempotency check, MIT API call,
 * auto-halt on failure, and cycle advancement.
 */
class MMG_Subscription_Renewal_Handler {

	/**
	 * API client for MIT payment calls.
	 *
	 * @var MMG_API_Client
	 */
	private $api_client;

	/**
	 * Email handler for subscription notifications.
	 *
	 * @var MMG_Subscription_Email
	 */
	private $email;

	/**
	 * Reminder scheduler for managing AS reminder jobs.
	 *
	 * @var MMG_Subscription_Reminder_Scheduler
	 */
	private $scheduler;

	/**
	 * Subscription manager for date calculations.
	 *
	 * @var MMG_Subscription_Manager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param MMG_API_Client                      $api_client Optional injected API client.
	 * @param MMG_Subscription_Email              $email      Optional injected email handler.
	 * @param MMG_Subscription_Reminder_Scheduler $scheduler  Optional injected scheduler.
	 * @param MMG_Subscription_Manager            $manager    Optional injected manager.
	 */
	public function __construct(
		MMG_API_Client $api_client = null,
		MMG_Subscription_Email $email = null,
		MMG_Subscription_Reminder_Scheduler $scheduler = null,
		MMG_Subscription_Manager $manager = null
	) {
		$this->api_client = null !== $api_client ? $api_client : new MMG_API_Client();
		$this->email      = null !== $email ? $email : new MMG_Subscription_Email();
		$this->scheduler  = null !== $scheduler ? $scheduler : new MMG_Subscription_Reminder_Scheduler();
		$this->manager    = null !== $manager ? $manager : new MMG_Subscription_Manager();
	}

	/**
	 * Process a scheduled subscription renewal.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function process_renewal( int $subscription_id ): void {
		$sub = $this->get_subscription( $subscription_id );
		if ( ! $sub || 'active' !== $sub->status ) {
			return;
		}

		if ( $this->is_cycle_paid( $sub->payment_cycle_id ) ) {
			$this->advance_cycle( $sub );
			return;
		}

		try {
			$this->call_mit_api( $sub );
			MMG_Logger::info( "MIT payment initiated for subscription #{$subscription_id}.", 'api-requests' );
		} catch ( Exception $e ) {
			MMG_Logger::error( "MIT payment failed for subscription #{$subscription_id}: " . $e->getMessage(), 'errors' );
			$this->halt_subscription( (int) $sub->id );
			$this->email->send_payment_failed( $sub, $e->getMessage() );
		}
	}

	/**
	 * Advance cycle and send confirmation after MIT payment confirmed via webhook.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function on_mit_payment_confirmed( int $subscription_id ): void {
		$sub = $this->get_subscription( $subscription_id );
		if ( ! $sub ) {
			return;
		}
		$this->advance_cycle( $sub );
		$this->email->send_payment_confirmed( $sub );
	}

	/**
	 * Get subscription row from DB.
	 *
	 * @param int $id Subscription ID.
	 * @return object|null
	 */
	protected function get_subscription( int $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mmg_subscriptions WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Check if the current billing cycle was already paid via the reminder email link.
	 *
	 * @param string $cycle_id payment_cycle_id value.
	 * @return bool
	 */
	protected function is_cycle_paid( string $cycle_id ): bool {
		if ( empty( $cycle_id ) ) {
			return false;
		}
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				'status'     => array( 'wc-completed', 'wc-processing' ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'   => '_mmg_payment_cycle_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value' => $cycle_id,
			)
		);
		return ! empty( $orders );
	}

	/**
	 * Call the Merchant Initiated Transactions payment API.
	 *
	 * @param object $sub Subscription row.
	 * @return array API response.
	 * @throws Exception On API failure.
	 */
	protected function call_mit_api( object $sub ): array {
		$mode        = get_option( 'mmg_mode', 'demo' );
		$merchant_id = get_option( "mmg_{$mode}_merchant_id" );
		$product     = wc_get_product( $sub->product_id );
		$amount      = $product ? (int) round( (float) $product->get_price() ) : 0;

		return $this->api_client->initiate_payment(
			array(
				'merchant_msisdn'         => $merchant_id,
				'payment_token'           => $sub->payment_token,
				'amount'                  => $amount,
				'currency'                => 'GYD',
				'merchant_transaction_id' => $sub->payment_cycle_id,
				'description'             => 'Subscription renewal: ' . ( $product ? $product->get_name( 'edit' ) : "#{$sub->id}" ),
			)
		);
	}

	/**
	 * Set subscription status to on-hold.
	 *
	 * @param int $id Subscription ID.
	 */
	protected function halt_subscription( int $id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'mmg_subscriptions',
			array( 'status' => 'on-hold' ),
			array( 'id' => $id )
		);
	}

	/**
	 * Calculate next payment date, rotate cycle ID, clear reminder state, schedule next jobs.
	 *
	 * @param object $sub Subscription row.
	 */
	protected function advance_cycle( object $sub ): void {
		global $wpdb;

		$next_date    = $this->manager->calculate_next_date(
			current_time( 'mysql' ),
			$sub->billing_period,
			$sub->billing_interval
		);
		$new_cycle_id = $sub->id . '-' . gmdate( 'Y-m-d', strtotime( $next_date ) );

		// Optimistic lock: only advance if the cycle ID hasn't changed yet (prevents double-advance on duplicate AS job).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$wpdb->prefix . 'mmg_subscriptions',
			array(
				'next_payment_date'  => $next_date,
				'payment_cycle_id'   => $new_cycle_id,
				'last_reminder_sent' => null,
			),
			array(
				'id'               => (int) $sub->id,
				'payment_cycle_id' => $sub->payment_cycle_id,
			)
		);
		if ( ! $updated ) {
			return;
		}

		$this->scheduler->schedule_for_subscription( (int) $sub->id, $next_date );
		as_enqueue_scheduled_action(
			strtotime( $next_date ),
			'mmg_subscription_renewal',
			array( 'subscription_id' => (int) $sub->id ),
			'mmg-subscriptions'
		);

		MMG_Logger::info( "Subscription #{$sub->id} cycle advanced. Next: {$next_date}", 'api-requests' );
	}
}
