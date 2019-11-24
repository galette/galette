{extends file="forms_types/input.tpl"}
{assign var="inline" value=true}

{block name="element"}
    <select
        name="{$name}"
        id="{$id}"
        {if isset($required) and $required == true} required="required"{/if}
        {if isset($disabled) and $disabled == true} disabled="disabled"{/if}
        >
        {foreach item=label from=$values key=value}
        <option value="{$value}">{$label}</option>
        {/foreach}
    </select>
{/block}
