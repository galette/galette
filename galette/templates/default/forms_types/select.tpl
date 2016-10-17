{extends file="forms_types/input.tpl"}

{block name="element"}
    <select
        name="{$name}"
        id="{$id}"
        {if isset($required) and $required == true} required="required"{/if}
        >
        {foreach item=label from=$values key=value}
        <option value="{$value}">{$label}</option>
        {/foreach}
    </select>
{/block}
