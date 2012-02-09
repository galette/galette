{include
    file="./input.tpl"
    type="password"
    name=$entry->field_id
    id=$entry->field_id
    required=$entry->required
    label=$entry->label
    autocomplete="off"
    example={_T string="(at least %i characters)" pattern="/%i/" replace=6}
    value=null
}
{include
    file="./input.tpl"
    type="password"
    name="mdp_adh2"
    id="mdp_adh2"
    required=$entry->required
    label={_T string="Password confirmation:"}
    autocomplete="off"
    example={_T string="(Confirmation)"}
    elt_class="labelalign"
    value=null
}
