jQuery(document).ready(function($) {
    var originalValues = {};

    // Store original values
    $('form#mmg-checkout-settings-form :input').each(function() {
        originalValues[this.id] = $(this).val();
    });

    // Ensure mmgcp_mode is set correctly
    originalValues['mmgcp_mode'] = $('#mmgcp_mode').val();

    $('.toggle-secret-key').click(function() {
        var targetId = $(this).data('target');
        var secretKeyInput = $('#' + targetId);
        if (secretKeyInput.attr('type') === 'password') {
            secretKeyInput.attr('type', 'text');
            $(this).text('Hide');
        } else {
            secretKeyInput.attr('type', 'password');
            $(this).text('Show');
        }
    });

    function toggleLiveModeIndicator() {
        if ($('#mmgcp_mode').val() === 'live') {
            $('#live-mode-indicator').show();
        } else {
            $('#live-mode-indicator').hide();
        }
    }

    $('#mmgcp_mode').on('change', toggleLiveModeIndicator);
    toggleLiveModeIndicator(); // Initial state

    $('form#mmg-checkout-settings-form').submit(function(e) {
        var changedFields = [];
        $('form#mmg-checkout-settings-form :input').each(function() {
            if ($(this).val() !== originalValues[this.id]) {
                changedFields.push($(this).closest('tr').find('th').text());
            }
        });

        if (changedFields.length > 0) {
            var confirmMessage = '';
            if (changedFields.includes('Mode')) {
                var oldMode = originalValues['mmgcp_mode'];
                var newMode = $('#mmgcp_mode').val();
                confirmMessage = 'You have switched from ' + oldMode + ' to ' + newMode + '.\n\nAre you sure you want to save this change?';
            } else {
                confirmMessage += 'You have changed the following fields:\n' + changedFields.join('\n') + '\nAre you sure you want to save these changes?';
            }
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        }
    });
});

function copyToClipboard(text) {
    var tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    
    var successMessage = document.getElementById('copy-success');
    successMessage.style.display = 'inline';
    setTimeout(function() {
        successMessage.style.display = 'none';
    }, 2000);
}