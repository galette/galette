{extends file="./input.tpl"}

{block name="element"}
    <select
        name="{$name}"
        id="{$id}"
        >
        {foreach item=label from=$values key=value}
        <option value="{$value}">{$label}</option>
        {/foreach}
    </select>
{/block}
