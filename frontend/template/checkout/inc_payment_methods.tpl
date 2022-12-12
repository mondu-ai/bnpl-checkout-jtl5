extends file="{$parent_template_path}/checkout/inc_payment_methods.tpl"}

{block name='checkout-inc-payment-methods-note'}
  <span class="checkout-payment-method-note">
      <small>{$zahlungsart->cHinweisText|trans|replace:"[url=": "<a target=\"_blank\" href=\""|replace:"[/url]":"</a>"|replace:"]":"\" >"}</small>
  </span>
{/block}