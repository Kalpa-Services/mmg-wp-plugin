<?php
if ( ! defined( 'ABSPATH')){
	exit;
}

/**
 * Fired during plugin deactivation
 *
 * @since      1.0.0
 * @package    MMG Checkout Payment
 * @author     Kalpa Services Inc. <info@kalpa.dev>
 */

class MMG_Checkout_Payment_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
        flush_rewrite_rules();
	}

}
