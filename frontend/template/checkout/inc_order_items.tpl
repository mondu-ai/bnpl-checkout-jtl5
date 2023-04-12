extends file="{$parent_template_path}/checkout/inc_order_items.tpl"}

{block name='checkout-inc-order-items-is-not-product'}
    {capture name="parent_content"}{$smarty.block.parent}{/capture}
    {$smarty.capture.parent_content|replace: "[br]":"<br />"|replace:"[b]":"<b>"|replace:"[/b]":"</b>"|replace:"[url=": "<a target=\"_blank\" href=\""|replace:"[/url]":"</a>"|replace:"]":"\" >"}
{/block}