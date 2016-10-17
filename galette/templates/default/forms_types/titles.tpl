{extends file="forms_types/select.tpl"}

{block name="element"}
    {assign var="values" value=""}
    <select name="titre_adh" id="titre_adh"{if isset($disabled.titre_adh)} {$disabled.titre_adh}{/if}{if isset($required) and $required == true} required="required"{/if}>
        <option value="{if isset($required.titre_adh) and $required.titre_adh eq 1}-1{/if}">{_T string="Not supplied"}</option>
{foreach item=title from=$titles_list}
        <option value="{$title->id}"{if $member->title neq null and $member->title->id eq $title->id} selected="selected"{/if}>{$title->long}</option>
{/foreach}
    </select>
{/block}
