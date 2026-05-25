<?php
/**
 * MMG Subscription Model
 *
 * Data access layer for the wp_mmg_subscriptions table.
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MMG_Subscription_Model class.
 */
class MMG_Subscription_Model {

	/**
	 * Cache group used for all subscription row caches.
	 */
	const CACHE_GROUP = 'mmg_subscriptions';

	/**
	 * Cache TTL in seconds.
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Return the full table name for this installation.
	 *
	 * @return string
	 */
	private function get_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mmg_subscriptions';
	}

	/**
	 * Build a cache key for a single row by primary key.
	 *
	 * @param int $id Subscription ID.
	 * @return string
	 */
	private function cache_key( int $id ): string {
		return 'sub_' . $id;
	}

	/**
	 * Invalidate the object cache for a given subscription row.
	 *
	 * @param int $id Subscription ID.
	 */
	private function bust_cache( int $id ): void {
		wp_cache_delete( $this->cache_key( $id ), self::CACHE_GROUP );
	}

	// -------------------------------------------------------------------------
	// Read methods
	// -------------------------------------------------------------------------

	/**
	 * Fetch a single subscription by primary key.
	 *
	 * Uses the object cache; subsequent calls within the same request are free.
	 *
	 * @param int $id Subscription ID.
	 * @return object|null
	 */
	public function get_by_id( int $id ): ?object {
		$cached = wp_cache_get( $this->cache_key( $id ), self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached ? $cached : null;
		}

		global $wpdb;
		$table_name = $this->get_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		// Store 0 for a miss so we don't re-query within the same request.
		wp_cache_set( $this->cache_key( $id ), $row ?? 0, self::CACHE_GROUP, self::CACHE_TTL );

		return $row ? $row : null;
	}

	/**
	 * Fetch a single active subscription by ID.
	 *
	 * Piggybacks on get_by_id() so the result is cached.
	 *
	 * @param int $id Subscription ID.
	 * @return object|null  Null when the row does not exist or is not active.
	 */
	public function get_active_by_id( int $id ): ?object {
		$sub = $this->get_by_id( $id );
		return ( $sub && 'active' === $sub->status ) ? $sub : null;
	}

	/**
	 * Fetch a subscription by its parent WooCommerce order ID.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return object|null
	 */
	public function get_by_order_id( int $order_id ): ?object {
		global $wpdb;
		$table_name = $this->get_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);
		return $row ? $row : null;
	}

	/**
	 * Fetch all subscriptions for a customer, newest first.
	 *
	 * @param int $customer_id WordPress user ID.
	 * @return object[]
	 */
	public function get_by_customer_id( int $customer_id ): array {
		global $wpdb;
		$table_name = $this->get_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE customer_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id
			)
		);
	}

	/**
	 * Fetch a subscription that belongs to a specific customer (ownership check).
	 *
	 * @param int $id          Subscription ID.
	 * @param int $customer_id WordPress user ID.
	 * @return object|null
	 */
	public function get_owned_by_customer( int $id, int $customer_id ): ?object {
		global $wpdb;
		$table_name = $this->get_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d AND customer_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id,
				$customer_id
			)
		);
		return $row ? $row : null;
	}

	/**
	 * Fetch a paginated slice of subscriptions for the admin list.
	 *
	 * @param int    $per_page      Rows per page.
	 * @param int    $offset        Row offset.
	 * @param string $status_filter Optional status to filter by.
	 * @return object[]
	 */
	public function get_paginated( int $per_page, int $offset, string $status_filter = '' ): array {
		global $wpdb;
		$table_name = $this->get_table();
		$where      = $status_filter ? $wpdb->prepare( 'WHERE status = %s', $status_filter ) : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count total subscriptions, optionally filtered by status.
	 *
	 * @param string $status_filter Optional status to filter by.
	 * @return int
	 */
	public function count( string $status_filter = '' ): int {
		global $wpdb;
		$table_name = $this->get_table();
		$where      = $status_filter ? $wpdb->prepare( 'WHERE status = %s', $status_filter ) : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Fetch the most recent subscriptions (used for the admin settings preview).
	 *
	 * @param int $limit Maximum rows to return.
	 * @return object[]
	 */
	public function get_recent( int $limit = 100 ): array {
		global $wpdb;
		$table_name = $this->get_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			)
		);
	}

	/**
	 * Check whether the subscriptions table exists in this database.
	 *
	 * @return bool
	 */
	public function table_exists(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $this->get_table() )
			)
		);
	}

	// -------------------------------------------------------------------------
	// Write methods
	// -------------------------------------------------------------------------

	/**
	 * Insert a new subscription row and return its new ID.
	 *
	 * @param array $data Column => value pairs to insert.
	 * @return int|false  New row ID on success, false on failure.
	 */
	public function insert( array $data ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $this->get_table(), $data );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update rows matching $where with new $data.
	 *
	 * Automatically invalidates the object cache when $where contains an 'id'.
	 *
	 * @param array $data  Column => value pairs to set.
	 * @param array $where Column => value pairs for the WHERE clause.
	 * @return int|false   Rows affected, or false on DB error.
	 */
	public function update( array $data, array $where ) {
		global $wpdb;
		if ( isset( $where['id'] ) ) {
			$this->bust_cache( (int) $where['id'] );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->update( $this->get_table(), $data, $where );
	}

	/**
	 * Convenience wrapper — update only the status column for a single row.
	 *
	 * @param int    $id     Subscription ID.
	 * @param string $status New status value.
	 * @return bool
	 */
	public function update_status( int $id, string $status ): bool {
		return false !== $this->update( array( 'status' => $status ), array( 'id' => $id ) );
	}

	/**
	 * Record that a reminder email was just sent for a subscription.
	 *
	 * @param int $id Subscription ID.
	 * @return bool
	 */
	public function update_last_reminder_sent( int $id ): bool {
		return false !== $this->update(
			array( 'last_reminder_sent' => current_time( 'mysql' ) ),
			array( 'id' => $id )
		);
	}

	/**
	 * Advance a subscription's billing cycle using an optimistic lock.
	 *
	 * Updates the row only when the current payment_cycle_id still matches
	 * $expected_cycle_id, preventing double-advances from duplicate AS jobs.
	 *
	 * @param int    $id               Subscription ID.
	 * @param string $expected_cycle_id The cycle ID the caller read; update is skipped if it has changed.
	 * @param array  $data             Fields to set on a successful advance.
	 * @return bool  True when exactly one row was updated; false on error or stale cycle ID.
	 */
	public function advance_cycle_if_current( int $id, string $expected_cycle_id, array $data ): bool {
		global $wpdb;
		$this->bust_cache( $id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$this->get_table(),
			$data,
			array(
				'id'               => $id,
				'payment_cycle_id' => $expected_cycle_id,
			)
		);
		return (bool) $updated;
	}
}
