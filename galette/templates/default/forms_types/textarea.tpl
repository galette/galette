{extends file="forms_types/input.tpl"}

{block name="element"}
    <textarea
        name="{$name}"
        id="{$id}"
        cols="50"
        rows="6"
        {if isset($required) and $required == true} required="required"{/if}
        {if isset($title)} title="{$title}"{/if}
        {if isset($maxlength)} maxlength="{$maxlength}"{/if}
        {if isset($disabled.$id)} disabled="disabled"{/if}
        {if isset($elt_class)} class="{$elt_class}"{/if}
        {if isset($autocomplete)} autocomplete="{$autocomplete}"{/if}
        {if isset($size)} size="{$size}"{/if}
        >{if null !== $value}{$value}{/if}</textarea><br/>
{/block}
