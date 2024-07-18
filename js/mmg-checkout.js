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
                if (response.success && response.data.checkout_url) {
                    if (isValidUrl(response.data.checkout_url)) {
                        window.location.href = response.data.checkout_url;
                    } else {
                        console.error('Invalid checkout URL:', response.data.checkout_url);
                        alert('Error: Invalid checkout URL generated');
                    }
                } else {
                    console.error('Error generating checkout URL:', response.data.error);
                    alert('Error generating checkout URL: ' + (response.data.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('Error communicating with the server. Please try again.');
            }
        });
    });

    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
});