<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MMG_Checkout_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_admin_menu() {
        add_options_page('MMG Checkout Settings', 'MMG Checkout', 'manage_options', 'mmg-checkout-settings', array($this, 'settings_page'));
    }

    public function register_settings() {
        register_setting('mmg_checkout_settings', 'mmg_mode');
        register_setting('mmg_checkout_settings', 'mmg_client_id');
        register_setting('mmg_checkout_settings', 'mmg_merchant_id');
        register_setting('mmg_checkout_settings', 'mmg_secret_key');
        register_setting('mmg_checkout_settings', 'mmg_rsa_public_key');
        register_setting('mmg_checkout_settings', 'mmg_rsa_private_key');
        register_setting('mmg_checkout_settings', 'mmg_merchant_name');
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>MMG Checkout Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('mmg_checkout_settings');
                do_settings_sections('mmg_checkout_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Mode</th>
                        <td>
                            <select name="mmg_mode">
                                <option value="live" <?php selected(get_option('mmg_mode'), 'live'); ?>>Live</option>
                                <option value="demo" <?php selected(get_option('mmg_mode'), 'demo'); ?>>Demo</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Checkout URL</th>
                        <td><input type="text" value="<?php echo esc_attr($this->get_checkout_url()); ?>" readonly /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Client ID</th>
                        <td><input type="text" name="mmg_client_id" value="<?php echo esc_attr(get_option('mmg_client_id')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Merchant Name</th>
                        <td><input type="text" name="mmg_merchant_name" value="<?php echo esc_attr(get_option('mmg_merchant_name', get_bloginfo('name'))); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Merchant ID</th>
                        <td><input type="text" name="mmg_merchant_id" value="<?php echo esc_attr(get_option('mmg_merchant_id')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Secret Key</th>
                        <td>
                            <input type="password" id="mmg_secret_key" name="mmg_secret_key" value="<?php echo esc_attr(get_option('mmg_secret_key')); ?>" />
                            <button type="button" id="toggle_secret_key">Show</button>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">RSA Public Key</th>
                        <td><textarea name="mmg_rsa_public_key"><?php echo esc_textarea(get_option('mmg_rsa_public_key')); ?></textarea></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">RSA Private Key</th>
                        <td><textarea name="mmg_rsa_private_key"><?php echo esc_textarea(get_option('mmg_rsa_private_key')); ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
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
        });
        </script>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook != 'settings_page_mmg-checkout-settings') {
            return;
        }
        wp_enqueue_script('jquery');
    }

    private function get_checkout_url() {
        $mode = get_option('mmg_mode', 'demo');
        $live_checkout_url = 'https://gtt-checkout.qpass.com:8743/checkout-endpoint/home';
        $demo_checkout_url = 'https://gtt-uat-checkout.qpass.com:8743/checkout-endpoint/home';
        return $mode === 'live' ? $live_checkout_url : $demo_checkout_url;
    }
}
