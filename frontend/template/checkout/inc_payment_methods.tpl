extends file="{$parent_template_path}/checkout/inc_payment_methods.tpl"}

{block name='checkout-inc-payment-methods-note'}
  {if $zahlungsart->cAnbieter == 'Mondu'}
    <span class="checkout-payment-method-note">
      <small>{$zahlungsart->cHinweisText|trans|replace: "[br]":"<br />"|replace:"[b]":"<b>"|replace:"[/b]":"</b>"|replace:"[url=": "<a target=\"_blank\" href=\""|replace:"[/url]":"</a>"|replace:"]":"\" >"}</small>
    </span>
  {else}
    {$zahlungsart->cHinweisText|trans}
  {/if}
{/block}

{block name='checkout-inc-payment-methods-image-title'}
  {if $zahlungsart->cAnbieter == 'Mondu'}
    {parent}

    {if $paymentMethodNameVisible}
      <span class="content">
        <span class="title">{$zahlungsart->angezeigterName|trans}</span>
      </span>
    {/if}
  {else}
    {parent}
  {/if}
{/block}