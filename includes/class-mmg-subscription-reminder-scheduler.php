<?php
/**
 * MMG Subscription Reminder Scheduler
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues and cancels Action Scheduler jobs for subscription reminders.
 */
class MMG_Subscription_Reminder_Scheduler {

	/**
	 * Enqueue reminder AS jobs for a subscription cycle.
	 *
	 * @param int    $subscription_id  Subscription ID.
	 * @param string $next_payment_date MySQL datetime of the next renewal.
	 */
	public function schedule_for_subscription( int $subscription_id, string $next_payment_date ): void {
		$offsets    = $this->get_reminder_offsets();
		$renewal_ts = strtotime( $next_payment_date );
		if ( false === $renewal_ts ) {
			MMG_Logger::error( "Invalid next_payment_date '{$next_payment_date}' for subscription #{$subscription_id}.", 'errors' );
			return;
		}

		foreach ( $offsets as $days_before ) {
			$reminder_ts = $renewal_ts - ( (int) $days_before * DAY_IN_SECONDS );
			as_enqueue_scheduled_action(
				$reminder_ts,
				'mmg_subscription_reminder',
				array(
					'subscription_id' => $subscription_id,
					'days_before'     => (int) $days_before,
				),
				'mmg-subscriptions'
			);
		}
	}

	/**
	 * Cancel all pending reminder and renewal jobs for a subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function cancel_for_subscription( int $subscription_id ): void {
		$args = array( 'subscription_id' => $subscription_id );
		as_unschedule_all_actions( 'mmg_subscription_reminder', $args, 'mmg-subscriptions' );
		as_unschedule_all_actions( 'mmg_subscription_renewal', $args, 'mmg-subscriptions' );
	}

	/**
	 * Read admin-configured offsets (days before renewal).
	 *
	 * @return int[]
	 */
	private function get_reminder_offsets(): array {
		$raw     = get_option( 'mmg_reminder_schedule', '' );
		$offsets = $raw ? json_decode( $raw, true ) : null;
		return ( is_array( $offsets ) && ! empty( $offsets ) ) ? $offsets : array( 3 );
	}
}
