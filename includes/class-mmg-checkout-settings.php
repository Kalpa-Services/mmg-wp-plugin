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
		add_menu_page(
			'MMG Checkout',
			'MMG Checkout',
			'manage_options',
			'mmg-checkout-settings',
			array( $this, 'settings_page' ),
			'dashicons-money-alt',
			58
		);
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
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$mode        = get_option( 'mmg_mode', 'demo' );
		$has_token   = (bool) get_transient( 'mmg_access_token_' . $mode );
		$logo_url    = plugin_dir_url( __FILE__ ) . '../public/images/mmg-logo-white.png';

		$tabs = array(
			'dashboard'    => array(
				'label' => 'Dashboard',
				'icon'  => 'dashicons-dashboard',
			),
			'credentials'  => array(
				'label' => 'Credentials',
				'icon'  => 'dashicons-lock',
			),
			'balance'      => array(
				'label' => 'Balance',
				'icon'  => 'dashicons-chart-area',
			),
			'transactions' => array(
				'label' => 'Transactions',
				'icon'  => 'dashicons-list-view',
			),
		);
		?>
		<div class="mmg-dashboard-wrap">
			<!-- Header -->
			<div class="mmg-header">
				<div class="mmg-header-left">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="MMG" class="mmg-header-logo" />
					<div>
						<h1 class="mmg-header-title">MMG Checkout</h1>
						<div class="mmg-header-subtitle">Payment Gateway Control Panel</div>
					</div>
				</div>
				<div class="mmg-header-right">
					<span class="mmg-badge mmg-badge-version">v<?php echo esc_html( MMG_PLUGIN_VERSION ); ?></span>
					<?php if ( 'live' === $mode ) : ?>
						<span class="mmg-badge mmg-badge-live"><span class="mmg-badge-dot"></span> Live</span>
					<?php else : ?>
						<span class="mmg-badge mmg-badge-sandbox"><span class="mmg-badge-dot"></span> Sandbox</span>
					<?php endif; ?>
				</div>
			</div>

			<!-- Body -->
			<div class="mmg-body">
				<!-- Sidebar -->
				<nav class="mmg-sidebar">
					<ul class="mmg-nav">
						<?php foreach ( $tabs as $slug => $tab ) : ?>
							<li>
								<a href="#<?php echo esc_attr( $slug ); ?>"
									class="mmg-nav-link <?php echo $slug === $current_tab ? 'mmg-nav-active' : ''; ?>"
									data-tab="<?php echo esc_attr( $slug ); ?>">
									<span class="mmg-nav-icon"><span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span></span>
									<?php echo esc_html( $tab['label'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>

				<!-- Content -->
				<div class="mmg-content">
					<div id="mmg-panel-dashboard" class="mmg-tab-panel <?php echo 'dashboard' === $current_tab ? 'mmg-tab-active' : ''; ?>">
						<?php $this->render_dashboard_tab( $mode, $has_token ); ?>
					</div>
					<div id="mmg-panel-credentials" class="mmg-tab-panel <?php echo 'credentials' === $current_tab ? 'mmg-tab-active' : ''; ?>">
						<?php $this->render_credentials_tab( $mode ); ?>
					</div>
					<div id="mmg-panel-balance" class="mmg-tab-panel <?php echo 'balance' === $current_tab ? 'mmg-tab-active' : ''; ?>">
						<?php $this->render_balance_tab(); ?>
					</div>
					<div id="mmg-panel-transactions" class="mmg-tab-panel <?php echo 'transactions' === $current_tab ? 'mmg-tab-active' : ''; ?>">
						<?php $this->render_transactions_tab(); ?>
					</div>
				</div>
			</div>
		</div>
		<!-- Toast container -->
		<div id="mmg-toast" class="mmg-toast"></div>
		<?php
	}

	/**
	 * Render the Dashboard tab.
	 *
	 * @param string $mode       Current mode (live or demo).
	 * @param bool   $has_token  Whether a valid token exists.
	 */
	private function render_dashboard_tab( $mode, $has_token ) {
		$cb = esc_url( $this->get_callback_url() );
		?>
		<h2 class="mmg-section-title">Overview</h2>
		<p class="mmg-section-desc">Quick view of your MMG Checkout integration status and configuration.</p>

		<div class="mmg-alert mmg-alert-warning">
			<span class="mmg-alert-icon dashicons dashicons-shield"></span>
			<div><strong>Security Notice:</strong> Never share your private keys with anyone. MMG will never ask for your private keys.</div>
		</div>

		<div class="mmg-stats-grid">
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-accent"><span class="dashicons dashicons-admin-settings"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Mode</p>
					<p class="mmg-stat-value"><?php echo 'live' === $mode ? 'Live' : 'Sandbox'; ?></p>
				</div>
			</div>
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon <?php echo $has_token ? 'mmg-stat-icon-success' : 'mmg-stat-icon-danger'; ?>" id="mmg-auth-stat-icon-box">
					<span class="dashicons <?php echo $has_token ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" id="mmg-auth-stat-icon"></span>
				</div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Auth Status</p>
					<p class="mmg-stat-value">
						<span class="mmg-status-inline <?php echo $has_token ? 'mmg-status-connected' : 'mmg-status-disconnected'; ?>" id="mmg-auth-status-pill">
							<span class="mmg-status-dot"></span>
							<span id="mmg-auth-status-text"><?php echo $has_token ? 'Connected' : 'Not Connected'; ?></span>
						</span>
					</p>
				</div>
			</div>
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-accent"><span class="dashicons dashicons-store"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Merchant</p>
					<p class="mmg-stat-value mmg-stat-value-sm"><?php echo esc_html( get_option( 'mmg_merchant_name', get_bloginfo( 'name' ) ) ); ?></p>
				</div>
			</div>
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-accent"><span class="dashicons dashicons-admin-users"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Merchant ID</p>
					<p class="mmg-stat-value mmg-stat-value-sm"><?php echo esc_html( get_option( "mmg_{$mode}_merchant_id", '—' ) ); ?></p>
				</div>
			</div>
		</div>

		<!-- Quick Actions -->
		<div class="mmg-card">
			<div class="mmg-card-body">
				<div class="mmg-form-grid">
					<div class="mmg-form-row">
						<div class="mmg-form-label">Callback URL</div>
						<div class="mmg-form-control">
							<div class="mmg-input-group">
								<input type="text" value="<?php echo esc_attr( $cb ); ?>" readonly style="background:var(--mmg-surface-alt);" />
								<button type="button" class="mmg-btn mmg-btn-secondary mmg-btn-sm" onclick="mmgCopyToClipboard('<?php echo esc_js( $cb ); ?>')">
									<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;"></span> Copy
								</button>
							</div>
							<p class="mmg-form-hint">Provide this URL to MMG for payment callbacks.</p>
						</div>
					</div>
					<div class="mmg-form-row">
						<div class="mmg-form-label">Authentication</div>
						<div class="mmg-form-control">
							<button type="button" id="mmg-reauthenticate" class="mmg-btn mmg-btn-secondary mmg-btn-sm">
								<span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;"></span> Re-authenticate
							</button>
							<span id="mmg-reauth-message" class="mmg-form-hint" style="display:none;margin-left:8px;"></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Credentials tab.
	 *
	 * @param string $mode Current mode (live or demo).
	 */
	private function render_credentials_tab( $mode ) {
		?>
		<h2 class="mmg-section-title">API Credentials</h2>
		<p class="mmg-section-desc">Configure your MMG merchant credentials for live and sandbox environments.</p>

		<form method="post" action="options.php" id="mmg-checkout-settings-form">
			<?php settings_fields( 'mmg_checkout_settings' ); ?>

			<!-- Common Settings -->
			<div class="mmg-card" style="margin-bottom:20px;">
				<div class="mmg-card-header" data-collapse="mmg-common">
					<h3 class="mmg-card-header-title"><span class="dashicons dashicons-admin-generic"></span> General Settings</h3>
					<span class="mmg-card-chevron dashicons dashicons-arrow-down-alt2"></span>
				</div>
				<div class="mmg-card-body" id="mmg-common">
					<div class="mmg-form-grid">
						<div class="mmg-form-row">
							<div class="mmg-form-label">Mode</div>
							<div class="mmg-form-control">
								<select name="mmg_mode" id="mmg_mode">
									<option value="live" <?php selected( $mode, 'live' ); ?>>Live</option>
									<option value="demo" <?php selected( $mode, 'demo' ); ?>>Sandbox</option>
								</select>
							</div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">Merchant Name</div>
							<div class="mmg-form-control">
								<input type="text" name="mmg_merchant_name" value="<?php echo esc_attr( get_option( 'mmg_merchant_name', get_bloginfo( 'name' ) ) ); ?>" />
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Live Credentials -->
			<div class="mmg-card" style="margin-bottom:20px;">
				<div class="mmg-card-header" data-collapse="mmg-live-creds">
					<h3 class="mmg-card-header-title"><span class="dashicons dashicons-lock"></span> Live Credentials</h3>
					<span class="mmg-card-chevron dashicons dashicons-arrow-down-alt2"></span>
				</div>
				<div class="mmg-card-body" id="mmg-live-creds">
					<div class="mmg-form-grid">
						<div class="mmg-form-row">
							<div class="mmg-form-label">Merchant ID</div>
							<div class="mmg-form-control"><input type="text" name="mmg_live_merchant_id" value="<?php echo esc_attr( get_option( 'mmg_live_merchant_id' ) ); ?>" /></div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">API Key</div>
							<div class="mmg-form-control">
								<input type="text" name="mmg_live_api_key" value="<?php echo esc_attr( get_option( 'mmg_live_api_key' ) ); ?>" />
								<p class="mmg-form-hint">MMG-issued API key used for authentication.</p>
							</div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">MMG Password</div>
							<div class="mmg-form-control">
								<input type="password" id="mmg_live_mmg_password" name="mmg_live_mmg_password" value="" placeholder="<?php echo get_option( 'mmg_live_mmg_password' ) ? esc_attr( 'Password saved — enter new to change' ) : esc_attr( 'Enter MMG account password' ); ?>" autocomplete="new-password" />
								<p class="mmg-form-hint">Stored encrypted — leave blank to keep existing.</p>
							</div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">Client ID</div>
							<div class="mmg-form-control">
								<input type="text" name="mmg_live_client_id" value="<?php echo esc_attr( get_option( 'mmg_live_client_id' ) ); ?>" />
								<p class="mmg-form-hint">Used in the checkout payment URL (X-Client-ID).</p>
							</div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">Secret Key</div>
							<div class="mmg-form-control">
								<div class="mmg-input-group">
									<input type="password" id="mmg_live_secret_key" name="mmg_live_secret_key" value="<?php echo esc_attr( get_option( 'mmg_live_secret_key' ) ); ?>" />
									<button type="button" class="mmg-btn mmg-btn-secondary mmg-btn-sm toggle-secret-key" data-target="mmg_live_secret_key">Show</button>
								</div>
								<p class="mmg-form-hint">Included in the encrypted checkout token payload.</p>
							</div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">RSA Public Key <span class="mmg-label-hint">(MMG)</span></div>
							<div class="mmg-form-control"><textarea name="mmg_live_rsa_public_key"><?php echo esc_textarea( get_option( 'mmg_live_rsa_public_key' ) ); ?></textarea></div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">RSA Private Key <span class="mmg-label-hint">(Merchant)</span></div>
							<div class="mmg-form-control"><textarea name="mmg_live_rsa_private_key"><?php echo esc_textarea( get_option( 'mmg_live_rsa_private_key' ) ); ?></textarea></div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">Checkout URL</div>
							<div class="mmg-form-control"><input type="text" name="mmg_live_checkout_url" value="<?php echo esc_attr( get_option( 'mmg_live_checkout_url', 'https://mmgpg.mymmg.gy/mmg-pg/web/payments' ) ); ?>" /></div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">mWallet Base URL</div>
							<div class="mmg-form-control">
								<input type="text" name="mmg_live_mwallet_url" value="<?php echo esc_attr( get_option( 'mmg_live_mwallet_url', 'https://mwallet.mymmg.gy/olive/publisher/v1' ) ); ?>" />
								<p class="mmg-form-hint">Leave as default unless MMG provides a different one.</p>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Sandbox Credentials -->
			<div class="mmg-card">
				<div class="mmg-card-header" data-collapse="mmg-sandbox-creds">
					<h3 class="mmg-card-header-title"><span class="dashicons dashicons-visibility"></span> Sandbox Credentials</h3>
					<span class="mmg-card-chevron dashicons dashicons-arrow-down-alt2"></span>
				</div>
				<div class="mmg-card-body" id="mmg-sandbox-creds">
					<div class="mmg-form-grid">
						<div class="mmg-form-row">
							<div class="mmg-form-label">Merchant ID</div>
							<div class="mmg-form-control"><input type="text" name="mmg_demo_merchant_id" value="<?php echo esc_attr( get_option( 'mmg_demo_merchant_id' ) ); ?>" /></div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">API Key</div>
							<div class="mmg-form-control">
								<input type="text" name="mmg_demo_api_key" value="<?php echo esc_attr( get_option( 'mmg_demo_api_key' ) ); ?>" />
								<p class="mmg-form-hint">MMG-issued API key used for authentication.</p>
							</div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">MMG Password</div>
							<div class="mmg-form-control">
								<input type="password" id="mmg_demo_mmg_password" name="mmg_demo_mmg_password" value="" placeholder="<?php echo get_option( 'mmg_demo_mmg_password' ) ? esc_attr( 'Password saved — enter new to change' ) : esc_attr( 'Enter MMG account password' ); ?>" autocomplete="new-password" />
								<p class="mmg-form-hint">Stored encrypted — leave blank to keep existing.</p>
							</div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">Client ID</div>
							<div class="mmg-form-control">
								<input type="text" name="mmg_demo_client_id" value="<?php echo esc_attr( get_option( 'mmg_demo_client_id' ) ); ?>" />
								<p class="mmg-form-hint">Used in the checkout payment URL (X-Client-ID).</p>
							</div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">Secret Key</div>
							<div class="mmg-form-control">
								<div class="mmg-input-group">
									<input type="password" id="mmg_demo_secret_key" name="mmg_demo_secret_key" value="<?php echo esc_attr( get_option( 'mmg_demo_secret_key' ) ); ?>" />
									<button type="button" class="mmg-btn mmg-btn-secondary mmg-btn-sm toggle-secret-key" data-target="mmg_demo_secret_key">Show</button>
								</div>
								<p class="mmg-form-hint">Included in the encrypted checkout token payload.</p>
							</div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">RSA Public Key <span class="mmg-label-hint">(MMG)</span></div>
							<div class="mmg-form-control"><textarea name="mmg_demo_rsa_public_key"><?php echo esc_textarea( get_option( 'mmg_demo_rsa_public_key' ) ); ?></textarea></div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">RSA Private Key <span class="mmg-label-hint">(Merchant)</span></div>
							<div class="mmg-form-control"><textarea name="mmg_demo_rsa_private_key"><?php echo esc_textarea( get_option( 'mmg_demo_rsa_private_key' ) ); ?></textarea></div>
						</div>
						<div class="mmg-form-row">
							<div class="mmg-form-label">Checkout URL</div>
							<div class="mmg-form-control"><input type="text" name="mmg_demo_checkout_url" value="<?php echo esc_attr( get_option( 'mmg_demo_checkout_url', 'https://mmgpg.mmgtest.net/mmg-pg/web/payments' ) ); ?>" /></div>
						</div>
					</div>
				</div>
			</div>

			<button type="submit" class="mmg-btn mmg-btn-primary mmg-btn-save">
				<span class="dashicons dashicons-saved" style="font-size:16px;width:16px;height:16px;"></span> Save Changes
			</button>
		</form>
		<?php
	}

	/**
	 * Render the Balance tab.
	 */
	private function render_balance_tab() {
		$mode = get_option( 'mmg_mode', 'demo' );
		?>
		<h2 class="mmg-section-title">Account Balance</h2>
		<p class="mmg-section-desc">Check your current MMG merchant account balance.</p>

		<p class="mmg-section-label" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--mmg-text-muted);margin:0 0 10px;">Account Info</p>
		<div class="mmg-stats-grid" style="margin-bottom:24px;">
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-accent"><span class="dashicons dashicons-admin-users"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Merchant ID</p>
					<p class="mmg-stat-value mmg-stat-value-sm"><?php echo esc_html( get_option( "mmg_{$mode}_merchant_id", '—' ) ); ?></p>
				</div>
			</div>
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-warning"><span class="dashicons dashicons-tickets-alt"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Currency</p>
					<p class="mmg-stat-value" id="mmg-balance-currency">—</p>
				</div>
			</div>
		</div>

		<p class="mmg-section-label" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--mmg-text-muted);margin:0 0 10px;">Balances</p>
		<div class="mmg-stats-grid" style="margin-bottom:24px;">
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-success"><span class="dashicons dashicons-money-alt"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Current Balance</p>
					<p class="mmg-stat-value" id="mmg-balance-current">—</p>
				</div>
			</div>
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-accent"><span class="dashicons dashicons-chart-area"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Available Balance</p>
					<p class="mmg-stat-value" id="mmg-balance-result">—</p>
				</div>
			</div>
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-warning"><span class="dashicons dashicons-lock"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Reserved Balance</p>
					<p class="mmg-stat-value" id="mmg-balance-reserved">—</p>
				</div>
			</div>
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-warning"><span class="dashicons dashicons-clock"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Uncleared Balance</p>
					<p class="mmg-stat-value" id="mmg-balance-uncleared">—</p>
				</div>
			</div>
		</div>

		<p class="mmg-section-label" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--mmg-text-muted);margin:0 0 10px;">Limits</p>
		<div class="mmg-stats-grid" style="margin-bottom:24px;">
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-danger"><span class="dashicons dashicons-arrow-up-alt"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Upper Limit</p>
					<p class="mmg-stat-value" id="mmg-balance-upper-limit">—</p>
				</div>
			</div>
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-danger"><span class="dashicons dashicons-arrow-down-alt"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Lower Limit</p>
					<p class="mmg-stat-value" id="mmg-balance-lower-limit">—</p>
				</div>
			</div>
			<div class="mmg-stat-card">
				<div class="mmg-stat-icon mmg-stat-icon-accent"><span class="dashicons dashicons-bell"></span></div>
				<div class="mmg-stat-content">
					<p class="mmg-stat-label">Notification Threshold</p>
					<p class="mmg-stat-value" id="mmg-balance-threshold">—</p>
				</div>
			</div>
		</div>

		<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
			<button type="button" id="mmg-check-balance" class="mmg-btn mmg-btn-primary">
				<span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;"></span> Refresh Balance
			</button>
			<span id="mmg-balance-spinner" class="spinner" style="float:none;display:none;"></span>
			<span id="mmg-balance-last-updated" style="font-size:12px;color:var(--mmg-text-muted);display:none;">
				<span class="dashicons dashicons-backup" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span>
				Last updated: <span id="mmg-balance-timestamp"></span>
			</span>
		</div>
		<p id="mmg-balance-error" class="mmg-error-text"></p>
		<?php
	}

	/**
	 * Render the Transactions tab.
	 */
	private function render_transactions_tab() {
		?>
		<h2 class="mmg-section-title">Transaction History</h2>
		<p class="mmg-section-desc">View and search your MMG payment transactions.</p>

		<div class="mmg-card" style="margin-bottom:24px;">
			<div class="mmg-card-header">
				<h3 class="mmg-card-header-title"><span class="dashicons dashicons-calendar-alt"></span> Date Range Query</h3>
			</div>
			<div class="mmg-card-body">
				<div class="mmg-date-range">
					<label>From: <input type="date" id="mmg-start-date" /></label>
					<label>To: <input type="date" id="mmg-end-date" /></label>
					<button type="button" id="mmg-fetch-transactions" class="mmg-btn mmg-btn-primary mmg-btn-sm">
						<span class="dashicons dashicons-search" style="font-size:14px;width:14px;height:14px;"></span> Fetch
					</button>
					<span id="mmg-txn-spinner" class="spinner" style="float:none;display:none;"></span>
				</div>
				<p id="mmg-txn-error" class="mmg-error-text"></p>
				<div id="mmg-txn-results"></div>
			</div>
		</div>

		<div class="mmg-card">
			<div class="mmg-card-header">
				<h3 class="mmg-card-header-title"><span class="dashicons dashicons-search"></span> Transaction Lookup</h3>
			</div>
			<div class="mmg-card-body">
				<div class="mmg-lookup-row">
					<input type="text" id="mmg-lookup-txn-id" placeholder="Enter transaction ID" />
					<button type="button" id="mmg-lookup-txn" class="mmg-btn mmg-btn-primary mmg-btn-sm">
						<span class="dashicons dashicons-visibility" style="font-size:14px;width:14px;height:14px;"></span> Lookup
					</button>
					<span id="mmg-lookup-spinner" class="spinner" style="float:none;display:none;"></span>
				</div>
				<p id="mmg-lookup-error" class="mmg-error-text"></p>
				<pre id="mmg-lookup-result" class="mmg-lookup-result"></pre>
			</div>
		</div>
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
		if ( 'toplevel_page_mmg-checkout-settings' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_script( 'mmg-admin-script', plugin_dir_url( __FILE__ ) . '../admin/js/admin-script.js', array( 'jquery' ), MMG_PLUGIN_VERSION, true );
		wp_localize_script(
			'mmg-admin-script',
			'mmg_admin_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mmg_admin_nonce' ),
			)
		);
		wp_enqueue_style( 'mmg-admin-style', plugin_dir_url( __FILE__ ) . '../admin/css/admin-style.css', array(), MMG_PLUGIN_VERSION );
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
		if ( false === $dec ) {
			return '';
		}
		// A non-printable character indicates the key has changed (e.g. salt rotation or DB migration).
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
		if ( ! preg_match( '/^[\x20-\x7E]+$/', $dec ) ) {
			error_log( '[MMG] decrypt_value: decrypted value contains non-printable characters — password was likely encrypted with a different WordPress auth salt. Re-save the password in MMG Checkout Settings.' );
			return '';
		}
		// phpcs:enable
		return $dec;
	}
}