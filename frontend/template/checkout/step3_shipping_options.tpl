extends file="{$parent_template_path}/checkout/step3_shipping_options.tpl"}

{block name='checkout-step3-shipping-options-legend-payment' prepend}
    <link rel="stylesheet" href="/plugins/MonduPayment/frontend/css/style.css?v=1762960876" type="text/css">
    <style>
        /* Inline Mondu styles */
        .mondu-card .mondu-payment-method {
            display: flex;
            justify-content: space-between;
        }
        .mondu-card .mondu-payment-method .mondu-highlight {
            display: flex;
        }
        .mondu-method-title {
            font-weight: 700;
            font-size: 16px;
            line-height: 20px;
            color: #000000;
        }
        .mondu-logo {
            width: 50px;
            min-width: 50px;
            margin-right: 12px;
        }
        .mondu-logo img {
            max-width: 50px;
            width: 100%;
            height: auto;
        }
    </style>
{/block}

{block name='checkout-step3-shipping-options-legend-payment' append}
    {if $paymentMethodGroupEnabled && count($monduGroups) > 0}

        <div class="mondu-payment-method-groups {if isset($payPalPlus)}mondu-paypal-plus-enabled{else}mondu-paypal-plus-disabled{/if}">
            {foreach $monduGroups as $group}
                {$paymentMethod = reset($group['payment_methods'])}
                <div class="mondu-card">
                    <div class="mondu-payment-method">
                        <div class="mondu-highlight">
                            <div class="mondu-logo">
                                {image src=$group['image'] alt=$group['title'] fluid=true class="img-sm mondu-payment-method-image"}
                            </div>
                            <div class="mondu-description">
                                <p class="mondu-method-title">{$group['title']}</p>

                                {foreach $group['payment_methods'] as $zahlungsart}
                                    {if $zahlungsart->cAnbieter == 'Mondu'}
                                    <div class="mondu-payment-method-box">
                                        {radio name="Zahlungsart"
                                                value=$zahlungsart->kZahlungsart
                                                id="payment{$zahlungsart->kZahlungsart}"
                                                required=($zahlungsart@first)}
                                        
                                            {block name='checkout-inc-payment-methods-image-title'}
                                            <span class="title">{$zahlungsart->angezeigterName|trans}</span>
                                            {/block}

                                            {if $zahlungsart->fAufpreis != 0}
                                                {block name='checkout-inc-payment-methods-badge'}
                                                    <strong class="checkout-payment-method-badge">
                                                    {if $zahlungsart->cPreisLocalized[0] == '+'}
                                                    {$zahlungsart->cPreisLocalized}
                                                    {else}
                                                    + {$zahlungsart->cPreisLocalized}
                                                    {/if}
                                                    </strong>
                                                {/block}
                                            {/if}
                                        {/radio}
                                    </div>
                                    {/if}
                                {/foreach}
                            </div>
                        </div>
                    </div>
                </div>
            {/foreach}

            {foreach $Zahlungsarten as $zahlungsart}
                {if $zahlungsart->cAnbieter == 'Mondu'}
                    {$Zahlungsarten[$zahlungsart@key] = null}
                    {$Zahlungsarten = $Zahlungsarten|array_filter}
                {/if}
            {/foreach}
        </div>
    {/if} 
{/block}

 
