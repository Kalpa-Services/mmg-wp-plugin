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

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_generate_checkout_url', array($this, 'generate_checkout_url'));
        add_action('wp_ajax_nopriv_generate_checkout_url', array($this, 'generate_checkout_url'));
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_class'));
        add_action('plugins_loaded', array($this, 'init_gateway_class'), 11);
        add_action('wp_ajax_mmg_payment_confirmation', array($this, 'handle_payment_confirmation'));
        add_action('wp_ajax_nopriv_mmg_payment_confirmation', array($this, 'handle_payment_confirmation'));

    }

    public function enqueue_scripts() {
        wp_enqueue_script('mmg-checkout', plugin_dir_url(__FILE__) . 'js/mmg-checkout.js', array('jquery'), '1.0', true);
        wp_localize_script('mmg-checkout', 'mmg_checkout_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));

        // For blocks support
        $gateway_settings = get_option('woocommerce_mmg_checkout_settings', array());
        wp_localize_script('wc-mmg-payments-blocks', 'mmgCheckoutData', array(
            'title' => isset($gateway_settings['title']) ? $gateway_settings['title'] : 'MMG Checkout',
            'description' => isset($gateway_settings['description']) ? $gateway_settings['description'] : 'Pay with MMG Checkout',
            'supports' => array('products', 'refunds'),
        ));
    }

    public function generate_checkout_url() {
        // Verify nonce and user capabilities here

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Invalid order');
        }

        $amount = $order->get_total();
        $description = 'Order #' . $order->get_order_number();

        $token_data = array(
            'secretKey' => get_option('mmg_secret_key'),
            'amount' => $amount,
            'merchantId' => get_option('mmg_merchant_id'),
            'merchantTransactionId' => $order_id,
            'productDescription' => $description,
            'requestInitiationTime' => (string) round(microtime(true) * 1000),
            'merchantName' => get_option('mmg_merchant_name', get_bloginfo('name')),
        );

        $token = $this->encrypt_and_encode($token_data);

        $checkout_url = add_query_arg(array(
            'token' => $token,
            'merchantId' => get_option('mmg_merchant_id'),
            'X-Client-ID' => get_option('mmg_client_id'),
        ), $this->get_checkout_url());

        wp_send_json_success(array('checkout_url' => $checkout_url));
    }

    private function get_checkout_url() {
        return $this->mode === 'live' ? $this->live_checkout_url : $this->demo_checkout_url;
    }

    private function encrypt_and_encode($data) {
        $json = json_encode($data);
        $public_key = openssl_pkey_get_public(get_option('mmg_rsa_public_key'));
        openssl_public_encrypt($json, $encrypted, $public_key);
        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    public function add_gateway_class($gateways) {
        $gateways[] = 'WC_MMG_Gateway';
        return $gateways;
    }

    public function init_gateway_class() {
        if (class_exists('WC_Payment_Gateway')) {
            require_once dirname(__FILE__) . '/class-wc-mmg-gateway.php';
        }
    }


    public function handle_payment_confirmation() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die('Invalid order', 'MMG Checkout Error', array('response' => 400));
        }

        // Verify the payment status with MMG API here
        // This is a placeholder for the actual verification process
        $payment_verified = $this->verify_payment_with_mmg($order_id, $status);

        if ($payment_verified) {
            $order->payment_complete();
            $order->add_order_note('Payment completed via MMG Checkout.');
            wp_redirect($order->get_checkout_order_received_url());
        } else {
            $order->update_status('failed', 'Payment failed or was cancelled.');
            wp_redirect($order->get_checkout_payment_url());
        }
        exit;
    }

    private function verify_payment_with_mmg($order_id, $status) {
        // Implement the actual verification process here
        // This should involve making an API call to MMG to confirm the payment status
        // For now, we'll just check if the status is 'success'
        return $status === 'success';
    }

}