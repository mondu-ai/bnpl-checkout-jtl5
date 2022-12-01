extends file="{$parent_template_path}/checkout/step5_confirmation.tpl"}

{block name='checkout-step5-confirmation-payment-method'}
  <p><strong class="title">{lang key='paymentOptions'}</strong></p>
  <p>{$smarty.session.Zahlungsart->angezeigterName|trans}</p>
  {if isset($smarty.session.Zahlungsart->cHinweisText) && !empty($smarty.session.Zahlungsart->cHinweisText)}{* this should be localized *}
      <p class="small text-muted-util">{$smarty.session.Zahlungsart->cHinweisText|replace:"[url=": "<a target=\"_blank\" href=\""|replace:"[/url]":"</a>"|replace:"]":"\" >"}</p>
  {/if}
{/block}