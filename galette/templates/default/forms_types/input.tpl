{block component}
{if not isset($notag)}
<p{if isset($component_id)} id="{$component_id}"{/if}{if isset($component_class)} class="{$component_class}"{/if}>
{/if}
    {block name="label"}<label for="{$id}"{if isset($title)} title="{$title}"{/if}{if isset($tip)} class="tooltip"{/if}>{$label}</label>{/block}
    {if isset($tip)}<span class="tip">{$tip}</span>{/if}
    {block name="element"}<input
        type="{$type}"
        name="{$name}"
        id="{$id}"
        value="{if null !== $value}{$value}{/if}"
        {if isset($required) and $required == true} required="required"{/if}
        {if isset($title)} title="{$title}"{/if}
        {if isset($maxlength)} maxlength="{$maxlength}"{/if}
        {if isset($disabled.$id)} disabled="disabled"{/if}
        {if isset($elt_class)} class="{$elt_class}"{/if}
        {if isset($autocomplete)} autocomplete="{$autocomplete}"{/if}
        {if isset($size)} size="{$size}"{/if}
        {if isset($checked) and $checked eq true} checked="checked"{/if}
        />
    {/block}
    {if isset($example)}<span class="exemple">{$example}</span>{/if}
{if not isset($notag)}
</p>
{/if}
{/block}
