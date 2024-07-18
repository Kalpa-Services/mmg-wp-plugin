jQuery(document).ready(function($) {
    $('.mmg-checkout-button').on('click', function(e) {
        e.preventDefault();

        $.ajax({
            url: mmg_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_checkout_url',
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