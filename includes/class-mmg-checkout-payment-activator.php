<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    MMG Checkout Payment
 * @author     Kalpa Services Inc. <info@kalpa.dev>
 */
class MMG_Checkout_Payment_Activator {

    /**
     * @since    1.0.0
     */
    public static function activate() {
        mmg_activate();
    }
}

function mmg_activate() {
    mmg_add_rewrite_rules();
    flush_rewrite_rules();
}