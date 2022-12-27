 extends file="{$parent_template_path}/checkout/step3_shipping_options.tpl"}

{block name='checkout-step3-shipping-options-legend-payment' append}
    {if $paymentMethodGroupEnabled}

        <div class="mondu-payment-method-groups {if isset($payPalPlus)}mondu-paypal-plus-enabled{else}mondu-paypal-plus-disabled{/if}">
            {foreach $monduGroups as $group}
                {$paymentMethod = reset($group['payment_methods'])}
                <div class="mondu-card">
                    <div class="mondu-payment-method">
                        <div class="mondu-highlight">
                            <div class="mondu-logo">
                                {image src=$paymentMethod->cBild alt=$paymentMethod->angezeigterName|trans fluid=true class="img-sm mondu-payment-method-image" width="50"}
                            </div>
                            <div class="mondu-description">
                                <p class="mondu-method-title">{$group['title']}</p>
                                <p class="mondu-method-description">{$group['description']|replace:"[url=": "<a target=\"_blank\" href=\""|replace:"[/url]":"</a>"|replace:"]":"\" >"}</p>

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

                                            {if $zahlungsart->cHinweisText|has_trans}
                                                {block name='checkout-inc-payment-methods-note'}
                                                    <br />
                                                    <span class="checkout-payment-method-note">
                                                        <small>{$zahlungsart->cHinweisText|trans|replace:"[url=": "<a target=\"_blank\" href=\""|replace:"[/url]":"</a>"|replace:"]":"\" >"}</small>
                                                    </span>
                                                {/block}
                                            {/if}
                                            <div class="mondu-benefits-text">
                                                <ul>
                                                {foreach explode("|", $zahlungsart->monduBenefits) as $benefit}
                                                    <li>{$benefit}</li>
                                                {/foreach}
                                                </ul>
                                            </div>
                                        {/radio}
                                    </div>
                                    {/if}
                                {/foreach}
                            </div>
                        </div>
                        <div class="mondu-checkmark-box">
                            <img class="mondu-checkmark" src="{$monduFrontendUrl}img/checkmark.png" />
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

 
