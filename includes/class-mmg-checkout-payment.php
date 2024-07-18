<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MMG_Checkout_Payment {
    private $client_id;
    private $merchant_id;
    private $secret_key;
    private $rsa_public_key;
    private $mode;
    private $live_checkout_url = 'https://gtt-checkout.qpass.com:8743/checkout-endpoint/home';
    private $demo_checkout_url = 'https://gtt-uat-checkout.qpass.com:8743/checkout-endpoint/home';

    public function __construct() {
        // Initialize plugin
        $this->mode = get_option('mmg_mode', 'demo'); // Default mode set to 'demo'
        
        // Load settings
        require_once dirname(__FILE__) . '/class-mmg-settings.php';
        new MMG_Checkout_Settings();

        add_shortcode('mmg_checkout_button', array($this, 'checkout_button_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_generate_checkout_url', array($this, 'generate_checkout_url'));
        add_action('wp_ajax_nopriv_generate_checkout_url', array($this, 'generate_checkout_url'));
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_class'));
        add_action('plugins_loaded', array($this, 'init_gateway_class'));
        add_action('wp_ajax_mmg_payment_confirmation', array($this, 'handle_payment_confirmation'));
        add_action('wp_ajax_nopriv_mmg_payment_confirmation', array($this, 'handle_payment_confirmation'));

    }
    public function checkout_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'amount' => '',
            'description' => '',
        ), $atts);

        return '<button class="mmg-checkout-button" data-amount="' . esc_attr($atts['amount']) . '" data-description="' . esc_attr($atts['description']) . '">Pay with MMG</button>';
    }

    public function enqueue_scripts() {
        wp_enqueue_script('mmg-checkout', plugin_dir_url(__FILE__) . 'js/mmg-checkout.js', array('jquery'), '1.0', true);
        wp_localize_script('mmg-checkout', 'mmg_checkout_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    public function generate_checkout_url() {
    }

    private function get_checkout_url() {
        return $this->mode === 'live' ? $this->live_checkout_url : $this->demo_checkout_url;
    }

    private function encrypt_and_encode($data) {
    }

    public function add_gateway_class($gateways) {
        $gateways[] = 'WC_MMG_Gateway';
        return $gateways;
    }

    public function init_gateway_class() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-mmg-gateway.php';
    }

    public function handle_payment_confirmation() {
    }

    private function verify_payment_with_mmg($order_id, $status) {
        // Implement the actual verification process here
        // This should involve making an API call to MMG to confirm the payment status
        // For now, we'll just check if the status is 'success'
        return $status === 'success';
    }

}