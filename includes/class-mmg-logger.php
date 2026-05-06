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
	 */
	public static function info( $message ) {
		self::log( $message, 'info' );
	}

	/**
	 * Log at WARNING level.
	 *
	 * @param string $message Message.
	 */
	public static function warning( $message ) {
		self::log( $message, 'warning' );
	}

	/**
	 * Log at ERROR level.
	 *
	 * @param string $message Message.
	 */
	public static function error( $message ) {
		self::log( $message, 'error' );
	}

	/**
	 * Core log method.
	 *
	 * @param string $message Message.
	 * @param string $level   'info' | 'warning' | 'error'.
	 */
	public static function log( $message, $level = 'info' ) {
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[MMG][' . strtoupper( $level ) . '] ' . $message );
		// phpcs:enable

		$entry = array(
			'ts'  => time(),
			'lvl' => $level,
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
