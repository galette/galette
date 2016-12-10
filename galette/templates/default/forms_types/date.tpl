{extends file="forms_types/input.tpl"}

{block name="component"}
    {assign var="type" value="text"}
    {assign var="example" value={_T string="(yyyy-mm-dd format)"}}
    {if $id eq 'ddn_adh'}
        {assign var="example" value={_T string="(yyyy-mm-dd format)"}|cat:"<span id=\"member_age\">{$member->getAge()}</span>"}
    {/if}
    {$smarty.block.parent}
{/block}
