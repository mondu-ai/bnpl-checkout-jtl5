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
        var clearedInitially = false;
        var that = this;

        jQuery("html").on('click', '.mondu-payment-methods', function (e) {
            e.stopPropagation();
        });

        jQuery('html').on('click', '.mondu-payment-method-card', function () {
            if ($('.mondu-payment-methods', this).css('display') == 'none') {
                $(this).find('input[type="radio"]').first().prop('checked', true);
                $(this).find('input[type="radio"]').first().trigger('change');
            }
        });

        jQuery('html').on('change', '[name="Zahlungsart"]', function () {
            if (typeof ppp !== 'undefined') {
                ppp.deselectPaymentMethod();
                ppp.setPaymentMethod(null);
            }

            that.checkPPP();

            $('.active-mondu-method').removeClass('active-mondu-method');
            $(this).closest('.mondu-payment-method-card').addClass('active-mondu-method');

            $('.active-mondu-payment-method-box').removeClass('active-mondu-payment-method-box');
            $(this).closest('.mondu-payment-method-box').addClass('active-mondu-payment-method-box');
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
        jQuery(document).ready(function () {
            window.addEventListener("message", (event) => {

                if (!clearedInitially) {
                    clearedInitially = true;
                    that.clearSelection();
                }

                if (event.origin.includes('paypal') && JSON.parse(event.data)?.action == 'resizeHeightOfTheIframe') {
                    that.checkPPP();
                }

            }, false);

        });
    }

    clearSelection() {
        if (typeof ppp !== 'undefined') {

            if (ppp.getPaymentMethod() != null) {
                ppp.deselectPaymentMethod();
                ppp.setPaymentMethod(null);

                this.setMonduDefaultPaymentMethod();
                this.clearSpinner();
            }
        }

        if (typeof ppConfig === 'undefined') {
            this.clearSpinner();
        }
    }

    setMonduDefaultPaymentMethod() {
        var $paymentMethod = $('[name="Zahlungsart"]').first();

        $paymentMethod.prop('checked', true);
        $paymentMethod.trigger('change');
    }

    clearSpinner() {
        setTimeout(() => {
            $('#fieldset-payment').css({ height: '100%', overflow: 'initial' });
            $('.mondu-loader .card-body').css('display', 'block');
            $('.mondu-loader').removeClass('mondu-loader');
            $('#pp-plus').css('visibility', 'visible');
        }, 300);
    }

    checkPPP() {
        if (typeof ppp !== 'undefined') {
            if (ppp.getPaymentMethod() != null) {
                $('.active-mondu-method').removeClass('active-mondu-method');
            }
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
