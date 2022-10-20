 extends file="{$parent_template_path}/checkout/inc_payment_methods.tpl"}
 {block name='checkout-inc-payment-methods' prepend}

    {if $paymentMethodGroupEnabled}
        {foreach $monduGroups as $group}
          {$paymentMethod = reset($group['payment_methods'])}
                <div style="width: 100%">
                  <div class="mondu-payment-method-card">
                    <div class="mondu-payment-method-card-body">
                        <div style="flex: 3">
                          <p class="mondu-payment-method-title">{$group['title']}</p>
                          <p class="mondu-payment-method-description">{$group['description']}</p>
                        </div>
                        <div style="flex: 1">
                          {image src=$paymentMethod->cBild alt=$paymentMethod->angezeigterName|trans fluid=true class="img-sm mondu-payment-method-image" width="90"}
                        </div>
                    </div>
                  

                      <div class="mondu-payment-methods" style="display: none">
                        {foreach $group['payment_methods'] as $zahlungsart}
                          {if $zahlungsart->cAnbieter == 'Mondu'}
                            <div class="mondu-payment-method-box">
                              {radio name="Zahlungsart"
                                        value=$zahlungsart->kZahlungsart
                                        id="payment{$zahlungsart->kZahlungsart}"
                                        checked=($AktiveZahlungsart === $zahlungsart->kZahlungsart || $Zahlungsarten|@count === 1)
                                        required=($zahlungsart@first)
                              }
                                  {block name='checkout-inc-payment-methods-image-title'}
                                    <span class="title">{$zahlungsart->angezeigterName|trans}</span>
                                  {/block}

                                  {if $zahlungsart->fAufpreis != 0}
                                      {block name='checkout-inc-payment-methods-badge'}
                                          <strong class="checkout-payment-method-badge">
                                          {if $zahlungsart->cGebuehrname|has_trans}
                                              <span>{$zahlungsart->cGebuehrname|trans} </span>
                                          {/if}
                                              {$zahlungsart->cPreisLocalized}
                                          </strong>
                                      {/block}
                                  {/if}

                                  {if $zahlungsart->cHinweisText|has_trans}
                                        {block name='checkout-inc-payment-methods-note'}
                                            <br />
                                            <span class="checkout-payment-method-note">
                                                <small>{$zahlungsart->cHinweisText|trans}</small>
                                            </span>
                                        {/block}
                                    {/if}
                                {/radio}
                            </div>
                          {/if}
                        {/foreach}
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
    {/if}
 {/block}

 