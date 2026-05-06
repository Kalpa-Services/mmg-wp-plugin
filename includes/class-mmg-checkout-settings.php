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
		add_action( 'wp_ajax_mmg_reauthenticate', array( $this, 'ajax_reauthenticate' ) );
		add_action( 'wp_ajax_mmg_check_balance', array( $this, 'ajax_check_balance' ) );
		add_action( 'wp_ajax_mmg_get_transactions', array( $this, 'ajax_get_transactions' ) );
		add_action( 'wp_ajax_mmg_lookup_transaction', array( $this, 'ajax_lookup_transaction' ) );
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

		// Live credentials.
		register_setting( 'mmg_checkout_settings', 'mmg_live_client_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_live_merchant_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_live_secret_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_live_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_live_mmg_password', array( 'sanitize_callback' => array( $this, 'sanitize_live_password' ) ) );
		register_setting( 'mmg_checkout_settings', 'mmg_live_rsa_public_key', array( 'sanitize_callback' => array( $this, 'sanitize_multiline_field' ) ) );
		register_setting( 'mmg_checkout_settings', 'mmg_live_rsa_private_key', array( 'sanitize_callback' => array( $this, 'sanitize_multiline_field' ) ) );
		register_setting( 'mmg_checkout_settings', 'mmg_live_checkout_url', array( 'sanitize_callback' => 'esc_url' ) );

		// Demo credentials.
		register_setting( 'mmg_checkout_settings', 'mmg_demo_client_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_demo_merchant_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_demo_secret_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_demo_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_demo_mmg_password', array( 'sanitize_callback' => array( $this, 'sanitize_demo_password' ) ) );
		register_setting( 'mmg_checkout_settings', 'mmg_demo_rsa_public_key', array( 'sanitize_callback' => array( $this, 'sanitize_multiline_field' ) ) );
		register_setting( 'mmg_checkout_settings', 'mmg_demo_rsa_private_key', array( 'sanitize_callback' => array( $this, 'sanitize_multiline_field' ) ) );
		register_setting( 'mmg_checkout_settings', 'mmg_demo_checkout_url', array( 'sanitize_callback' => 'esc_url' ) );

		// Common settings.
		register_setting( 'mmg_checkout_settings', 'mmg_merchant_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mmg_checkout_settings', 'mmg_live_mwallet_url', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	/**
	 * Render settings page.
	 */
	public function settings_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$page_url    = admin_url( 'options-general.php?page=mmg-checkout-settings' );
		?>
		<div class="wrap">
			<h1>MMG Checkout Settings</h1>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $page_url . '&tab=settings' ); ?>"
					class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">Settings</a>
				<a href="<?php echo esc_url( $page_url . '&tab=balance' ); ?>"
					class="nav-tab <?php echo 'balance' === $current_tab ? 'nav-tab-active' : ''; ?>">Balance</a>
				<a href="<?php echo esc_url( $page_url . '&tab=transactions' ); ?>"
					class="nav-tab <?php echo 'transactions' === $current_tab ? 'nav-tab-active' : ''; ?>">Transactions</a>
			</nav>
			<?php
			if ( 'balance' === $current_tab ) {
				$this->render_balance_tab(); } elseif ( 'transactions' === $current_tab ) {
				$this->render_transactions_tab(); } else {
					$this->render_settings_tab(); }
				?>
		</div>
		<?php
	}

	/**
	 * Render the Settings tab.
	 */
	private function render_settings_tab() {
		$mode      = get_option( 'mmg_mode', 'demo' );
		$has_token = (bool) get_transient( 'mmg_access_token_' . $mode );
		?>
		<p style="font-size: 14px;">This plugin requires a merchant account with MMG. If you don't have one, please contact MMG to get started.</p>
		<div class="notice notice-warning">
			<p><strong>Warning:</strong> Never share your private keys with anyone. MMG will never ask for your private keys.</p>
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
						<span id="live-mode-indicator" style="display:none;margin-left:10px;"><span class="blinking-dot"></span> Live Mode</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Callback URL</th>
					<td>
						<?php
						$cb = esc_url( $this->get_callback_url() );
						echo esc_html( $cb );
						?>
						<button type="button" class="button" onclick="copyToClipboard('<?php echo esc_js( $cb ); ?>')">Copy</button>
						<span id="copy-success" style="color:green;display:none;margin-left:10px;">Copied!</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Merchant Name</th>
					<td><input type="text" name="mmg_merchant_name" value="<?php echo esc_attr( get_option( 'mmg_merchant_name', get_bloginfo( 'name' ) ) ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Authentication Status</th>
					<td>
						<span id="mmg-auth-status">
							<?php if ( $has_token ) : ?>
								<span style="color:green;">&#10003; Valid token cached (<?php echo esc_html( $mode ); ?> mode)</span>
							<?php else : ?>
								<span style="color:#c00;">&#10007; No token &mdash; login required</span>
							<?php endif; ?>
						</span>
						&nbsp;<button type="button" id="mmg-reauthenticate" class="button">Re-authenticate</button>
						<span id="mmg-reauth-message" style="margin-left:10px;display:none;"></span>
					</td>
				</tr>
			</table>

			<h2>Live Credentials</h2>
			<table class="form-table">
				<tr valign="top"><th>Live Merchant ID</th><td><input type="text" name="mmg_live_merchant_id" value="<?php echo esc_attr( get_option( 'mmg_live_merchant_id' ) ); ?>" /></td></tr>
				<tr valign="top"><th>Live API Key</th><td>
					<input type="text" name="mmg_live_api_key" value="<?php echo esc_attr( get_option( 'mmg_live_api_key' ) ); ?>" style="width:350px;" />
					<p class="description">MMG-issued API key used for authentication.</p>
				</td></tr>
				<tr valign="top"><th>Live MMG Password</th><td>
					<input type="password" id="mmg_live_mmg_password" name="mmg_live_mmg_password" value="" placeholder="<?php echo get_option( 'mmg_live_mmg_password' ) ? esc_attr( 'Password saved — enter new to change' ) : esc_attr( 'Enter MMG account password' ); ?>" autocomplete="new-password" />
					<p class="description">Your MMG account login password. Stored encrypted — leave blank to keep existing.</p>
				</td></tr>
				<tr valign="top"><th>Live Client ID</th><td>
					<input type="text" name="mmg_live_client_id" value="<?php echo esc_attr( get_option( 'mmg_live_client_id' ) ); ?>" />
					<p class="description">Used in the checkout payment URL (X-Client-ID).</p>
				</td></tr>
				<tr valign="top"><th>Live Secret Key</th><td>
					<input type="password" id="mmg_live_secret_key" name="mmg_live_secret_key" value="<?php echo esc_attr( get_option( 'mmg_live_secret_key' ) ); ?>" />
					<button type="button" class="toggle-secret-key" data-target="mmg_live_secret_key">Show</button>
					<p class="description">Included in the encrypted checkout token payload.</p>
				</td></tr>
				<tr valign="top"><th>Live RSA Public Key (MMG)</th><td><textarea name="mmg_live_rsa_public_key"><?php echo esc_textarea( get_option( 'mmg_live_rsa_public_key' ) ); ?></textarea></td></tr>
				<tr valign="top"><th>Live RSA Private Key (Merchant)</th><td><textarea name="mmg_live_rsa_private_key"><?php echo esc_textarea( get_option( 'mmg_live_rsa_private_key' ) ); ?></textarea></td></tr>
				<tr valign="top"><th>Live Checkout URL</th><td><input type="text" name="mmg_live_checkout_url" value="<?php echo esc_attr( get_option( 'mmg_live_checkout_url', 'https://mmgpg.mymmg.gy/mmg-pg/web/payments' ) ); ?>" /></td></tr>
				<tr valign="top"><th>Live mwallet Base URL</th><td>
					<input type="text" name="mmg_live_mwallet_url" value="<?php echo esc_attr( get_option( 'mmg_live_mwallet_url', 'https://mwallet.mymmg.gy/olive/publisher/v1' ) ); ?>" style="width:350px;" />
					<p class="description">Full base URL including path. Leave as default unless MMG provides a different one.</p>
				</td></tr>
			</table>

			<h2>Sandbox Credentials</h2>
			<table class="form-table">
				<tr valign="top"><th>Sandbox Merchant ID</th><td><input type="text" name="mmg_demo_merchant_id" value="<?php echo esc_attr( get_option( 'mmg_demo_merchant_id' ) ); ?>" /></td></tr>
				<tr valign="top"><th>Sandbox API Key</th><td>
					<input type="text" name="mmg_demo_api_key" value="<?php echo esc_attr( get_option( 'mmg_demo_api_key' ) ); ?>" style="width:350px;" />
					<p class="description">MMG-issued API key used for authentication.</p>
				</td></tr>
				<tr valign="top"><th>Sandbox MMG Password</th><td>
					<input type="password" id="mmg_demo_mmg_password" name="mmg_demo_mmg_password" value="" placeholder="<?php echo get_option( 'mmg_demo_mmg_password' ) ? esc_attr( 'Password saved — enter new to change' ) : esc_attr( 'Enter MMG account password' ); ?>" autocomplete="new-password" />
					<p class="description">Your MMG account login password. Stored encrypted — leave blank to keep existing.</p>
				</td></tr>
				<tr valign="top"><th>Sandbox Client ID</th><td>
					<input type="text" name="mmg_demo_client_id" value="<?php echo esc_attr( get_option( 'mmg_demo_client_id' ) ); ?>" />
					<p class="description">Used in the checkout payment URL (X-Client-ID).</p>
				</td></tr>
				<tr valign="top"><th>Sandbox Secret Key</th><td>
					<input type="password" id="mmg_demo_secret_key" name="mmg_demo_secret_key" value="<?php echo esc_attr( get_option( 'mmg_demo_secret_key' ) ); ?>" />
					<button type="button" class="toggle-secret-key" data-target="mmg_demo_secret_key">Show</button>
					<p class="description">Included in the encrypted checkout token payload.</p>
				</td></tr>
				<tr valign="top"><th>Sandbox RSA Public Key (MMG)</th><td><textarea name="mmg_demo_rsa_public_key"><?php echo esc_textarea( get_option( 'mmg_demo_rsa_public_key' ) ); ?></textarea></td></tr>
				<tr valign="top"><th>Sandbox RSA Private Key (Merchant)</th><td><textarea name="mmg_demo_rsa_private_key"><?php echo esc_textarea( get_option( 'mmg_demo_rsa_private_key' ) ); ?></textarea></td></tr>
				<tr valign="top"><th>Sandbox Checkout URL</th><td><input type="text" name="mmg_demo_checkout_url" value="<?php echo esc_attr( get_option( 'mmg_demo_checkout_url', 'https://mmgpg.mmgtest.net/mmg-pg/web/payments' ) ); ?>" /></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the Balance tab.
	 */
	private function render_balance_tab() {
		$mode = get_option( 'mmg_mode', 'demo' );
		?>
		<h2>Account Balance</h2>
		<table class="form-table">
			<tr valign="top">
				<th>Merchant ID</th>
				<td><?php echo esc_html( get_option( "mmg_{$mode}_merchant_id", '—' ) ); ?></td>
			</tr>
			<tr valign="top">
				<th>Available Balance</th>
				<td>
					<span id="mmg-balance-result">—</span> &nbsp;
					<button type="button" id="mmg-check-balance" class="button">Check Balance</button>
					<span id="mmg-balance-spinner" class="spinner" style="float:none;display:none;"></span>
					<p id="mmg-balance-error" style="color:#c00;display:none;"></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the Transactions tab.
	 */
	private function render_transactions_tab() {
		?>
		<h2>Transaction History</h2>
		<table class="form-table">
			<tr valign="top">
				<th>Date Range</th>
				<td>
					<label>From: <input type="date" id="mmg-start-date" /></label> &nbsp;
					<label>To: <input type="date" id="mmg-end-date" /></label> &nbsp;
					<button type="button" id="mmg-fetch-transactions" class="button">Fetch</button>
					<span id="mmg-txn-spinner" class="spinner" style="float:none;display:none;"></span>
				</td>
			</tr>
			<tr valign="top">
				<th>Results</th>
				<td>
					<p id="mmg-txn-error" style="color:#c00;display:none;"></p>
					<div id="mmg-txn-results"></div>
				</td>
			</tr>
		</table>
		<hr />
		<h2>Transaction Lookup</h2>
		<table class="form-table">
			<tr valign="top">
				<th>Transaction ID</th>
				<td>
					<input type="text" id="mmg-lookup-txn-id" placeholder="Enter transaction ID" style="width:300px;" /> &nbsp;
					<button type="button" id="mmg-lookup-txn" class="button">Lookup</button>
					<span id="mmg-lookup-spinner" class="spinner" style="float:none;display:none;"></span>
				</td>
			</tr>
			<tr valign="top">
				<th>Result</th>
				<td>
					<p id="mmg-lookup-error" style="color:#c00;display:none;"></p>
					<pre id="mmg-lookup-result" style="background:#f6f7f7;padding:10px;display:none;white-space:pre-wrap;"></pre>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * AJAX handler: Re-authenticate.
	 */
	public function ajax_reauthenticate() {
		check_ajax_referer( 'mmg_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 ); }
		require_once __DIR__ . '/class-mmg-api-client.php';
		try {
			( new MMG_API_Client() )->reauthenticate();
			wp_send_json_success( array( 'message' => 'Authentication successful.' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Authentication failed: ' . esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * AJAX handler: Check balance.
	 */
	public function ajax_check_balance() {
		check_ajax_referer( 'mmg_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 ); }
		require_once __DIR__ . '/class-mmg-api-client.php';
		try {
			wp_send_json_success( ( new MMG_API_Client() )->get_balance() );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * AJAX handler: Get transactions.
	 */
	public function ajax_get_transactions() {
		check_ajax_referer( 'mmg_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 ); }
		$params = array();
		if ( ! empty( $_POST['start_date'] ) ) {
			$params['start_date'] = sanitize_text_field( wp_unslash( $_POST['start_date'] ) );
		}
		if ( ! empty( $_POST['end_date'] ) ) {
			$params['end_date'] = sanitize_text_field( wp_unslash( $_POST['end_date'] ) );
		}
		require_once __DIR__ . '/class-mmg-api-client.php';
		try {
			wp_send_json_success( ( new MMG_API_Client() )->get_transaction_history( $params ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * AJAX handler: Lookup transaction.
	 */
	public function ajax_lookup_transaction() {
		check_ajax_referer( 'mmg_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 ); }
		$txn_id = isset( $_POST['txn_id'] ) ? sanitize_text_field( wp_unslash( $_POST['txn_id'] ) ) : '';
		if ( empty( $txn_id ) ) {
			wp_send_json_error( array( 'message' => 'Transaction ID is required.' ) ); }
		require_once __DIR__ . '/class-mmg-api-client.php';
		try {
			wp_send_json_success( ( new MMG_API_Client() )->lookup_transaction( $txn_id ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}
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
		wp_enqueue_script( 'mmg-admin-script', plugin_dir_url( __FILE__ ) . '../admin/js/admin-script.js', array( 'jquery' ), '2.0.0', true );
		wp_localize_script(
			'mmg-admin-script',
			'mmg_admin_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mmg_admin_nonce' ),
			)
		);
		wp_enqueue_style( 'mmg-admin-style', plugin_dir_url( __FILE__ ) . '../admin/css/admin-style.css', array(), '1.0.0' );
	}

	/**
	 * Get checkout URL.
	 *
	 * @return string
	 */
	private function get_checkout_url() {
		$mode = get_option( 'mmg_mode', 'demo' );
		return get_option( "mmg_{$mode}_checkout_url", 'live' === $mode ? 'https://mmgpg.mymmg.gy/mmg-pg/web/payments' : 'https://mmgpg.mmgtest.net/mmg-pg/web/payments' );
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

	/**
	 * Sanitize live MMG password — encrypt if provided, keep existing if blank.
	 *
	 * @param string $input The input to sanitize.
	 * @return string
	 */
	public function sanitize_live_password( $input ) {
		return $this->sanitize_encrypted_password( $input, 'mmg_live_mmg_password' );
	}

	/**
	 * Sanitize demo MMG password — encrypt if provided, keep existing if blank.
	 *
	 * @param string $input The input to sanitize.
	 * @return string
	 */
	public function sanitize_demo_password( $input ) {
		return $this->sanitize_encrypted_password( $input, 'mmg_demo_mmg_password' );
	}

	/**
	 * Encrypt and save a password field, or retain the existing value if blank.
	 *
	 * @param string $input       New value from the form.
	 * @param string $option_name WP option name for the stored (encrypted) value.
	 * @return string Encrypted value to persist.
	 */
	private function sanitize_encrypted_password( $input, $option_name ) {
		if ( '' === trim( $input ) ) {
			return get_option( $option_name, '' );
		}
		return self::encrypt_value( sanitize_text_field( $input ) );
	}

	/**
	 * Encrypt a string using AES-256-CBC with a key derived from WP auth salts.
	 *
	 * @param string $value Plain-text value to encrypt.
	 * @return string Base64-encoded IV + ciphertext, or empty string on failure.
	 */
	public static function encrypt_value( $value ) {
		if ( '' === $value ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = openssl_random_pseudo_bytes( 16 );
		$enc = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $enc ) {
			return '';
		}
		return base64_encode( $iv . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a value encrypted by encrypt_value().
	 *
	 * @param string $stored Encrypted (base64-encoded) value from the database.
	 * @return string Decrypted plain-text, or empty string on failure.
	 */
	public static function decrypt_value( $stored ) {
		if ( '' === $stored ) {
			return '';
		}
		$raw = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= 16 ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = substr( $raw, 0, 16 );
		$enc = substr( $raw, 16 );
		$dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return false !== $dec ? $dec : '';
	}
}