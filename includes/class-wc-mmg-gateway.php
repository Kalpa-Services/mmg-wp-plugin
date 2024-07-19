<?php
class WC_MMG_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'mmg_checkout';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = 'MMG Checkout';
        $this->method_description = 'Enables MMG Checkout Payment flow for WooCommerce';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->supports = array(
            'products',
            'refunds',
            'checkout_block_support',
        );

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable MMG Checkout',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default' => 'MMG Checkout',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default' => 'Pay with MMG Checkout',
            ),
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        echo '<div id="mmg-checkout-container">Redirecting to MMG Checkout...</div>';
        ?>
        <button id="mmg-checkout-button" class="mmg-checkout-button">Checkout with MMG</button>
        <script>
        jQuery(function($) {
            var data = {
                'action': 'generate_checkout_url',
                'order_id': '<?php echo esc_js($order_id); ?>'
            };

            $.post(mmg_checkout_params.ajax_url, data, function(response) {
                if (response.success) {
                    window.location.href = response.data.checkout_url;
                } else {
                    alert('Error: ' + response.data);
                }
            });
        });
        </script>
        <?php
    }
}