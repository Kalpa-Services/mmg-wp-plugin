<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MMG_Dependency_Checker {
    public static function check_dependencies() {
        if (!self::is_woocommerce_active()) {
            add_action('admin_notices', array(__CLASS__, 'woocommerce_missing_notice'));
            return false;
        }
        return true;
    }

    private static function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    public static function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('MMG Checkout requires WooCommerce to be installed and active. Please install and activate WooCommerce to use this plugin.', 'mmg-checkout'); ?></p>
        </div>
        <?php
    }
}
