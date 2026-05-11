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
		parent::__construct(
			array(
				'singular' => 'subscription',
				'plural'   => 'subscriptions',
				'ajax'     => false,
			)
		);
		$this->model = new MMG_Subscription_Model();
		add_action( 'admin_init', array( $this, 'handle_row_actions' ) );
	}

	/**
	 * Register the admin submenu page.
	 */
	public static function register_menu(): void {
		add_submenu_page(
			'mmg-checkout-settings',
			'Subscriptions',
			'Subscriptions',
			'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown
			'mmg-subscriptions-admin',
			array( new self(), 'render_page' )
		);
	}

	/**
	 * Render the subscriptions admin page.
	 */
	public function render_page(): void {
		echo '<div class="wrap"><h1>MMG Subscriptions</h1>';
		$this->prepare_items();
		echo '<form method="get"><input type="hidden" name="page" value="mmg-subscriptions-admin">';
		$this->display();
		echo '</form></div>';
	}

	/**
	 * Define the table columns.
	 *
	 * @return array Column definitions.
	 */
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

	/**
	 * Define the sortable columns.
	 *
	 * @return array Sortable column definitions.
	 */
	protected function get_sortable_columns(): array {
		return array(
			'next_payment_date' => array( 'next_payment_date', false ),
			'status'            => array( 'status', false ),
		);
	}

	/**
	 * Fetch subscription rows and configure pagination.
	 */
	public function prepare_items(): void {
		$per_page      = 20;
		$current_page  = $this->get_pagenum();
		$offset        = ( $current_page - 1 ) * $per_page;
		$status_filter = isset( $_GET['sub_status'] ) ? sanitize_text_field( $_GET['sub_status'] ) : ''; // phpcs:ignore

		$this->items = $this->model->get_paginated( $per_page, $offset, $status_filter );
		$total       = $this->model->count( $status_filter );

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

	/**
	 * Render a generic column cell.
	 *
	 * @param object $item        Current row object.
	 * @param string $column_name Column identifier.
	 * @return string Escaped cell content.
	 */
	protected function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '' );
	}

	/**
	 * Render the ID column with row actions.
	 *
	 * @param object $item Current row object.
	 * @return string HTML for the ID cell.
	 */
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
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

	/**
	 * Admin action: set subscription to on-hold.
	 *
	 * @param int $id Subscription ID.
	 */
	private function admin_halt( int $id ): void {
		$this->model->update_status( $id, 'on-hold' );
		as_unschedule_all_actions( 'mmg_subscription_renewal', array( 'subscription_id' => $id ), 'mmg-subscriptions' );
		( new MMG_Subscription_Reminder_Scheduler() )->cancel_for_subscription( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=mmg-subscriptions-admin&updated=halted' ) );
		exit;
	}

	/**
	 * Admin action: cancel a subscription.
	 *
	 * @param int $id Subscription ID.
	 */
	private function admin_cancel( int $id ): void {
		$this->model->update_status( $id, 'cancelled' );
		as_unschedule_all_actions( 'mmg_subscription_renewal', array( 'subscription_id' => $id ), 'mmg-subscriptions' );
		( new MMG_Subscription_Reminder_Scheduler() )->cancel_for_subscription( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=mmg-subscriptions-admin&updated=cancelled' ) );
		exit;
	}

	/**
	 * Admin action: resend the payment reminder email.
	 *
	 * @param int $id Subscription ID.
	 */
	private function admin_resend( int $id ): void {
		$sub = $this->model->get_by_id( $id );
		if ( ! $sub ) {
			wp_safe_redirect( admin_url( 'admin.php?page=mmg-subscriptions-admin' ) );
			exit;
		}
		$url = MMG_Subscription_Account::generate_pay_token_url( $id, (int) $sub->customer_id );
		( new MMG_Subscription_Email() )->send_reminder( $sub, $url );
		$this->model->update_last_reminder_sent( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=mmg-subscriptions-admin&updated=resent' ) );
		exit;
	}
}
