extends file="{$parent_template_path}/checkout/inc_payment_methods.tpl"}

{block name='checkout-inc-payment-methods-image-title'}
  {if $zahlungsart->cAnbieter == 'Mondu'}
    {if $zahlungsart->cBild}
      {image src=$zahlungsart->cBild alt=$zahlungsart->angezeigterName|trans fluid=true class="img-sm mondu-small"}
    {/if}
    <span class="content">
      <span class="title">{$zahlungsart->angezeigterName|trans}</span>
    </span>
  {else}
    {parent}
  {/if}
{/block}

{block name='checkout-inc-payment-methods-note'}
  {if $zahlungsart->cAnbieter == 'Mondu'}
    <span class="checkout-payment-method-note">
      <small>{$zahlungsart->cHinweisText|trans|replace: "[br]":"<br />"|replace:"[b]":"<b>"|replace:"[/b]":"</b>"|replace:"[url=": "<a target=\"_blank\" href=\""|replace:"[/url]":"</a>"|replace:"]":"\" >"}</small>
    </span>
  {else}
    {$zahlungsart->cHinweisText|trans}
  {/if}
{/block}