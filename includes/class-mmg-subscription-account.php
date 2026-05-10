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
	 * Add menu item to My Account.
	 *
	 * @param array $items Menu items.
	 * @return array
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
	 * Render content.
	 */
	public function endpoint_content() {
		global $wpdb;
		$user_id = get_current_user_id();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$subs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmg_subscriptions WHERE customer_id = %d ORDER BY created_at DESC", $user_id ) );

		if ( empty( $subs ) ) {
			echo '<p>You have no active subscriptions.</p>';
			return;
		}

		echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders">
			<thead>
				<tr>
					<th>Subscription</th>
					<th>Status</th>
					<th>Next Payment</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>';

		foreach ( $subs as $sub ) {
			$product      = wc_get_product( $sub->product_id );
			$product_name = $product ? $product->get_name() : 'Unknown Product';
			$cancel_url   = wp_nonce_url( add_query_arg( 'cancel_mmg_sub', $sub->id ), 'cancel_sub_' . $sub->id );

			echo '<tr>
				<td data-title="Subscription">' . esc_html( $product_name ) . '</td>
				<td data-title="Status">' . esc_html( ucfirst( $sub->status ) ) . '</td>
				<td data-title="Next Payment">' . esc_html( $sub->next_payment_date ) . '</td>
				<td data-title="Actions">';
			if ( 'active' === $sub->status ) {
				echo '<a href="' . esc_url( $cancel_url ) . '" class="woocommerce-button button cancel">Cancel</a>';
			}
			echo '</td>
			</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Handle actions (like cancel).
	 */
	public function handle_actions() {
		if ( isset( $_GET['cancel_mmg_sub'] ) && isset( $_GET['_wpnonce'] ) ) {
			$sub_id = intval( $_GET['cancel_mmg_sub'] );
			$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			if ( wp_verify_nonce( $nonce, 'cancel_sub_' . $sub_id ) ) {
				global $wpdb;
				$user_id = get_current_user_id();

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->update(
					$wpdb->prefix . 'mmg_subscriptions',
					array( 'status' => 'cancelled' ),
					array(
						'id'          => $sub_id,
						'customer_id' => $user_id,
					)
				);

				wc_add_notice( 'Subscription cancelled successfully.', 'success' );
				wp_safe_redirect( wc_get_endpoint_url( 'mmg-subscriptions' ) );
				exit;
			}
		}
	}
}
