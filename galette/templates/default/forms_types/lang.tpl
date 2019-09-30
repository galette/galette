{extends file="forms_types/select.tpl"}

{block name="element"}
    <select name="pref_lang" id="pref_lang" class="ui dropdown nochosen lang"{if isset($disabled) and $disabled == true} disabled="disabled"{/if}{if isset($required.pref_lang) and $required.pref_lang eq 1} required="required"{/if}>
        {foreach item=langue from=$languages}
            <option value="{$langue->getID()}"{if $member->language eq $langue->getID()} selected="selected"{/if}>{$langue->getName()}</option>
        {/foreach}
    </select>
{/block}
