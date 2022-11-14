class MonduCheckoutPlugin {
    init() {
        this._registerPaymentMethodEvents();

        if (!this._isMonduPaymentSelected())
            return;

        this._registerEvents();
        this._registerState();
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

    _registerPaymentMethodEvents() {
        var submittedForm = false;

        jQuery('html').on('click', '.mondu-payment-method-card-body', function () {
            $(this).parent().find('input[type="radio"]').first().prop('checked', true);
            $(this).parent().find('input[type="radio"]').first().trigger('change');

            var siblingMonduPaymentMethods = $(this).siblings('.mondu-payment-methods');
            siblingMonduPaymentMethods.slideToggle();
        });

        jQuery('html').on('change', '[name="Zahlungsart"]', function () {
            if (typeof ppp !== 'undefined') {
                ppp.deselectPaymentMethod();
                ppp.setPaymentMethod(null);
            }
        });

        jQuery('html').on('submit', 'form', function (e) {
            if (!submittedForm && typeof ppp !== 'undefined') {
                e.preventDefault();

                var pppMethod = ppp.getPaymentMethod();

                if (pppMethod == null) {
                    $('#pp-plus').remove();
                    submittedForm = true;
                    $(this).submit();
                } else {
                    $('.mondu-payment-method-card').remove();
                    ppp.doContinue();
                }
            }
        });

        jQuery('html').on('click', '.paymentMethodRow', function () {
            $('[name="Zahlungsart"]').filter(':checked').prop('checked', false);
        });

        $('.ppp-container iframe').on('load', function () {
            $('.ppp-container frame').contents().find('.paymentMethodRow').click(function () {
                $('[name="Zahlungsart"]').filter(':checked').prop('checked', false);
            });
        });
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

        console.log('submitted!')
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