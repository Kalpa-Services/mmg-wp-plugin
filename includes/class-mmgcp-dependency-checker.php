<?php
/**
 * MMG Dependency Checker
 *
 * This file contains the MMGCP_Dependency_Checker class which checks for required dependencies.
 *
 * @package MMG_Checkout_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class MMGCP_Dependency_Checker
 *
 * Checks for required dependencies and displays notices if they are missing.
 */
class MMGCP_Dependency_Checker {
	/**
	 * Check if all dependencies are met.
	 *
	 * @return bool True if all dependencies are met, false otherwise.
	 */
	public static function mmgcp_check_dependencies() {
		if ( ! self::mmgcp_is_woocommerce_active() ) {
			add_action( 'admin_notices', array( __CLASS__, 'mmgcp_woocommerce_missing_notice' ) );
			return false;
		}
		if ( ! self::mmgcp_is_mbstring_installed() ) {
			add_action( 'admin_notices', array( __CLASS__, 'mmgcp_mbstring_missing_notice' ) );
			return false;
		}
		if ( ! self::mmgcp_is_ssl_valid() ) {
			add_action( 'admin_notices', array( __CLASS__, 'mmgcp_ssl_invalid_notice' ) );
			return false;
		}
		return true;
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is active, false otherwise.
	 */
	private static function mmgcp_is_woocommerce_active() {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}

	/**
	 * Check if mbstring extension is installed.
	 *
	 * @return bool True if mbstring is installed, false otherwise.
	 */
	private static function mmgcp_is_mbstring_installed() {
		return extension_loaded( 'mbstring' );
	}

	/**
	 * Check if SSL is valid.
	 *
	 * @return bool True if SSL is valid or if on localhost, false otherwise.
	 */
	private static function mmgcp_is_ssl_valid() {
		if ( self::mmgcp_is_localhost() ) {
			return true;
		}
		return is_ssl();
	}

	/**
	 * Check if the site is running on localhost.
	 *
	 * @return bool True if on localhost, false otherwise.
	 */
	private static function mmgcp_is_localhost() {
		if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
			return false;
		}
		$server_name = strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) );
		return in_array( $server_name, array( 'localhost', '127.0.0.1', '::1' ), true );
	}

	/**
	 * Display admin notice for missing WooCommerce.
	 */
	public static function mmgcp_woocommerce_missing_notice() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'MMG Checkout requires WooCommerce to be installed and active. Please install and activate WooCommerce to use this plugin.', 'mmg-checkout' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display admin notice for missing mbstring extension.
	 */
	public static function mmgcp_mbstring_missing_notice() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'MMG Checkout requires the mbstring PHP extension to be installed and active on the server. Please install and activate mbstring to use this plugin.', 'mmg-checkout' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display admin notice for invalid SSL.
	 */
	public static function mmgcp_ssl_invalid_notice() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'MMG Checkout requires a valid SSL certificate to be installed on your website. Please install and configure an SSL certificate to use this plugin.', 'mmg-checkout' ); ?></p>
		</div>
		<?php
	}
}