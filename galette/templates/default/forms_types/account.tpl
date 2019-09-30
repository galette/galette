{extends file="forms_types/select.tpl"}

{block name="element"}
    <select name="activite_adh" id="activite_adh" class="ui dropdown nochosen"{if isset($disabled) and $disabled == true} disabled="disabled"{/if}{if isset($required.activite_adh) and $required.activite_adh eq 1} required="required"{/if}>
        <option value="1" {if $member->isActive() eq 1}selected="selected"{/if}>{_T string="Active"}</option>
        <option value="0" {if $member->isActive() eq 0}selected="selected"{/if}>{_T string="Inactive"}</option>
    </select>
{/block}
