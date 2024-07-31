<?php
/**
 * MMG Checkout Settings
 *
 * This class handles the settings page for the MMG Checkout plugin.
 *
 * @package MMG_Checkout_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class MMG_Checkout_Settings
 */
class MMG_Checkout_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_options_page( 'MMG Checkout Settings', 'MMG Checkout', 'manage_options', 'mmg-checkout-settings', array( $this, 'settings_page' ) );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'mmg_checkout_settings', 'mmg_mode', array( 'sanitize_callback' => array( $this, 'sanitize_mode' ) ) );
		register_setting( 'mmg_checkout_settings', 'mmg_client_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_merchant_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_secret_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_rsa_public_key', array( 'sanitize_callback' => array( $this, 'sanitize_multiline_field' ) ) );
		register_setting( 'mmg_checkout_settings', 'mmg_rsa_private_key', array( 'sanitize_callback' => array( $this, 'sanitize_multiline_field' ) ) );
		register_setting( 'mmg_checkout_settings', 'mmg_merchant_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_live_checkout_url', array( 'sanitize_callback' => 'esc_url' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_demo_checkout_url', array( 'sanitize_callback' => 'esc_url' ) );
	}

	/**
	 * Render settings page.
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1>MMG Checkout Settings</h1>
			<p style="font-size: 14px;">
				This plugin requires a merchant account with MMG. If you don't have one, please contact MMG to get started.
			</p>
			<div class="notice notice-warning">
				<p><strong>Warning:</strong> Never share your private key with anyone. MMG will never ask for your private key. Keep it secure and confidential at all times.</p>
			</div>
			<form method="post" action="options.php" id="mmg-checkout-settings-form">
				<?php
				settings_fields( 'mmg_checkout_settings' );
				do_settings_sections( 'mmg_checkout_settings' );
				?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Mode</th>
						<td>
							<select name="mmg_mode" id="mmg_mode">
								<option value="live" <?php selected( get_option( 'mmg_mode' ), 'live' ); ?>>Live</option>
								<option value="demo" <?php selected( get_option( 'mmg_mode' ), 'demo' ); ?>>Sandbox</option>
							</select>
							<span id="live-mode-indicator" style="display: none; margin-left: 10px;">
								<span class="blinking-dot"></span> Live Mode
							</span>
						</td>
					</tr>
					<style>
						.blinking-dot {
							display: inline-block;
							width: 10px;
							height: 10px;
							background-color: #00ff00;
							border-radius: 50%;
							animation: blink 1s infinite;
						}
						@keyframes blink {
							0% { opacity: 0; }
							50% { opacity: 1; }
							100% { opacity: 0; }
						}
					</style>
					<tr valign="top">
						<th scope="row">Callback URL</th>
						<td>
							<?php $callback_url = esc_url( $this->get_callback_url() ); ?>
							<?php echo esc_html( $callback_url ); ?>
							<button type="button" class="button" onclick="copyToClipboard('<?php echo esc_js( $callback_url ); ?>')">Copy</button>
							<span id="copy-success" style="color: green; display: none; margin-left: 10px;">Copied!</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Client ID</th>
						<td><input type="text" name="mmg_client_id" id="mmg_client_id" value="<?php echo esc_attr( get_option( 'mmg_client_id' ) ); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Merchant Name</th>
						<td><input type="text" name="mmg_merchant_name" id="mmg_merchant_name" value="<?php echo esc_attr( get_option( 'mmg_merchant_name', get_bloginfo( 'name' ) ) ); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Merchant ID</th>
						<td><input type="text" name="mmg_merchant_id" id="mmg_merchant_id" value="<?php echo esc_attr( get_option( 'mmg_merchant_id' ) ); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Secret Key</th>
						<td>
							<input type="password" id="mmg_secret_key" name="mmg_secret_key" value="<?php echo esc_attr( get_option( 'mmg_secret_key' ) ); ?>" />
							<button type="button" id="toggle_secret_key">Show</button>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">RSA Public Key (MMG)</th>
						<td><textarea name="mmg_rsa_public_key" id="mmg_rsa_public_key"><?php echo esc_textarea( get_option( 'mmg_rsa_public_key' ) ); ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row">RSA Private Key (Merchant)</th>
						<td><textarea name="mmg_rsa_private_key" id="mmg_rsa_private_key"><?php echo esc_textarea( get_option( 'mmg_rsa_private_key' ) ); ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row">Live Checkout URL</th>
						<td>
							<input type="text" id="mmg_live_checkout_url" name="mmg_live_checkout_url" value="<?php echo esc_attr( get_option( 'mmg_live_checkout_url', 'https://gtt-checkout.qpass.com:8743/checkout-endpoint/home' ) ); ?>" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Sandbox Checkout URL</th>
						<td>
							<input type="text" id="mmg_demo_checkout_url" name="mmg_demo_checkout_url" value="<?php echo esc_attr( get_option( 'mmg_demo_checkout_url', 'https://gtt-uat-checkout.qpass.com:8743/checkout-endpoint/home' ) ); ?>" />
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<script>
		jQuery(document).ready(function($) {
			var originalValues = {};

			// Store original values
			$('form#mmg-checkout-settings-form :input').each(function() {
				originalValues[this.id] = $(this).val();
			});

			$('#toggle_secret_key').click(function() {
				var secretKeyInput = $('#mmg_secret_key');
				if (secretKeyInput.attr('type') === 'password') {
					secretKeyInput.attr('type', 'text');
					$(this).text('Hide');
				} else {
					secretKeyInput.attr('type', 'password');
					$(this).text('Show');
				}
			});

			function toggleLiveModeIndicator() {
				if ($('#mmg_mode').val() === 'live') {
					$('#live-mode-indicator').show();
				} else {
					$('#live-mode-indicator').hide();
				}
			}

			$('#mmg_mode').on('change', toggleLiveModeIndicator);
			toggleLiveModeIndicator(); // Initial state

			$('form#mmg-checkout-settings-form').submit(function(e) {
				var changedFields = [];
				$('form#mmg-checkout-settings-form :input').each(function() {
					if ($(this).val() !== originalValues[this.id]) {
						changedFields.push($(this).closest('tr').find('th').text());
					}
				});

				if (changedFields.length > 0) {
					var confirmMessage = '';
					if (changedFields.includes('Mode')) {
						var oldMode = originalValues['mmg_mode'];
						var newMode = $('#mmg_mode').val();
						confirmMessage = 'You have switched from ' + oldMode + ' to ' + newMode + '.\n\nAre you sure you want to save this change?';
					} else {
						confirmMessage += 'You have changed the following fields:\n' + changedFields.join('\n') + '\nAre you sure you want to save these changes?';
					}
					if (!confirm(confirmMessage)) {
						e.preventDefault();
					}
				}
			});
		});

		function copyToClipboard(text) {
			var tempInput = document.createElement('input');
			tempInput.value = text;
			document.body.appendChild(tempInput);
			tempInput.select();
			document.execCommand('copy');
			document.body.removeChild(tempInput);
			
			var successMessage = document.getElementById('copy-success');
			successMessage.style.display = 'inline';
			setTimeout(function() {
				successMessage.style.display = 'none';
			}, 2000);
		}
		</script>
		<?php
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'settings_page_mmg-checkout-settings' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Get checkout URL.
	 *
	 * @return string
	 */
	private function get_checkout_url() {
		$mode              = get_option( 'mmg_mode', 'demo' );
		$live_checkout_url = get_option( 'mmg_live_checkout_url', 'https://gtt-checkout.qpass.com:8743/checkout-endpoint/home' );
		$demo_checkout_url = get_option( 'mmg_demo_checkout_url', 'https://gtt-uat-checkout.qpass.com:8743/checkout-endpoint/home' );
		return 'live' === $mode ? $live_checkout_url : $demo_checkout_url;
	}

	/**
	 * Get callback URL.
	 *
	 * @return string
	 */
	private function get_callback_url() {
		$callback_key = get_option( 'mmg_callback_key' );
		$callback_url = $callback_key ? home_url( 'wc-api/mmg-checkout/' . $callback_key ) : 'Not generated yet';
		return $callback_url;
	}

	/**
	 * Sanitize mode.
	 *
	 * @param string $input The input to sanitize.
	 * @return string
	 */
	public function sanitize_mode( $input ) {
		$valid_modes = array( 'live', 'demo' );
		return in_array( $input, $valid_modes, true ) ? $input : 'demo';
	}

	/**
	 * Sanitize multiline field.
	 *
	 * @param string $input The input to sanitize.
	 * @return string
	 */
	public function sanitize_multiline_field( $input ) {
		return implode( "\n", array_map( 'sanitize_text_field', explode( "\n", $input ) ) );
	}
}