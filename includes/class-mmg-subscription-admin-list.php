<?php
/**
 * MMG Subscription Admin List
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Admin subscription management list table.
 */
class MMG_Subscription_Admin_List extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'subscription',
				'plural'   => 'subscriptions',
				'ajax'     => false,
			)
		);
		add_action( 'admin_init', array( $this, 'handle_row_actions' ) );
	}

	/**
	 * Register the admin submenu page.
	 */
	public static function register_menu(): void {
		add_submenu_page(
			'mmg-checkout',
			'Subscriptions',
			'Subscriptions',
			'manage_woocommerce',
			'mmg-subscriptions-admin',
			array( new self(), 'render_page' )
		);
	}

	public function render_page(): void {
		echo '<div class="wrap"><h1>MMG Subscriptions</h1>';
		$this->prepare_items();
		echo '<form method="get"><input type="hidden" name="page" value="mmg-subscriptions-admin">';
		$this->display();
		echo '</form></div>';
	}

	public function get_columns(): array {
		return array(
			'id'                 => 'ID',
			'customer_id'        => 'Customer',
			'product_id'         => 'Product',
			'status'             => 'Status',
			'next_payment_date'  => 'Next Payment',
			'last_reminder_sent' => 'Last Reminder',
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'next_payment_date' => array( 'next_payment_date', false ),
			'status'            => array( 'status', false ),
		);
	}

	public function prepare_items(): void {
		global $wpdb;
		$per_page      = 20;
		$current_page  = $this->get_pagenum();
		$offset        = ( $current_page - 1 ) * $per_page;
        $status_filter = isset( $_GET['sub_status'] ) ? sanitize_text_field( $_GET['sub_status'] ) : ''; // phpcs:ignore

		$where = $status_filter ? $wpdb->prepare( 'WHERE status = %s', $status_filter ) : '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->items = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}mmg_subscriptions {$where} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mmg_subscriptions {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
		$columns               = $this->get_columns();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, array(), $sortable );
	}

	protected function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '' );
	}

	protected function column_id( $item ): string {
		$halt_url   = wp_nonce_url(
			add_query_arg(
				array(
					'page'           => 'mmg-subscriptions-admin',
					'admin_halt_sub' => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'admin_halt_' . $item->id
		);
		$cancel_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'             => 'mmg-subscriptions-admin',
					'admin_cancel_sub' => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'admin_cancel_' . $item->id
		);
		$resend_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'             => 'mmg-subscriptions-admin',
					'admin_resend_sub' => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'admin_resend_' . $item->id
		);

		$actions = array(
			'halt'   => '<a href="' . esc_url( $halt_url ) . '">Halt</a>',
			'cancel' => '<a href="' . esc_url( $cancel_url ) . '">Cancel</a>',
			'resend' => '<a href="' . esc_url( $resend_url ) . '">Resend Reminder</a>',
		);
		if ( 'cancelled' === $item->status ) {
			unset( $actions['halt'], $actions['cancel'], $actions['resend'] );
		}
		return '<strong>' . esc_html( $item->id ) . '</strong>' . $this->row_actions( $actions );
	}

	/**
	 * Handle admin row actions (halt, cancel, resend).
	 *
	 * Hooked on admin_init so it runs before headers are sent.
	 */
	public function handle_row_actions(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( $_GET['_wpnonce'] ) : '';

		if ( isset( $_GET['admin_halt_sub'] ) ) {
			$id = (int) $_GET['admin_halt_sub'];
			if ( wp_verify_nonce( $nonce, 'admin_halt_' . $id ) ) {
				$this->admin_halt( $id );
			}
		} elseif ( isset( $_GET['admin_cancel_sub'] ) ) {
			$id = (int) $_GET['admin_cancel_sub'];
			if ( wp_verify_nonce( $nonce, 'admin_cancel_' . $id ) ) {
				$this->admin_cancel( $id );
			}
		} elseif ( isset( $_GET['admin_resend_sub'] ) ) {
			$id = (int) $_GET['admin_resend_sub'];
			if ( wp_verify_nonce( $nonce, 'admin_resend_' . $id ) ) {
				$this->admin_resend( $id );
			}
		}
	}

	private function admin_halt( int $id ): void {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( $wpdb->prefix . 'mmg_subscriptions', array( 'status' => 'on-hold' ), array( 'id' => $id ) );
		as_unschedule_all_actions( 'mmg_subscription_renewal', array( 'subscription_id' => $id ), 'mmg-subscriptions' );
		( new MMG_Subscription_Reminder_Scheduler() )->cancel_for_subscription( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=mmg-subscriptions-admin&updated=halted' ) );
		exit;
	}

	private function admin_cancel( int $id ): void {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( $wpdb->prefix . 'mmg_subscriptions', array( 'status' => 'cancelled' ), array( 'id' => $id ) );
		as_unschedule_all_actions( 'mmg_subscription_renewal', array( 'subscription_id' => $id ), 'mmg-subscriptions' );
		( new MMG_Subscription_Reminder_Scheduler() )->cancel_for_subscription( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=mmg-subscriptions-admin&updated=cancelled' ) );
		exit;
	}

	private function admin_resend( int $id ): void {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sub = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mmg_subscriptions WHERE id = %d",
				$id
			)
		);
		if ( ! $sub ) {
			wp_safe_redirect( admin_url( 'admin.php?page=mmg-subscriptions-admin' ) );
			exit;
		}
		$url = MMG_Subscription_Account::generate_pay_token_url( $id );
		( new MMG_Subscription_Email() )->send_reminder( $sub, $url );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'mmg_subscriptions',
			array( 'last_reminder_sent' => current_time( 'mysql' ) ),
			array( 'id' => $id )
		);
		wp_safe_redirect( admin_url( 'admin.php?page=mmg-subscriptions-admin&updated=resent' ) );
		exit;
	}
}
