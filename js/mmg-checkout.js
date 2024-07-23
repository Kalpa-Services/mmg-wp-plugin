jQuery(document).ready(function($) {
    $(document).on('click', '#mmg-checkout-button', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        var $button = $(this);

        $button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: mmg_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_checkout_url',
                order_id: orderId,
                nonce: mmg_checkout_params.nonce
            },
            success: function(response) {
                if (response.success && response.data.checkout_url) {
                    window.location.href = response.data.checkout_url;
                } else {
                    alert('Error generating checkout URL: ' + (response.data.error || 'Unknown error'));
                    $button.prop('disabled', false).text('Pay with MMG');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('Error communicating with the server. Please try again.');
                $button.prop('disabled', false).text('Pay with MMG');
            }
        });
    });
});