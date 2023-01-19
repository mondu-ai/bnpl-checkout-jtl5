class MonduCheckoutPlugin {
    init() {
        this._registerState();
        this._registerPaymentMethodEvents();

        if (this._isMonduConfigPresent()) {
            this._registerAuthorizationFlow();
        }
    }

    _registerAuthorizationFlow() {
        function submitForm() {
            document.getElementsByClassName('checkout-shipping-form')[0].submit();
        }

        function submitFormCallback(e) {
            const value = jQuery('input[name="Zahlungsart"]:checked').val(); //110
            const monduPaymentMethods = window.MONDU_CONFIG.payment_methods;
            const isMondu = Object.keys(monduPaymentMethods).includes(value);

            if(!isMondu) return;
            
            this._handleSubmit.call(this, e, submitForm, monduPaymentMethods[value])
        }

        const completeOrderForm = document.getElementsByClassName('checkout-shipping-form')[0];

        if (!completeOrderForm) return;

        completeOrderForm.addEventListener('submit', submitFormCallback.bind(this));
    }

    _registerState() {
        this.state = {
            isSuccess: false
        };
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

        jQuery('html').on('submit', '.checkout-shipping-form', function (e) {
            if (!submittedForm && that._paypalEnabled()) {
                e.preventDefault();

                var pppMethod = ppp.getPaymentMethod();

                if (pppMethod == null || $('#pp-plus').length == 0) {
                    $('#pp-plus').remove();
                    submittedForm = true;
                    $(this).submit();
                } else {
                    $('.mondu-payment-method-groups').remove();
                    ppp.doContinue();
                }
            }
        });

        window.addEventListener("message", (event) => {
            var isPaypal = event.origin.includes('paypal');

            if (isPaypal) {
                console.log(event)

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

    async _handleSubmit(e, callback, paymentMethod = null) {
        e.preventDefault();
        e.stopPropagation();
        const that = this;
        const token = await this._getMonduToken(paymentMethod);
        const removeWidgetContainer = this._removeWidgetContainer.bind(this);

        window.monduCheckout.render({
            token,
            onClose() {
                removeWidgetContainer();

                if (that.state.isSuccess) {
                    callback()
                } else {
                    window.location.href.reload();
                }
            },
            onSuccess() {
                that.state.isSuccess = true;
            }
        });
    }

    async _getMonduToken(paymentMethod) {
        const client = new HttpRequest();
        const tokenUrl = window.MONDU_CONFIG.token_url;
        var tokenObject = await client.post('/' + tokenUrl, { payment_method: paymentMethod });

        if (!tokenObject.data.error) {
            return tokenObject.data.token;
        } else {
            return null;
        }
    }

    _removeWidgetContainer() {
        const widgetContainer = document.getElementById("mondu-checkout-widget");

        if (widgetContainer) {
            widgetContainer.remove();
            window.monduCheckout.destroy();

            window.location.reload();
        }
    }

    _isMonduConfigPresent() {
        return !!window.MONDU_CONFIG;
    }
}
