jQuery(document).ready(function() {
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
                    jQuery('#webhooks_secret').val(result.webhooks_secret);
                } else {
                    alert('Something went wrong, make sure you filled out API Secret correctly and saved the configuration.')
                }
            },
            error: function() {
                alert('Something went wrong, make sure you filled out API Secret correctly and saved the configuration.')
            },
            complete: function() {
                jQuery('#mondu_webhook_register_button').prop('disabled', false);
            }
        })
    });
});
