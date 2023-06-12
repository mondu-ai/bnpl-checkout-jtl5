class MonduCheckoutPlugin {
    init() {
        this._registerState();
        this._registerPaymentMethodEvents();

        if (!this._isMonduPaymentSelected())
            return;
    }

    _registerState() {
        this.state = {
            isSuccess: false
        };
    }

    _monduPresent() {
        $('.mondu-payment-method-groups').length > 0;
    }

    _paypalEnabled() {
        return typeof ppp !== 'undefined';
    }

    _registerPaymentMethodEvents() {
        var that = this;
        var submittedForm = false;
        var clearedInitially = false;

        jQuery('html').on('change', '[name="Zahlungsart"]', function () {
            if (that._paypalEnabled()) {

                try {
                    ppp.deselectPaymentMethod();
                    ppp.setPaymentMethod(null);
                } catch (e) {
                    if ($(this).closest('.mondu-card').length == 0) {
                        $('.mondu-card-active').removeClass('mondu-card-active');
                    }
                }

                $(this).closest('.mondu-card').addClass('mondu-card-active');
            } else {
                if ($(this).closest('.mondu-card').length == 0) {
                    $('.mondu-card-active').removeClass('mondu-card-active');
                }
            }
        });

        jQuery('html').on('click', '.mondu-card', function () {
            if (!$(this).hasClass('mondu-card-active')) {
                $(this).find('input[type="radio"]').first().prop('checked', true);
                $(this).find('input[type="radio"]').first().trigger('change');

                $('.mondu-card-active').removeClass('mondu-card-active');
                $(this).closest('.mondu-card').addClass('mondu-card-active');
            }
        });


        window.addEventListener("message", (event) => {
            var isPaypal = event.origin.includes('paypal');

            if (isPaypal) {
                var action = JSON.parse(event?.data)?.action;

                if (isPaypal && (action == 'enableContinueButton')) {
                    that._deselectMondu();
                }
            }
        }, false);
    }

    _deselectMondu() {
        if (this._paypalEnabled()) {
            $('.mondu-card-active').removeClass('mondu-card-active');
        }
    }



    _isMonduPaymentSelected() {
        return window.MONDU_CONFIG != undefined && window.MONDU_CONFIG.selected;
    }

}