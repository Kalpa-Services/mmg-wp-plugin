<?php
/**
 * MMG Logger
 *
 * @package MMG_Checkout_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight circular-buffer logger for MMG Checkout.
 *
 * Persists up to MAX_ENTRIES log entries in a single site option (autoload=false).
 * Every entry is also forwarded to the PHP error log for server-side visibility.
 */
class MMG_Logger {

	const OPTION_KEY  = 'mmg_plugin_logs';
	const MAX_ENTRIES = 500;

	/**
	 * Log at INFO level.
	 *
	 * @param string $message Message.
	 * @param string $context Context (api-requests, webhooks, errors).
	 */
	public static function info( $message, $context = 'api-requests' ) {
		self::log( $message, 'info', $context );
	}

	/**
	 * Log at WARNING level.
	 *
	 * @param string $message Message.
	 * @param string $context Context (api-requests, webhooks, errors).
	 */
	public static function warning( $message, $context = 'api-requests' ) {
		self::log( $message, 'warning', $context );
	}

	/**
	 * Log at ERROR level.
	 *
	 * @param string $message Message.
	 * @param string $context Context (api-requests, webhooks, errors).
	 */
	public static function error( $message, $context = 'errors' ) {
		self::log( $message, 'error', $context );
	}

	/**
	 * Core log method.
	 *
	 * @param string $message Message.
	 * @param string $level   'info' | 'warning' | 'error'.
	 * @param string $context Context (api-requests, webhooks, errors).
	 */
	public static function log( $message, $level = 'info', $context = 'api-requests' ) {
		// 1. Forward to WooCommerce Logger for file-based persistence.
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->log( $level, $message, array( 'source' => 'mmg-' . $context ) );
		}

		// 2. Forward to PHP error log.
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[MMG][' . strtoupper( $level ) . '][' . strtoupper( $context ) . '] ' . $message );
		// phpcs:enable

		// 3. Persist to internal circular buffer for dashboard display.
		$entry = array(
			'ts'  => time(),
			'lvl' => $level,
			'ctx' => $context,
			'msg' => $message,
		);

		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		array_unshift( $logs, $entry );

		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $logs, false );
	}

	/**
	 * Return all stored log entries, newest first.
	 *
	 * @return array
	 */
	public static function get_logs() {
		$logs = get_option( self::OPTION_KEY, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Count entries by level.
	 *
	 * @return array { error: int, warning: int, info: int }
	 */
	public static function count_by_level() {
		$counts = array(
			'error'   => 0,
			'warning' => 0,
			'info'    => 0,
		);
		foreach ( self::get_logs() as $entry ) {
			$lvl = isset( $entry['lvl'] ) ? $entry['lvl'] : 'info';
			if ( isset( $counts[ $lvl ] ) ) {
				++$counts[ $lvl ];
			}
		}
		return $counts;
	}

	/**
	 * Delete all stored log entries.
	 */
	public static function clear() {
		delete_option( self::OPTION_KEY );
	}
}
