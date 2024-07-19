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
    private $callback_url;

    public function __construct() {
        // Initialize plugin
        $this->mode = get_option('mmg_mode', 'demo'); // Default mode set to 'demo'
        
        // Generate or retrieve unique callback URL
        $this->callback_url = $this->generate_unique_callback_url();
        
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
        add_action('woocommerce_api_mmg_payment_confirmation', array($this, 'handle_payment_confirmation'));
    }

    private function generate_unique_callback_url() {
        $callback_key = get_option('mmg_callback_key');
        if (!$callback_key) {
            $callback_key = wp_generate_password(32, false);
            update_option('mmg_callback_key', $callback_key);
        }
        return home_url('wc-api/mmg-checkout/' . $callback_key);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('mmg-checkout', plugin_dir_url(dirname(__FILE__)) . 'js/mmg-checkout.js', array('jquery'), '3.0', true);
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
        try {
            if (!$this->validate_public_key()) {
                throw new Exception('Invalid RSA public key');
            }

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
                'merchantTransactionId' => (string)$order->get_order_number(),
                'productDescription' => $description,
                'requestInitiationTime' => (string) round(microtime(true) * 1000),
                'merchantName' => get_option('mmg_merchant_name', get_bloginfo('name')),
            );

            $encrypted = $this->encrypt($token_data);
            $encoded = $this->url_safe_base64_encode($encrypted);
            $checkout_url = add_query_arg(array(
                'token' => $encoded,
                'merchantId' => get_option('mmg_merchant_id'),
                'X-Client-ID' => get_option('mmg_client_id'),
            ), $this->get_checkout_url());

            wp_send_json_success(array('checkout_url' => $checkout_url));
        } catch (Exception $e) {
            error_log('MMG Checkout Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            error_log('MMG Checkout Error Trace: ' . $e->getTraceAsString());
            wp_send_json_error('Error generating checkout URL: ' . $e->getMessage());
        }
    }

    private function get_checkout_url() {
        return $this->mode === 'live' ? $this->live_checkout_url : $this->demo_checkout_url;
    }

    private function encrypt($checkout_object) {
        $json_object = json_encode($checkout_object, JSON_PRETTY_PRINT);
        error_log("Checkout Object:\n $json_object\n");

        // message to bytes
        if (function_exists('mb_convert_encoding')) {
            $json_bytes = mb_convert_encoding($json_object, 'ISO-8859-1', 'UTF-8');
        } else {
            // Fallback method
            $json_bytes = utf8_decode($json_object);
        }
        
        // Load the public key
        try {
            $public_key = \phpseclib3\Crypt\PublicKeyLoader::load(get_option('mmg_rsa_public_key'));
        } catch (Exception $e) {
            error_log('Error loading public key: ' . $e->getMessage());
            throw new Exception('Failed to load RSA public key');
        }
        
        // Configure RSA encryption
        $rsa = $public_key->withPadding(\phpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
                          ->withHash('sha256')
                          ->withMGFHash('sha256');
        
        // Encrypt the data
        try {
            $ciphertext = $rsa->encrypt($json_bytes);
        } catch (Exception $e) {
            error_log('Error during encryption: ' . $e->getMessage());
            throw new Exception('Failed to encrypt data');
        }
        
        return $ciphertext;
    }

    private function url_safe_base64_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function validate_public_key() {
        $public_key = get_option('mmg_rsa_public_key');
        if (!$public_key) {
            error_log('MMG Checkout Error: RSA public key is missing');
            return false;
        }
        
        $key_resource = openssl_pkey_get_public($public_key);
        if (!$key_resource) {
            error_log('MMG Checkout Error: Invalid RSA public key');
            return false;
        }
        
        return true;
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

    private function decrypt($encrypted_data) {
        // Load the private key
        try {
            $private_key = \phpseclib3\Crypt\PublicKeyLoader::load(get_option('mmg_rsa_private_key'));
        } catch (Exception $e) {
            error_log('Error loading private key: ' . $e->getMessage());
            throw new Exception('Failed to load RSA private key');
        }
        
        // Configure RSA decryption
        $rsa = $private_key->withPadding(\phpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
                           ->withHash('sha256')
                           ->withMGFHash('sha256');
        
        // Decrypt the data
        try {
            $decrypted = $rsa->decrypt($encrypted_data);
        } catch (Exception $e) {
            error_log('Error during decryption: ' . $e->getMessage());
            throw new Exception('Failed to decrypt data');
        }
        
        return $decrypted;
    }

    private function url_safe_base64_decode($data) {
        $base64 = strtr($data, '-_', '+/');
        return base64_decode($base64);
    }

    public function handle_payment_confirmation() {
        $callback_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $stored_callback_key = get_option('mmg_callback_key');

        if (empty($callback_key) || $callback_key !== $stored_callback_key) {
            wp_die('Invalid callback URL', 'MMG Checkout Error', array('response' => 403));
        }

        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (empty($token)) {
            wp_die('Invalid token', 'MMG Checkout Error', array('response' => 400));
        }

        try {
            $decoded_token = $this->url_safe_base64_decode($token);
            $payment_data = $this->decrypt($decoded_token);
        } catch (Exception $e) {
            wp_die('Error decrypting token: ' . $e->getMessage(), 'MMG Checkout Error', array('response' => 400));
        }

        $order_id = isset($payment_data['merchantTransactionId']) ? intval($payment_data['merchantTransactionId']) : 0;
        $status = isset($payment_data['status']) ? sanitize_text_field($payment_data['status']) : '';

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die('Invalid order', 'MMG Checkout Error', array('response' => 400));
        }

        // Verify the payment status
        $payment_verified = $this->verify_payment_with_mmg($order_id, $status, $payment_data);

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

    private function verify_payment_with_mmg($order_id, $status, $payment_data) {
        // Implement the actual verification process here
        // This should involve checking the payment data against the order details
        // For now, we'll just check if the status is 'success'
        return $status === 'success' && $payment_data['amount'] == wc_get_order($order_id)->get_total();
    }
}
?>