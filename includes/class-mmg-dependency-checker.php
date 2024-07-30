<?php
/**
 * MMG Dependency Checker
 *
 * This file contains the MMG_Dependency_Checker class which checks for required dependencies.
 *
 * @package MMG_Checkout
 */
namespace MMG_Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class MMG_Dependency_Checker
 *
 * Checks for required dependencies and displays notices if they are missing.
 */
class MMG_Dependency_Checker {
	/**
	 * Check if all dependencies are met.
	 *
	 * @return bool True if all dependencies are met, false otherwise.
	 */
	public static function check_dependencies() {
		if ( ! self::is_woocommerce_active() ) {
			add_action( 'admin_notices', array( __CLASS__, 'woocommerce_missing_notice' ) );
			return false;
		}
		if ( ! self::is_mbstring_installed() ) {
			add_action( 'admin_notices', array( __CLASS__, 'mbstring_missing_notice' ) );
			return false;
		}
		return true;
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is active, false otherwise.
	 */
	private static function is_woocommerce_active() {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}

	/**
	 * Check if mbstring extension is installed.
	 *
	 * @return bool True if mbstring is installed, false otherwise.
	 */
	private static function is_mbstring_installed() {
		return extension_loaded( 'mbstring' );
	}

	/**
	 * Display admin notice for missing WooCommerce.
	 */
	public static function woocommerce_missing_notice() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'MMG Checkout requires WooCommerce to be installed and active. Please install and activate WooCommerce to use this plugin.', 'mmg-checkout' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display admin notice for missing mbstring extension.
	 */
	public static function mbstring_missing_notice() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'MMG Checkout requires the mbstring PHP extension to be installed and active on the server. Please install and activate mbstring to use this plugin.', 'mmg-checkout' ); ?></p>
		</div>
		<?php
	}
}
