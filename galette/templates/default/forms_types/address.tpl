<p>
{include
    file="forms_types/text.tpl"
    name=$entry->field_id
    id=$entry->field_id
    value=$member->address|escape
    required=$entry->required
    disabled=$entry->disabled
    label=$entry->label
    notag=true
    elt_class="large"
}
{if isset($fieldset->elements['adresse2_adh'])}
<br/>
{assign var="address2" value=$fieldset->elements['adresse2_adh']}
{include
    file="forms_types/text.tpl"
    name=$address2->field_id
    id=$address2->field_id
    value=$member->address_continuation|escape
    required=$address2->required
    disabled=$address2->disabled
    label=$address2->label
    notag=true
    elt_class="large"
}
{/if}
</p>
