<div class="grouped fields">
    <div class="field required">
{include
    file="forms_types/textarea.tpl"
    name=$entry->field_id
    id=$entry->field_id
    value=$member->address|escape
    required=$entry->required
    disabled=$entry->disabled
    label=$entry->label
    notag=true
    elt_class="large"
}
    </div>
</div>
