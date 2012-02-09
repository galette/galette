{extends file="./input.tpl"}

{block name="component"}
    {assign var="type" value="hidden"}
    {assign var="notag" value="true"}
    {assign var="title" value=""}
    {assign var="required" value=""}
    {assign var="disabled" value=""}
    {$smarty.block.parent}
{/block}
{block name="label"}{/block}
