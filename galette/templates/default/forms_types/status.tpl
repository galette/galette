{extends file="./select.tpl"}

{block name="element"}
    <select name="id_statut" id="id_statut"{if isset($disabled.id_statut)} {$disabled.id_statut}{/if}{if isset($required.id_statut) and $required.id_statut eq 1} required{/if}>
        {html_options options=$statuts selected=$member->status}
    </select>
{/block}
