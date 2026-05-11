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
 * Subscription email template settings rendered as a tab inside the main settings page.
 */
class MMG_Subscription_Email_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_post_mmg_save_email_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Render the email templates form content (called from the settings page tab panel).
	 */
	public static function render_tab_content(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return;
		}

		$defaults         = MMG_Subscription_Email::get_default_templates();
		$schedule_raw     = get_option( 'mmg_reminder_schedule', wp_json_encode( array( 3 ) ) );
		$schedule_decoded = json_decode( $schedule_raw, true );
		$schedule_arr     = is_array( $schedule_decoded ) ? $schedule_decoded : array( 3 );
		$schedule_str     = implode( ',', $schedule_arr );

		$tpl_reminder  = get_option( 'mmg_email_tpl_reminder', $defaults['mmg_email_tpl_reminder'] );
		$tpl_confirmed = get_option( 'mmg_email_tpl_confirmed', $defaults['mmg_email_tpl_confirmed'] );
		$tpl_failed    = get_option( 'mmg_email_tpl_failed', $defaults['mmg_email_tpl_failed'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$saved     = isset( $_GET['saved'] ) && '1' === $_GET['saved'] && isset( $_GET['tab'] ) && 'email-templates' === $_GET['tab'];
		$from_name = esc_html( get_bloginfo( 'name' ) );

		$editor_settings = array(
			'media_buttons' => false,
			'tinymce'       => array(
				'toolbar1'       => 'bold,italic,underline,forecolor,separator,link,unlink,separator,bullist,numlist,separator,code',
				'toolbar2'       => '',
				'statusbar'      => false,
				'valid_elements' => '*[*]',
			),
			'quicktags'     => true,
			'textarea_rows' => 14,
		);

		$preview_css = 'body{overflow-x:hidden;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;font-size:14px;line-height:1.7;color:#333;padding:20px 24px;margin:0}*{box-sizing:border-box;max-width:100%}p{margin:0 0 12px}p:last-child{margin-bottom:0}a{color:#0f9b8e}';
		?>

		<?php if ( $saved ) : ?>
			<div class="mmg-alert mmg-alert-info">
				<span class="mmg-alert-icon dashicons dashicons-yes-alt"></span>
				<span>Email templates saved successfully.</span>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mmg_save_email_settings">
			<?php wp_nonce_field( 'mmg_email_settings' ); ?>

			<!-- Reminder Schedule card -->
			<div class="mmg-card">
				<div class="mmg-card-header" data-collapse="mmg-email-schedule">
					<h3 class="mmg-card-header-title">
						<span class="dashicons dashicons-clock"></span>
						Reminder Schedule
					</h3>
					<span class="mmg-card-chevron dashicons dashicons-arrow-down-alt2"></span>
				</div>
				<div class="mmg-card-body" id="mmg-email-schedule">
					<div class="mmg-form-row">
						<label class="mmg-form-label" for="mmg_reminder_schedule">
							Days before renewal
							<span class="mmg-label-hint">Comma-separated integers</span>
						</label>
						<div class="mmg-form-control">
							<input type="text" id="mmg_reminder_schedule" name="mmg_reminder_schedule" value="<?php echo esc_attr( $schedule_str ); ?>">
							<p class="mmg-form-hint">Days before renewal to send reminders. Default: <code>3</code></p>
						</div>
					</div>
				</div>
			</div><!-- /.mmg-card (schedule) -->

			<!-- Reminder Email card -->
			<div class="mmg-card">
				<div class="mmg-card-header" data-collapse="mmg-email-reminder">
					<h3 class="mmg-card-header-title">
						<span class="dashicons dashicons-email-alt"></span>
						Reminder Email
					</h3>
					<span class="mmg-card-chevron dashicons dashicons-arrow-down-alt2"></span>
				</div>
				<div class="mmg-card-body" id="mmg-email-reminder">
					<div class="mmg-tpl-vars">
						<code>{customer_name}</code> <code>{subscription_name}</code> <code>{amount}</code>
						<code>{next_payment_date}</code> <code>{payment_url}</code> <code>{account_url}</code> <code>{site_name}</code>
					</div>
					<div class="mmg-tpl-split">
						<div class="mmg-tpl-editor">
							<div class="mmg-form-row">
								<label class="mmg-form-label" for="mmg_tpl_reminder_subject">Subject</label>
								<div class="mmg-form-control">
									<input type="text" id="mmg_tpl_reminder_subject" name="mmg_tpl_reminder_subject" value="<?php echo esc_attr( $tpl_reminder['subject'] ); ?>">
								</div>
							</div>
							<div class="mmg-editor-block">
								<label class="mmg-form-label" for="mmg_tpl_reminder_body">Email Body</label>
								<?php wp_editor( $tpl_reminder['body'], 'mmg_tpl_reminder_body', $editor_settings ); ?>
							</div>
						</div>
						<div class="mmg-tpl-preview-pane">
							<span class="mmg-tpl-preview-label">Live Preview</span>
							<div class="mmg-email-chrome">
								<div class="mmg-email-chrome-bar">
									<span class="mmg-email-chrome-dot"></span><span class="mmg-email-chrome-dot"></span><span class="mmg-email-chrome-dot"></span>
								</div>
								<div class="mmg-email-meta">
									<strong>From:</strong> <span><?php echo $from_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html applied above. ?></span><br>
									<strong>To:</strong> <span>jane.smith@example.com</span>
								</div>
								<div class="mmg-email-subject-preview" id="mmg-preview-subj-reminder"></div>
								<iframe class="mmg-email-body-preview" id="mmg-preview-body-reminder" sandbox="allow-same-origin" title="Reminder email body preview"></iframe>
							</div>
						</div>
					</div>
				</div>
			</div><!-- /.mmg-card (reminder) -->

			<!-- Payment Confirmed Email card -->
			<div class="mmg-card">
				<div class="mmg-card-header" data-collapse="mmg-email-confirmed">
					<h3 class="mmg-card-header-title">
						<span class="dashicons dashicons-yes-alt"></span>
						Payment Confirmed Email
					</h3>
					<span class="mmg-card-chevron dashicons dashicons-arrow-down-alt2"></span>
				</div>
				<div class="mmg-card-body" id="mmg-email-confirmed">
					<div class="mmg-tpl-vars">
						<code>{customer_name}</code> <code>{subscription_name}</code> <code>{amount}</code>
						<code>{next_payment_date}</code> <code>{account_url}</code> <code>{site_name}</code>
					</div>
					<div class="mmg-tpl-split">
						<div class="mmg-tpl-editor">
							<div class="mmg-form-row">
								<label class="mmg-form-label" for="mmg_tpl_confirmed_subject">Subject</label>
								<div class="mmg-form-control">
									<input type="text" id="mmg_tpl_confirmed_subject" name="mmg_tpl_confirmed_subject" value="<?php echo esc_attr( $tpl_confirmed['subject'] ); ?>">
								</div>
							</div>
							<div class="mmg-editor-block">
								<label class="mmg-form-label" for="mmg_tpl_confirmed_body">Email Body</label>
								<?php wp_editor( $tpl_confirmed['body'], 'mmg_tpl_confirmed_body', $editor_settings ); ?>
							</div>
						</div>
						<div class="mmg-tpl-preview-pane">
							<span class="mmg-tpl-preview-label">Live Preview</span>
							<div class="mmg-email-chrome">
								<div class="mmg-email-chrome-bar">
									<span class="mmg-email-chrome-dot"></span><span class="mmg-email-chrome-dot"></span><span class="mmg-email-chrome-dot"></span>
								</div>
								<div class="mmg-email-meta">
									<strong>From:</strong> <span><?php echo $from_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html applied above. ?></span><br>
									<strong>To:</strong> <span>jane.smith@example.com</span>
								</div>
								<div class="mmg-email-subject-preview" id="mmg-preview-subj-confirmed"></div>
								<iframe class="mmg-email-body-preview" id="mmg-preview-body-confirmed" sandbox="allow-same-origin" title="Confirmed email body preview"></iframe>
							</div>
						</div>
					</div>
				</div>
			</div><!-- /.mmg-card (confirmed) -->

			<!-- Payment Failed Email card -->
			<div class="mmg-card">
				<div class="mmg-card-header" data-collapse="mmg-email-failed">
					<h3 class="mmg-card-header-title">
						<span class="dashicons dashicons-dismiss"></span>
						Payment Failed Email
					</h3>
					<span class="mmg-card-chevron dashicons dashicons-arrow-down-alt2"></span>
				</div>
				<div class="mmg-card-body" id="mmg-email-failed">
					<div class="mmg-tpl-vars">
						<code>{customer_name}</code> <code>{subscription_name}</code>
						<code>{account_url}</code> <code>{site_name}</code>
					</div>
					<div class="mmg-tpl-split">
						<div class="mmg-tpl-editor">
							<div class="mmg-form-row">
								<label class="mmg-form-label" for="mmg_tpl_failed_subject">Subject</label>
								<div class="mmg-form-control">
									<input type="text" id="mmg_tpl_failed_subject" name="mmg_tpl_failed_subject" value="<?php echo esc_attr( $tpl_failed['subject'] ); ?>">
								</div>
							</div>
							<div class="mmg-editor-block">
								<label class="mmg-form-label" for="mmg_tpl_failed_body">Email Body</label>
								<?php wp_editor( $tpl_failed['body'], 'mmg_tpl_failed_body', $editor_settings ); ?>
							</div>
						</div>
						<div class="mmg-tpl-preview-pane">
							<span class="mmg-tpl-preview-label">Live Preview</span>
							<div class="mmg-email-chrome">
								<div class="mmg-email-chrome-bar">
									<span class="mmg-email-chrome-dot"></span><span class="mmg-email-chrome-dot"></span><span class="mmg-email-chrome-dot"></span>
								</div>
								<div class="mmg-email-meta">
									<strong>From:</strong> <span><?php echo $from_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html applied above. ?></span><br>
									<strong>To:</strong> <span>jane.smith@example.com</span>
								</div>
								<div class="mmg-email-subject-preview" id="mmg-preview-subj-failed"></div>
								<iframe class="mmg-email-body-preview" id="mmg-preview-body-failed" sandbox="allow-same-origin" title="Failed email body preview"></iframe>
							</div>
						</div>
					</div>
				</div>
			</div><!-- /.mmg-card (failed) -->

			<button type="submit" class="mmg-btn mmg-btn-primary mmg-btn-save">
				<span class="dashicons dashicons-saved" style="font-size:16px;width:16px;height:16px;margin-top:1px;"></span>
				Save Templates
			</button>

		</form>

		<script>
		(function () {
			var previewCss = <?php echo wp_json_encode( $preview_css ); ?>;
			var samples    = {
				'{customer_name}':     'Jane Smith',
				'{subscription_name}': 'Premium Monthly',
				'{amount}':            'GYD 5,000',
				'{next_payment_date}': 'May 17, 2026',
				'{payment_url}':       '#',
				'{account_url}':       '#',
				'{site_name}':         <?php echo wp_json_encode( get_bloginfo( 'name' ) ); ?>
			};

			function applyVars( text ) {
				Object.keys( samples ).forEach( function ( key ) {
					text = text.split( key ).join( samples[ key ] );
				} );
				return text;
			}

			function buildSrcdoc( bodyHtml ) {
				return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' + previewCss + '</style></head><body>' + bodyHtml + '</body></html>';
			}

			function getEditorContent( editorId ) {
				var ed = window.tinymce && tinymce.get( editorId );
				if ( ed && ! ed.isHidden() ) {
					return ed.getContent();
				}
				var ta = document.getElementById( editorId );
				return ta ? ta.value : '';
			}

			// Auto-resize iframe to content height so no scrollbar is needed.
			function fitIframe( frame ) {
				try {
					var doc = frame.contentDocument || ( frame.contentWindow && frame.contentWindow.document );
					if ( doc && doc.body ) {
						frame.style.height = doc.body.scrollHeight + 'px';
					}
				} catch ( e ) { /* cross-origin guard */ }
			}

			function wirePreview( subjId, editorId, previewSubjId, frameId ) {
				var subjEl      = document.getElementById( subjId );
				var previewSubj = document.getElementById( previewSubjId );
				var frame       = document.getElementById( frameId );

				if ( frame ) {
					frame.addEventListener( 'load', function () { fitIframe( frame ); } );
				}

				function refresh() {
					if ( previewSubj && subjEl ) {
						previewSubj.textContent = applyVars( subjEl.value );
					}
					if ( frame ) {
						frame.srcdoc = buildSrcdoc( applyVars( getEditorContent( editorId ) ) );
					}
				}

				if ( subjEl ) { subjEl.addEventListener( 'input', refresh ); }

				// Raw textarea events (quicktags / text mode).
				var ta = document.getElementById( editorId );
				if ( ta ) { ta.addEventListener( 'input', refresh ); }

				// TinyMCE visual mode events.
				if ( window.tinymce ) {
					tinymce.on( 'AddEditor', function ( event ) {
						if ( event.editor.id === editorId ) {
							event.editor.on( 'keyup Change NodeChange', refresh );
						}
					} );
				}

				// Delay initial render so TinyMCE can finish initializing.
				setTimeout( refresh, 600 );
			}

			wirePreview(
				'mmg_tpl_reminder_subject',  'mmg_tpl_reminder_body',
				'mmg-preview-subj-reminder', 'mmg-preview-body-reminder'
			);
			wirePreview(
				'mmg_tpl_confirmed_subject',  'mmg_tpl_confirmed_body',
				'mmg-preview-subj-confirmed', 'mmg-preview-body-confirmed'
			);
			wirePreview(
				'mmg_tpl_failed_subject',  'mmg_tpl_failed_body',
				'mmg-preview-subj-failed', 'mmg-preview-body-failed'
			);

			// When the email-templates tab becomes visible, resize TinyMCE editors
			// so they fill the full width (they initialise inside a hidden panel).
			var panel = document.getElementById( 'mmg-panel-email-templates' );
			if ( panel && window.MutationObserver ) {
				new MutationObserver( function ( mutations ) {
					mutations.forEach( function ( m ) {
						if ( 'class' === m.attributeName && panel.classList.contains( 'mmg-tab-active' ) ) {
							window.dispatchEvent( new Event( 'resize' ) );
						}
					} );
				} ).observe( panel, { attributes: true } );
			}
		}());
		</script>
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

		wp_safe_redirect( admin_url( 'admin.php?page=mmg-checkout-settings&tab=email-templates&saved=1' ) );
		exit;
	}
}
