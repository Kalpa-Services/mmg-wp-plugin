jQuery(document).ready(function($) {
    $('.mmg-checkout-button').on('click', function(e) {
        e.preventDefault();
        var amount = $(this).data('amount');
        var description = $(this).data('description');

        $.ajax({
            url: mmg_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_checkout_url',
                amount: amount,
                description: description
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
