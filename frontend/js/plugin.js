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
        jQuery('html').on('click', '.mondu-payment-method-card-body', function () {

            var siblingMonduPaymentMethods = $(this).siblings('.mondu-payment-methods');
            siblingMonduPaymentMethods.slideToggle();
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