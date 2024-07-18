jQuery(document).ready(function($) {
    $('.mmg-checkout-button').on('click', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');

        $.ajax({
            url: mmg_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_checkout_url',
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.checkout_url;
                } else {
                    alert('Error generating checkout URL');
                }
            }
        });
    });
});