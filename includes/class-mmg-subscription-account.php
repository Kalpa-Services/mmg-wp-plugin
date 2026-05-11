<?php
/**
 * MMG Subscription Account Class
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MMG_Subscription_Account class.
 */
class MMG_Subscription_Account {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_mmg-subscriptions_endpoint', array( $this, 'endpoint_content' ) );
		add_action( 'template_redirect', array( $this, 'handle_actions' ) );
	}

	/**
	 * Add subscriptions item to the WooCommerce account menu.
	 *
	 * @param array $items Existing menu items.
	 * @return array Modified menu items.
	 */
	public function add_menu_item( $items ) {
		$new_items = array();
		foreach ( $items as $key => $value ) {
			$new_items[ $key ] = $value;
			if ( 'dashboard' === $key ) {
				$new_items['mmg-subscriptions'] = 'My Subscriptions';
			}
		}
		return $new_items;
	}

	/**
	 * Render the my subscriptions endpoint content.
	 */
	public function endpoint_content() {
		global $wpdb;
		$user_id = get_current_user_id();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$subs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mmg_subscriptions WHERE customer_id = %d ORDER BY created_at DESC",
				$user_id
			)
		);

		if ( empty( $subs ) ) {
			echo '<p>You have no subscriptions.</p>';
			return;
		}

		echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders">
            <thead><tr>
                <th>Subscription</th><th>Status</th><th>Next Payment</th><th>Actions</th>
            </tr></thead><tbody>';

		foreach ( $subs as $sub ) {
			$product      = wc_get_product( $sub->product_id );
			$product_name = $product ? $product->get_name() : 'Unknown Product';
			$cancel_url   = wp_nonce_url( add_query_arg( 'cancel_mmg_sub', $sub->id ), 'cancel_sub_' . $sub->id );
			$halt_url     = wp_nonce_url( add_query_arg( 'halt_mmg_sub', $sub->id ), 'halt_sub_' . $sub->id );
			$renew_url    = wp_nonce_url( add_query_arg( 'renew_mmg_sub', $sub->id ), 'renew_sub_' . $sub->id );

			echo '<tr>
                <td data-title="Subscription">' . esc_html( $product_name ) . '</td>
                <td data-title="Status">' . esc_html( ucfirst( $sub->status ) ) . '</td>
                <td data-title="Next Payment">' . esc_html( $sub->next_payment_date ) . '</td>
                <td data-title="Actions">';

			if ( 'active' === $sub->status ) {
				echo '<a href="' . esc_url( $halt_url ) . '" class="woocommerce-button button">Pause</a> ';
				echo '<a href="' . esc_url( $cancel_url ) . '" class="woocommerce-button button cancel">Cancel</a> ';
				echo $this->render_upgrade_form( $sub );
			} elseif ( 'on-hold' === $sub->status ) {
				echo '<a href="' . esc_url( $renew_url ) . '" class="woocommerce-button button">Resume</a> ';
				echo '<a href="' . esc_url( $cancel_url ) . '" class="woocommerce-button button cancel">Cancel</a>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render upgrade form for a subscription.
	 *
	 * @param object $sub Subscription row.
	 * @return string HTML.
	 */
	private function render_upgrade_form( object $sub ): string {
		$nonce   = wp_create_nonce( 'upgrade_freq_' . $sub->id );
		$url     = add_query_arg(
			array(
				'upgrade_mmg_sub_freq' => $sub->id,
				'_wpnonce'             => $nonce,
			)
		);
		$periods = array(
			'day'   => 'Day',
			'week'  => 'Week',
			'month' => 'Month',
			'year'  => 'Year',
		);
		$options = '';
		foreach ( $periods as $val => $label ) {
			$sel      = selected( $val, $sub->billing_period, false );
			$options .= '<option value="' . esc_attr( $val ) . '"' . $sel . '>' . esc_html( $label ) . '</option>';
		}
		return '<form method="get" style="display:inline-block;margin-left:8px;">
            <input type="hidden" name="upgrade_mmg_sub_freq" value="' . esc_attr( $sub->id ) . '">
            <input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">
            <select name="period">' . $options . '</select>
            <input type="number" name="interval" value="' . esc_attr( $sub->billing_interval ) . '" min="1" style="width:50px;">
            <button type="submit" class="woocommerce-button button">Change Frequency</button>
        </form>';
	}

	/**
	 * Handle all subscription actions.
	 */
	public function handle_actions() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in each sub-handler.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in each sub-handler.
		if ( isset( $_GET['cancel_mmg_sub'] ) ) {
			$this->handle_cancel( (int) $_GET['cancel_mmg_sub'], $nonce, $user_id ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_GET['halt_mmg_sub'] ) ) {
			$this->handle_halt( (int) $_GET['halt_mmg_sub'], $nonce, $user_id ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_GET['renew_mmg_sub'] ) ) {
			$this->handle_renew( (int) $_GET['renew_mmg_sub'], $nonce, $user_id ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_GET['upgrade_mmg_sub_freq'] ) ) {
			$this->handle_upgrade_frequency( (int) $_GET['upgrade_mmg_sub_freq'], $nonce, $user_id ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_GET['mmg_pay_token'] ) ) {
			$this->handle_pay_token( sanitize_key( $_GET['mmg_pay_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}

	/**
	 * Cancel a subscription.
	 *
	 * @param int    $sub_id  Subscription ID.
	 * @param string $nonce   Request nonce.
	 * @param int    $user_id Current user ID.
	 */
	private function handle_cancel( int $sub_id, string $nonce, int $user_id ): void {
		if ( ! wp_verify_nonce( $nonce, 'cancel_sub_' . $sub_id ) ) {
			return;
		}
		global $wpdb;
		$sub = $this->fetch_owned_sub( $sub_id, $user_id );
		if ( ! $sub ) {
			return;
		}
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'mmg_subscriptions',
			array( 'status' => 'cancelled' ),
			array(
				'id'          => $sub_id,
				'customer_id' => $user_id,
			)
		);
		( new MMG_Subscription_Reminder_Scheduler() )->cancel_for_subscription( $sub_id );
		wc_add_notice( 'Subscription cancelled.', 'success' );
		wp_safe_redirect( wc_get_endpoint_url( 'mmg-subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Pause an active subscription.
	 *
	 * @param int    $sub_id  Subscription ID.
	 * @param string $nonce   Request nonce.
	 * @param int    $user_id Current user ID.
	 */
	private function handle_halt( int $sub_id, string $nonce, int $user_id ): void {
		if ( ! wp_verify_nonce( $nonce, 'halt_sub_' . $sub_id ) ) {
			return;
		}
		global $wpdb;
		$sub = $this->fetch_owned_sub( $sub_id, $user_id );
		if ( ! $sub || 'active' !== $sub->status ) {
			return;
		}
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'mmg_subscriptions',
			array( 'status' => 'on-hold' ),
			array(
				'id'          => $sub_id,
				'customer_id' => $user_id,
			)
		);
		as_unschedule_all_actions( 'mmg_subscription_renewal', array( 'subscription_id' => $sub_id ), 'mmg-subscriptions' );
		( new MMG_Subscription_Reminder_Scheduler() )->cancel_for_subscription( $sub_id );
		wc_add_notice( 'Subscription paused.', 'success' );
		wp_safe_redirect( wc_get_endpoint_url( 'mmg-subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Resume an on-hold subscription.
	 *
	 * @param int    $sub_id  Subscription ID.
	 * @param string $nonce   Request nonce.
	 * @param int    $user_id Current user ID.
	 */
	private function handle_renew( int $sub_id, string $nonce, int $user_id ): void {
		if ( ! wp_verify_nonce( $nonce, 'renew_sub_' . $sub_id ) ) {
			return;
		}
		global $wpdb;
		$sub = $this->fetch_owned_sub( $sub_id, $user_id );
		if ( ! $sub || 'on-hold' !== $sub->status ) {
			return;
		}

		$manager   = new MMG_Subscription_Manager();
		$next_date = $manager->calculate_next_date( current_time( 'mysql' ), $sub->billing_period, $sub->billing_interval );
		$next_ts   = strtotime( $next_date );
		if ( ! $next_ts ) {
			return;
		}
		$cycle_id = $sub->id . '-' . gmdate( 'Y-m-d', $next_ts );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'mmg_subscriptions',
			array(
				'status'             => 'active',
				'next_payment_date'  => $next_date,
				'payment_cycle_id'   => $cycle_id,
				'last_reminder_sent' => null,
			),
			array(
				'id'          => $sub_id,
				'customer_id' => $user_id,
			)
		);

		as_unschedule_all_actions( 'mmg_subscription_renewal', array( 'subscription_id' => $sub_id ), 'mmg-subscriptions' );
		$scheduler = new MMG_Subscription_Reminder_Scheduler();
		$scheduler->cancel_for_subscription( $sub_id );
		$scheduler->schedule_for_subscription( $sub_id, $next_date );
		as_enqueue_scheduled_action(
			strtotime( $next_date ),
			'mmg_subscription_renewal',
			array( 'subscription_id' => $sub_id ),
			'mmg-subscriptions'
		);

		wc_add_notice( 'Subscription resumed.', 'success' );
		wp_safe_redirect( wc_get_endpoint_url( 'mmg-subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Change the billing period and interval of an active subscription.
	 *
	 * @param int    $sub_id  Subscription ID.
	 * @param string $nonce   Request nonce.
	 * @param int    $user_id Current user ID.
	 */
	private function handle_upgrade_frequency( int $sub_id, string $nonce, int $user_id ): void {
		if ( ! wp_verify_nonce( $nonce, 'upgrade_freq_' . $sub_id ) ) {
			return;
		}
		$allowed_periods = array( 'day', 'week', 'month', 'year' );
		$period          = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '';
		$interval        = isset( $_GET['interval'] ) ? max( 1, intval( $_GET['interval'] ) ) : 1;

		if ( ! in_array( $period, $allowed_periods, true ) ) {
			return;
		}

		global $wpdb;
		$sub = $this->fetch_owned_sub( $sub_id, $user_id );
		if ( ! $sub || 'active' !== $sub->status ) {
			return;
		}

		$manager   = new MMG_Subscription_Manager();
		$next_date = $manager->calculate_next_date( current_time( 'mysql' ), $period, $interval );
		$next_ts   = strtotime( $next_date );
		if ( ! $next_ts ) {
			return;
		}
		$cycle_id = $sub->id . '-' . gmdate( 'Y-m-d', $next_ts );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'mmg_subscriptions',
			array(
				'billing_period'     => $period,
				'billing_interval'   => $interval,
				'next_payment_date'  => $next_date,
				'payment_cycle_id'   => $cycle_id,
				'last_reminder_sent' => null,
			),
			array(
				'id'          => $sub_id,
				'customer_id' => $user_id,
			)
		);

		as_unschedule_all_actions( 'mmg_subscription_renewal', array( 'subscription_id' => $sub_id ), 'mmg-subscriptions' );
		( new MMG_Subscription_Reminder_Scheduler() )->cancel_for_subscription( $sub_id );
		as_enqueue_scheduled_action(
			$next_ts,
			'mmg_subscription_renewal',
			array( 'subscription_id' => $sub_id ),
			'mmg-subscriptions'
		);
		( new MMG_Subscription_Reminder_Scheduler() )->schedule_for_subscription( $sub_id, $next_date );

		wc_add_notice( 'Subscription frequency updated.', 'success' );
		wp_safe_redirect( wc_get_endpoint_url( 'mmg-subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Handle pay-token URL from reminder email.
	 *
	 * @param string $token URL token.
	 */
	public function handle_pay_token( string $token ): void {
		$sub_id = get_transient( 'mmg_pay_token_' . $token );
		if ( ! $sub_id ) {
			wc_add_notice( 'This payment link has expired. Please contact support for a new one.', 'error' );
			wp_safe_redirect( wc_get_endpoint_url( 'mmg-subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}

		delete_transient( 'mmg_pay_token_' . $token );

		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sub = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mmg_subscriptions WHERE id = %d",
				(int) $sub_id
			)
		);

		if ( ! $sub || get_current_user_id() !== (int) $sub->customer_id ) {
			wp_safe_redirect( wc_get_endpoint_url( 'mmg-subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}

		$renewal_order = $this->create_renewal_order( $sub );
		$checkout      = MMG_Checkout_Payment::get_instance();

		try {
			$checkout_url = $checkout->build_mmg_checkout_url( $renewal_order );
		} catch ( Exception $e ) {
			wc_add_notice( 'Could not initiate payment. Please try again later.', 'error' );
			wp_safe_redirect( wc_get_endpoint_url( 'mmg-subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}

		wp_safe_redirect( $checkout_url );
		exit;
	}

	/**
	 * Create a renewal WC order for the given subscription.
	 *
	 * @param object $sub Subscription row.
	 * @return WC_Order
	 */
	private function create_renewal_order( object $sub ): WC_Order {
		$parent_order  = wc_get_order( $sub->order_id );
		$product       = wc_get_product( $sub->product_id );
		$renewal_order = wc_create_order(
			array(
				'customer_id' => (int) $sub->customer_id,
				'parent'      => (int) $sub->order_id,
			)
		);
		if ( $product ) {
			$renewal_order->add_product( $product, 1 );
		}
		if ( $parent_order ) {
			$renewal_order->set_currency( $parent_order->get_currency() );
		}
		$renewal_order->calculate_totals();
		$renewal_order->update_meta_data( '_mmg_payment_cycle_id', $sub->payment_cycle_id );
		$renewal_order->update_status( 'pending', 'Subscription renewal via reminder email.' );
		$renewal_order->save();
		return $renewal_order;
	}

	/**
	 * Fetch a subscription row owned by the given customer.
	 *
	 * @param int $sub_id     Subscription ID.
	 * @param int $customer_id Customer (user) ID.
	 * @return object|null
	 */
	private function fetch_owned_sub( int $sub_id, int $customer_id ) {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mmg_subscriptions WHERE id = %d AND customer_id = %d",
				$sub_id,
				$customer_id
			)
		);
	}

	/**
	 * Generate a signed 48-hour pay URL for use in reminder emails.
	 *
	 * @param int $sub_id Subscription ID.
	 * @return string Absolute URL with mmg_pay_token query arg.
	 */
	public static function generate_pay_token_url( int $sub_id ): string {
		$token = wp_generate_password( 32, false );
		set_transient( 'mmg_pay_token_' . $token, $sub_id, 2 * DAY_IN_SECONDS );
		return add_query_arg( array( 'mmg_pay_token' => $token ), wc_get_endpoint_url( 'mmg-subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) );
	}
}
