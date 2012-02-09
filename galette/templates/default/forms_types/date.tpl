{extends file="./input.tpl"}

{block name="component"}
    {assign var="type" value="text"}
    {assign var="example" value={_T string="(yyyy-mm-dd format)"}}
    {$smarty.block.parent}
{/block}
