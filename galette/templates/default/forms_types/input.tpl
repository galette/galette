{block component}
{if not isset($notag)}
<div{if isset($component_id)} id="{$component_id}"{/if}{if isset($component_class)} class="{$component_class}"{/if}>
{/if}
    {block name="label"}
        <label for="{$id}"{if isset($title)} title="{$title}"{/if}{if isset($tip) or isset($labelclass)} class="{if isset($labelclass)}{$labelclass}{/if}"{/if}>
        {if $masschange}
            {* Add a checkbox for fields to change on mass edition *}
            <input type="checkbox" name="mass_{$entry->field_id}" class="mass_checkbox"/>
        {/if}
            {$label}
        </label>
    {/block}
    {block name="element"}<input
        type="{$type}"
        name="{$name}"
        id="{$id}"
        value="{if null !== $value}{$value}{/if}"
        {if isset($required) and $required == true} required="required"{/if}
        {if isset($readonly) and $readonly == true} readonly="readonly"{/if}
        {if isset($disabled) and $disabled == true} disabled="disabled"{/if}
        {if isset($title)} title="{$title}"{/if}
        {if isset($maxlength)} maxlength="{$maxlength}"{/if}
        {if isset($elt_class)} class="{$elt_class}"{/if}
        {if isset($autocomplete)} autocomplete="{$autocomplete}"{/if}
        {if isset($size)} size="{$size}"{/if}
        {if isset($checked) and $checked eq true} checked="checked"{/if}
        />
    {/block}
    {if isset($tip)}<i class="circular inverted primary small icon info tooltip" data-html="{$tip}"></i>{/if}
    {if isset($example)}<span class="exemple">{$example}</span>{/if}
{if not isset($notag)}
</div>
{/if}
{/block}
