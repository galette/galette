{extends file="forms_types/input.tpl"}

{block name="component"}
    {assign var="type" value="text"}
    {if isset($masschange) and $masschange == true}
        {assign var="component_class" value="inline field{if $entry->required} required{/if}"}
    {else}
        {assign var="component_class" value="field{if $entry->required} required{/if}"}
    {/if}
    {$smarty.block.parent}
{/block}
