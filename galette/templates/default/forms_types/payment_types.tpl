{if !isset($classname)}
    {assign var="classname" value="bline"}
{/if}
{if !isset($show_inline)}
<p>
{/if}
    <label class="{$classname}" for="{$varname}">{if isset($label)}{$label}{else}{_T string="Payment type:"}{/if}</label>
    <select name="{$varname}" id="{$varname}">
{if isset($empty)}
        <option value="{$empty.value}">{$empty.label}</option>
{/if}
{assign var="ptypes" value=\Galette\Repository\PaymentTypes::getAll()}
{foreach from=$ptypes item=ptype}
        <option value="{$ptype->id}"{if $current eq $ptype->id} selected="selected"{/if}>{$ptype->getName()}</option>
{/foreach}
    </select>
{if !isset($show_inline)}
</p>
{/if}
