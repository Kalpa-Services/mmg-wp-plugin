<?php
/**
 * MMG Checkout Settings
 *
 * This class handles the settings page for the MMG Checkout plugin.
 *
 * @package Kalpa_MMG_Checkout_Main
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Kalpa_MMG_Checkout_Settings
 */
class Kalpa_MMG_Checkout_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'kalpa_add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'kalpa_register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'kalpa_enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function kalpa_add_admin_menu() {
		add_options_page( 'MMG Checkout Settings', 'MMG Checkout', 'manage_options', 'kalpa-mmg-checkout-settings', array( $this, 'kalpa_settings_page' ) );
	}

	/**
	 * Register settings.
	 */
	public function kalpa_register_settings() {
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_mode', array( 'sanitize_callback' => array( $this, 'kalpa_sanitize_mode' ) ) );

		// Live credentials.
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_live_client_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_live_merchant_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_live_secret_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_live_rsa_public_key', array( 'sanitize_callback' => array( $this, 'kalpa_sanitize_multiline_field' ) ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_live_rsa_private_key', array( 'sanitize_callback' => array( $this, 'kalpa_sanitize_multiline_field' ) ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_live_checkout_url', array( 'sanitize_callback' => 'esc_url' ) );

		// Demo credentials.
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_demo_client_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_demo_merchant_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_demo_secret_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_demo_rsa_public_key', array( 'sanitize_callback' => array( $this, 'kalpa_sanitize_multiline_field' ) ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_demo_rsa_private_key', array( 'sanitize_callback' => array( $this, 'kalpa_sanitize_multiline_field' ) ) );
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_demo_checkout_url', array( 'sanitize_callback' => 'esc_url' ) );

		// Common settings.
		register_setting( 'kalpa_mmg_checkout_settings', 'mmg_merchant_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	/**
	 * Render settings page.
	 */
	public function kalpa_settings_page() {
		?>
		<div class="wrap">
			<h1>MMG Checkout Settings</h1>
			<p style="font-size: 14px;">
				This plugin requires a merchant account with MMG. If you don't have one, please contact MMG to get started.
			</p>
			<div class="notice notice-warning">
				<p><strong>Warning:</strong> Never share your private keys with anyone. MMG will never ask for your private keys. Keep them secure and confidential at all times.</p>
			</div>
			<form method="post" action="options.php" id="mmg-checkout-settings-form">
				<?php
				settings_fields( 'kalpa_mmg_checkout_settings' );
				do_settings_sections( 'kalpa_mmg_checkout_settings' );
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

					<tr valign="top">
						<th scope="row">Callback URL</th>
						<td>
							<?php $callback_url = esc_url( $this->kalpa_set_callback_url() ); ?>
							<?php echo esc_html( $callback_url ); ?>
							<button type="button" class="button" onclick="copyToClipboard('<?php echo esc_js( $callback_url ); ?>')">Copy</button>
							<span id="copy-success" style="color: green; display: none; margin-left: 10px;">Copied!</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Merchant Name</th>
						<td><input type="text" name="mmg_merchant_name" id="mmg_merchant_name" value="<?php echo esc_attr( get_option( 'mmg_merchant_name', get_bloginfo( 'name' ) ) ); ?>" /></td>
					</tr>
				</table>
	
				<h2>Live Credentials</h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Live Client ID</th>
						<td><input type="text" name="mmg_live_client_id" id="mmg_live_client_id" value="<?php echo esc_attr( get_option( 'mmg_live_client_id' ) ); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Live Merchant ID</th>
						<td><input type="text" name="mmg_live_merchant_id" id="mmg_live_merchant_id" value="<?php echo esc_attr( get_option( 'mmg_live_merchant_id' ) ); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Live Secret Key</th>
						<td>
							<input type="password" id="mmg_live_secret_key" name="mmg_live_secret_key" value="<?php echo esc_attr( get_option( 'mmg_live_secret_key' ) ); ?>" />
							<button type="button" class="toggle-secret-key" data-target="mmg_live_secret_key">Show</button>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Live RSA Public Key (MMG)</th>
						<td><textarea name="mmg_live_rsa_public_key" id="mmg_live_rsa_public_key"><?php echo esc_textarea( get_option( 'mmg_live_rsa_public_key' ) ); ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row">Live RSA Private Key (Merchant)</th>
						<td><textarea name="mmg_live_rsa_private_key" id="mmg_live_rsa_private_key"><?php echo esc_textarea( get_option( 'mmg_live_rsa_private_key' ) ); ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row">Live Checkout URL</th>
						<td>
							<input type="text" id="mmg_live_checkout_url" name="mmg_live_checkout_url" value="<?php echo esc_attr( get_option( 'mmg_live_checkout_url', 'https://gtt-checkout.qpass.com:8743/checkout-endpoint/home' ) ); ?>" />
						</td>
					</tr>
				</table>
	
				<h2>Sandbox Credentials</h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Sandbox Client ID</th>
						<td><input type="text" name="mmg_demo_client_id" id="mmg_demo_client_id" value="<?php echo esc_attr( get_option( 'mmg_demo_client_id' ) ); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Sandbox Merchant ID</th>
						<td><input type="text" name="mmg_demo_merchant_id" id="mmg_demo_merchant_id" value="<?php echo esc_attr( get_option( 'mmg_demo_merchant_id' ) ); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">Sandbox Secret Key</th>
						<td>
							<input type="password" id="mmg_demo_secret_key" name="mmg_demo_secret_key" value="<?php echo esc_attr( get_option( 'mmg_demo_secret_key' ) ); ?>" />
							<button type="button" class="toggle-secret-key" data-target="mmg_demo_secret_key">Show</button>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Sandbox RSA Public Key (MMG)</th>
						<td><textarea name="mmg_demo_rsa_public_key" id="mmg_demo_rsa_public_key"><?php echo esc_textarea( get_option( 'mmg_demo_rsa_public_key' ) ); ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row">Sandbox RSA Private Key (Merchant)</th>
						<td><textarea name="mmg_demo_rsa_private_key" id="mmg_demo_rsa_private_key"><?php echo esc_textarea( get_option( 'mmg_demo_rsa_private_key' ) ); ?></textarea></td>
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
		<?php
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page.
	 */
	public function kalpa_enqueue_admin_scripts( $hook ) {
		if ( 'kalpa_settings_page_mmg-checkout-settings' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'mmg-admin-script', plugin_dir_url( __FILE__ ) . '../admin/js/admin-script.js', array( 'jquery' ), '1.0.0', true );
		wp_enqueue_style( 'mmg-admin-style', plugin_dir_url( __FILE__ ) . '../admin/css/admin-style.css', array(), '1.0.0' );
	}

	/**
	 * Get checkout URL.
	 *
	 * @return string
	 */
	private function kalpa_set_checkout_url() {
		$mode = get_option( 'mmg_mode', 'demo' );
		return get_option( "mmg_{$mode}_checkout_url", 'live' === $mode ? 'https://gtt-checkout.qpass.com:8743/checkout-endpoint/home' : 'https://gtt-uat-checkout.qpass.com:8743/checkout-endpoint/home' );
	}

	/**
	 * Get callback URL.
	 *
	 * @return string
	 */
	private function kalpa_set_callback_url() {
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
	public function kalpa_sanitize_mode( $input ) {
		$valid_modes = array( 'live', 'demo' );
		return in_array( $input, $valid_modes, true ) ? $input : 'demo';
	}

	/**
	 * Sanitize multiline field.
	 *
	 * @param string $input The input to sanitize.
	 * @return string
	 */
	public function kalpa_sanitize_multiline_field( $input ) {
		return implode( "\n", array_map( 'sanitize_text_field', explode( "\n", $input ) ) );
	}
}