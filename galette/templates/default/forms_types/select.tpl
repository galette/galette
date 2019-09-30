{extends file="forms_types/input.tpl"}

{block name="component"}
    {if isset($masschange) and $masschange == true}
        {assign var="component_class" value="inline field{if $entry->required} required{/if}"}
    {else}
        {assign var="component_class" value="field{if $entry->required} required{/if}"}
    {/if}
    {$smarty.block.parent}
{/block}

{block name="element"}
    <select
        name="{$name}"
        id="{$id}"
        {if isset($required) and $required == true} required="required"{/if}
        {if isset($disabled) and $disabled == true} disabled="disabled"{/if}
        class="ui search dropdown nochosen"
        >
        {foreach item=label from=$values key=value}
        <option value="{$value}">{$label}</option>
        {/foreach}
    </select>
{/block}
