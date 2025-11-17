jQuery(document).ready(function() {
    // Disable webhooks_secret field so users can't manually edit it
    jQuery('#webhooks_secret').prop('disabled', true).css('background-color', '#e9ecef');
    
    // Enable field before form submit so value gets sent
    jQuery('form').on('submit', function() {
        jQuery('#webhooks_secret').prop('disabled', false);
    });
    
    if (jQuery('.mondu_webhook_button').length === 0) {
        jQuery('#webhooks_secret')
            .parent()
            .parent()
            .after(`
                <div class="row mr-2 mb-3 mondu_webhook_button">
                    <div class="ml-auto col-sm-6 col-xl-auto">
                        <button name="mondu_webhook_register" id="mondu_webhook_register_button" class="btn btn-primary btn-block align-middle">
                            <span>Register Webhooks</span>
                        </button>
                    </div>
                </div>`
            );
    }


    jQuery('#mondu_webhook_register_button').on('click', function(event) {
        event.preventDefault();
        const requestUrl = jQuery('input[id=mondu_post_url]').val();
        jQuery('#mondu_webhook_register_button').prop('disabled', true);

        jQuery.ajax({
            url: requestUrl,
            type: 'post',
            data: {
                request_type: 'registerWebhooks'
            },
            success: function (result) {
                if (result.success) {
                    // Temporarily enable field to update value, then disable again
                    jQuery('#webhooks_secret')
                        .prop('disabled', false)
                        .val(result.webhooks_secret)
                        .prop('disabled', true);
                    alert('Webhook registered successfully!');
                } else {
                    alert('Something went wrong, make sure you filled out API Secret correctly and saved the configuration.')
                }
            },
            error: function(xhr) {
                let errorMessage = 'Something went wrong, make sure you filled out API Secret correctly and saved the configuration.';
                
                // Check if status code is 422 (Unprocessable Entity) - webhooks already registered
                if (xhr.status === 422) {
                    errorMessage = 'Webhooks are already registered.';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    const apiMessage = xhr.responseJSON.message.toLowerCase();
                    
                    // Double-check message content for webhook duplicates
                    if (apiMessage.includes('already exists') || 
                        apiMessage.includes('already registered') ||
                        apiMessage.includes('duplicate')) {
                        errorMessage = 'Webhooks are already registered.';
                    } else {
                        errorMessage = xhr.responseJSON.message;
                    }
                }
                
                alert(errorMessage);
            },
            complete: function() {
                jQuery('#mondu_webhook_register_button').prop('disabled', false);
            }
        })
    });
});
