<?php
/**
 * MMG Subscription Manager
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/models/class-mmg-subscription-model.php';

/**
 * MMG_Subscription_Manager class.
 */
class MMG_Subscription_Manager {

	/**
	 * Subscription data access layer.
	 *
	 * @var MMG_Subscription_Model
	 */
	private $model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->model = new MMG_Subscription_Model();
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_subscriptions' ), 10, 3 );
	}

	/**
	 * Process subscriptions in an order.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order       Order object.
	 */
	public function process_subscriptions( $order_id, $posted_data, $order ) {
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product && 'mmg_subscription' === $product->get_type() ) {
				$this->create_subscription( $order, $product );
			}
		}
	}

	/**
	 * Create a subscription record.
	 *
	 * @param WC_Order   $order   Order.
	 * @param WC_Product $product Product.
	 */
	public function create_subscription( $order, $product ) {
		$period   = $product->get_meta( '_mmg_sub_period' );
		$interval = $product->get_meta( '_mmg_sub_interval' );

		// Set initial next payment date (updated when payment is confirmed).
		$next_payment = $this->calculate_next_date( current_time( 'mysql' ), $period, $interval );

		$sub_id = $this->model->insert(
			array(
				'customer_id'       => $order->get_customer_id(),
				'order_id'          => $order->get_id(),
				'product_id'        => $product->get_id(),
				'status'            => 'pending-payment',
				'billing_interval'  => $interval,
				'billing_period'    => $period,
				'next_payment_date' => $next_payment,
				'payment_token'     => '',
				'created_at'        => current_time( 'mysql' ),
			)
		);

		$order->add_order_note( sprintf( 'MMG Subscription #%d created for product %s.', $sub_id, $product->get_name() ) );
		$order->update_meta_data( '_mmg_subscription_id', $sub_id );
		$order->save();
	}

	/**
	 * Calculate next payment date.
	 *
	 * @param string $from_date Starting date.
	 * @param string $period    Period (day, week, month, year).
	 * @param int    $interval  Interval.
	 * @return string
	 */
	public function calculate_next_date( $from_date, $period, $interval ) {
		$date   = new DateTime( $from_date );
		$modify = sprintf( '+%d %s', $interval, $period );
		$date->modify( $modify );
		return $date->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Activate subscription and schedule renewal.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $token    Payment token.
	 */
	public static function activate_subscription( $order_id, $token ) {
		$model = new MMG_Subscription_Model();
		$sub   = $model->get_by_order_id( (int) $order_id );
		if ( ! $sub ) {
			return;
		}

		$model->update(
			array(
				'status'        => 'active',
				'payment_token' => $token,
			),
			array( 'id' => $sub->id )
		);

		// Schedule renewal.
		if ( function_exists( 'as_enqueue_scheduled_action' ) ) {
			as_enqueue_scheduled_action(
				strtotime( $sub->next_payment_date ),
				'mmg_subscription_renewal',
				array( 'subscription_id' => $sub->id )
			);
			MMG_Logger::info( "Renewal scheduled for Subscription #{$sub->id} on {$sub->next_payment_date}", 'api-requests' );
		}
	}
}
