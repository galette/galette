{extends file="forms_types/input.tpl"}
{assign var="inline" value=true}

{block name="component"}
    {assign var="type" value="checkbox"}
    {if isset($masschange) and $masschange == true}
        {assign var="component_class" value="inline field"}
    {else}
        {assign var="component_class" value="field"}
    {/if}
    {$smarty.block.parent}
{/block}
