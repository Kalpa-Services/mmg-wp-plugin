jQuery(document).ready(function($) {
    $(document).on('click', '#mmg-checkout-button', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        var $button = $(this);

        $button.prop('disabled', true).text('Processing...');

        var postData = {
            action: 'generate_checkout_url',
            nonce: mmg_checkout_params.nonce,
            order_id: orderId,
        };

        $.ajax({
            url: mmg_checkout_params.ajax_url,
            type: 'POST',
            data: postData,
            success: function(response) {
                if (response.success && isValidUrl(response.data.checkout_url)) {
                    window.location.href = response.data.checkout_url;
                } else {
                    alert(response.data || 'Invalid checkout URL.');
                }
            },
            error: function() {
                alert('Error communicating with the server. Please try again.');
            }
        });
    });
});    

function isValidUrl(url) {
    try {
        const parsedUrl = new URL(url);
        const allowedHosts = ['qpass.com'];
        const hostnameWithoutPort = parsedUrl.hostname.split(':')[0]; // Remove port number if present
        const isValid = ['https:', 'http:'].includes(parsedUrl.protocol) && allowedHosts.some(host => hostnameWithoutPort.endsWith(host));
        return isValid;
    } catch (e) {
        return false;
    }
}
