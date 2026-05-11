<?php
/**
 * MMG Subscription Email Settings
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin submenu page for subscription email templates and reminder schedule.
 */
class MMG_Subscription_Email_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ) );
		add_action( 'admin_post_mmg_save_email_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Register the email templates submenu page.
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'mmg-checkout',
			'Email Templates',
			'Email Templates',
			'manage_woocommerce',
			'mmg-email-templates',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the email templates admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}
		$defaults         = MMG_Subscription_Email::get_default_templates();
		$schedule_raw     = get_option( 'mmg_reminder_schedule', wp_json_encode( array( 3 ) ) );
		$schedule_decoded = json_decode( $schedule_raw, true );
		$schedule_arr     = is_array( $schedule_decoded ) ? $schedule_decoded : array( 3 );
		$schedule_str = implode( ',', $schedule_arr );

		$tpl_reminder  = get_option( 'mmg_email_tpl_reminder', $defaults['mmg_email_tpl_reminder'] );
		$tpl_confirmed = get_option( 'mmg_email_tpl_confirmed', $defaults['mmg_email_tpl_confirmed'] );
		$tpl_failed    = get_option( 'mmg_email_tpl_failed', $defaults['mmg_email_tpl_failed'] );
		?>
		<div class="wrap">
			<h1>MMG Subscription Email Templates</h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mmg_save_email_settings">
				<?php wp_nonce_field( 'mmg_email_settings' ); ?>

				<h2>Reminder Schedule</h2>
				<p>Comma-separated days before renewal to send reminders. Default: <code>3</code></p>
				<input type="text" name="mmg_reminder_schedule" value="<?php echo esc_attr( $schedule_str ); ?>" style="width:200px;">

				<h2>Reminder Email</h2>
				<p><strong>Available variables:</strong> {customer_name}, {subscription_name}, {amount}, {next_payment_date}, {payment_url}, {account_url}, {site_name}</p>
				<p><label>Subject</label><br><input type="text" name="mmg_tpl_reminder_subject" value="<?php echo esc_attr( $tpl_reminder['subject'] ); ?>" style="width:100%;"></p>
				<p><label>Body (HTML)</label><br><textarea name="mmg_tpl_reminder_body" rows="8" style="width:100%;"><?php echo esc_textarea( $tpl_reminder['body'] ); ?></textarea></p>

				<h2>Payment Confirmed Email</h2>
				<p><strong>Available variables:</strong> {customer_name}, {subscription_name}, {amount}, {next_payment_date}, {account_url}, {site_name}</p>
				<p><label>Subject</label><br><input type="text" name="mmg_tpl_confirmed_subject" value="<?php echo esc_attr( $tpl_confirmed['subject'] ); ?>" style="width:100%;"></p>
				<p><label>Body (HTML)</label><br><textarea name="mmg_tpl_confirmed_body" rows="8" style="width:100%;"><?php echo esc_textarea( $tpl_confirmed['body'] ); ?></textarea></p>

				<h2>Payment Failed Email</h2>
				<p><strong>Available variables:</strong> {customer_name}, {subscription_name}, {account_url}, {site_name}</p>
				<p><label>Subject</label><br><input type="text" name="mmg_tpl_failed_subject" value="<?php echo esc_attr( $tpl_failed['subject'] ); ?>" style="width:100%;"></p>
				<p><label>Body (HTML)</label><br><textarea name="mmg_tpl_failed_body" rows="8" style="width:100%;"><?php echo esc_textarea( $tpl_failed['body'] ); ?></textarea></p>

				<?php submit_button( 'Save Templates' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle form submission — save all template options.
	 */
	public function save_settings(): void {
		check_admin_referer( 'mmg_email_settings' );

		// Reminder schedule — comma-separated integers only.
		$raw_schedule = isset( $_POST['mmg_reminder_schedule'] )
			? sanitize_text_field( wp_unslash( $_POST['mmg_reminder_schedule'] ) )
			: '3';
		$parts        = array_map( 'trim', explode( ',', $raw_schedule ) );
		$offsets      = array_values( array_filter( array_map( 'intval', $parts ), fn( $v ) => $v > 0 ) );
		if ( empty( $offsets ) ) {
			$offsets = array( 3 );
		}
		update_option( 'mmg_reminder_schedule', wp_json_encode( $offsets ) );

		// Templates.
		$keys = array( 'reminder', 'confirmed', 'failed' );
		foreach ( $keys as $key ) {
			$subject = isset( $_POST[ "mmg_tpl_{$key}_subject" ] )
				? sanitize_text_field( wp_unslash( $_POST[ "mmg_tpl_{$key}_subject" ] ) )
				: '';
			// Admin-only field: wp_unslash only — wp_kses_post strips email-specific HTML (style, bgcolor, etc.).
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$body = isset( $_POST[ "mmg_tpl_{$key}_body" ] )
				? wp_unslash( $_POST[ "mmg_tpl_{$key}_body" ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				: '';
			update_option(
				"mmg_email_tpl_{$key}",
				array(
					'subject' => $subject,
					'body'    => $body,
				)
			);
		}

		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=mmg-email-templates&saved=1' ) );
			exit;
		}
	}
}
