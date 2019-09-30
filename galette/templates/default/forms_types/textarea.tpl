{extends file="forms_types/input.tpl"}

{block name="component"}
    {assign var="component_class" value="field"}
    {$smarty.block.parent}
{/block}

{block name="label"}
    {*assign var="labelclass" value="vtop"*}
    {$smarty.block.parent}
{/block}

{block name="element"}
    <textarea
        name="{$name}"
        id="{$id}"
        cols="50"
        rows="6"
        {if isset($required) and $required == true} required="required"{/if}
        {if isset($disabled) and $disabled == true} disabled="disabled"{/if}
        {if isset($title)} title="{$title}"{/if}
        {if isset($maxlength)} maxlength="{$maxlength}"{/if}
        {if isset($elt_class)} class="{$elt_class}"{/if}
        {if isset($autocomplete)} autocomplete="{$autocomplete}"{/if}
        {if isset($size)} size="{$size}"{/if}
        >{if null !== $value}{$value}{/if}</textarea><br/>
{/block}
