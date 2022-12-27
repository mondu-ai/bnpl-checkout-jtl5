class MonduCheckoutPlugin {
    init() {
        this._registerState();
        this._registerPaymentMethodEvents();

        if (!this._isMonduPaymentSelected())
            return;

        this._registerEvents();
    }

    _registerEvents() {
        const completeOrderForm = document.getElementById('complete_order');

        if (completeOrderForm != undefined) {
            completeOrderForm.addEventListener('submit', this._handleSubmit.bind(this));
        }
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

    _clearSelection() {
        if (this._paypalEnabled()) {
            if (ppp.getPaymentMethod() != null) {
                ppp.deselectPaymentMethod();
                ppp.setPaymentMethod(null);

                $('[name="Zahlungsart"]').first().closest('.mondu-card').addClass('mondu-card-active');
                $('.mondu-loader').remove();
            }
        }

        if (typeof ppConfig === 'undefined') {
            $('.mondu-loader').remove();
        }
    }

    async _handleSubmit(e) {
        e.preventDefault();
        e.stopPropagation();

        const that = this;
        const token = await this._getMonduToken();
        const removeWidgetContainer = this._removeWidgetContainer.bind(this);

        window.monduCheckout.render({
            token,
            onClose() {
                removeWidgetContainer();

                if (that.state.isSuccess) {
                    that._submitForm();
                } else {
                    window.location.href.reload();
                }
            },
            onSuccess() {
                that.state.isSuccess = true;
            }
        });
    }

    async _getMonduToken() {
        const client = new HttpRequest();

        var tokenObject = await client.get('/' + window.MONDU_CONFIG.token_url);

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

    _isMonduPaymentSelected() {
        return window.MONDU_CONFIG != undefined && window.MONDU_CONFIG.selected;
    }

    _submitForm() {
        document.getElementById('complete_order').submit();
    }

}
