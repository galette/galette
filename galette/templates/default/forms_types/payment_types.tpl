{if !isset($show_inline)}
<div class="field">
{else}
<div class="field inline">
{/if}
    <label for="{$varname}">{if isset($label)}{$label}{else}{_T string="Payment type:"}{/if}</label>
    <select name="{$varname}" id="{$varname}" class="ui search dropdown nochosen">
{if isset($empty)}
        <option value="{$empty.value}">{$empty.label}</option>
{/if}
{assign var="ptypes" value=\Galette\Repository\PaymentTypes::getAll()}
{foreach from=$ptypes item=ptype}
        <option value="{$ptype->id}"{if $current eq $ptype->id} selected="selected"{/if}>{$ptype->getName()}</option>
{/foreach}
    </select>
</div>
