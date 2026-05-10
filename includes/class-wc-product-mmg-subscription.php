<?php
/**
 * MMG Subscription Product Class
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Product_MMG_Subscription class.
 */
class WC_Product_MMG_Subscription extends WC_Product_Simple {

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'mmg_subscription';
	}

	/**
	 * Get product name.
	 *
	 * @param string $context Context.
	 * @return string
	 */
	public function get_name( $context = 'view' ) {
		$name = parent::get_name( $context );
		if ( 'view' === $context ) {
			$name .= ' (' . $this->get_subscription_string() . ')';
		}
		return $name;
	}

	/**
	 * Get subscription string.
	 *
	 * @return string
	 */
	public function get_subscription_string() {
		$period   = $this->get_meta( '_mmg_sub_period' );
		$interval = $this->get_meta( '_mmg_sub_interval' );

		if ( ! $period ) {
			return '';
		}

		if ( 1 === intval( $interval ) ) {
			return sprintf( 'per %s', $period );
		}

		return sprintf( 'every %d %ss', $interval, $period );
	}
}
