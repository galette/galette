{extends file="forms_types/select.tpl"}

{block name="element"}
    <select name="pref_lang" id="pref_lang" class="lang"{if isset($disabled.pref_lang)} {$disabled.pref_lang}{/if}{if isset($required.pref_lang) and $required.pref_lang eq 1} required{/if}>
        {foreach item=langue from=$languages}
            <option value="{$langue->getID()}"{if $member->language eq $langue->getID()} selected="selected"{/if} style="background-image:url({base_url}/{$langue->getFlag()});">{$langue->getName()|ucfirst}</option>
        {/foreach}
    </select>
{/block}
