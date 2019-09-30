{extends file="forms_types/select.tpl"}

{block name="element"}
    <select name="id_statut" id="id_statut" class="ui dropdown nochosen"{if isset($disabled) and $disabled == true} disabled="disabled"{/if}{if isset($required.id_statut) and $required.id_statut eq 1} required="required"{/if}>
        {html_options options=$statuts selected=$member->status}
    </select>
{/block}
