            {foreach item=fieldset from=$fieldsets}
            <fieldset class="galette_form">
                <legend>{_T string=$fieldset->label}</legend>
                <div>
                {if !isset($masschange) && !$self_adh and $fieldset@first}
                    {include file="forms_types/picture.tpl"}
                {/if}
                {foreach item=entry from=$fieldset->elements}
                    {assign var="template" value="text.tpl"}
                    {assign var="title" value=null}
                    {assign var="tip" value=null}
                    {assign var="size" value=null}
                    {assign var="propname" value=$entry->propname}
                    {assign var="value" value=null}
                    {assign var="checked" value=null}
                    {assign var="example" value=null}

                    {if $entry->type eq constant('Galette\Entity\FieldsConfig::TYPE_BOOL')}
                        {assign var="template" value="checkbox.tpl"}
                        {assign var="value" value="1"}
                    {/if}
                    {if $entry->field_id eq 'titre_adh'}
                        {assign var="template" value="titles.tpl"}
                        {assign var="value" value=$member->title}
                    {/if}
                    {if $entry->field_id eq 'pref_lang'}
                        {assign var="template" value="lang.tpl"}
                    {/if}
                    {if $entry->field_id eq 'sexe_adh'}
                        {assign var="template" value="gender.tpl"}
                    {/if}
                    {if $entry->field_id eq 'societe_adh'}
                        {assign var="template" value="company.tpl"}
                    {/if}
                    {if $entry->field_id|strpos:'date_' === 0 or $entry->field_id eq 'ddn_adh'}
                        {assign var="template" value="date.tpl"}
                    {/if}
                    {if $entry->field_id eq 'adresse_adh'}
                        {assign var="template" value="address.tpl"}
                    {/if}
                    {if $entry->field_id eq 'mdp_adh'}
                        {if !$self_adh}
                            {assign var="template" value="password.tpl"}
                        {else}
                            {assign var="template" value="captcha.tpl"}
                        {/if}
                    {/if}
                    {if $entry->field_id eq 'info_adh'
                        or $entry->field_id eq 'info_public_adh'}
                        {assign var="template" value="textarea.tpl"}
                        {if $entry->field_id eq 'info_adh'}
                            {assign var="example" value={_T string="This comment is only displayed for admins and staff members."}}
                        {else}
                            {if $login->isAdmin() or $login->isStaff()}
                                {assign var="example" value={_T string="This comment is reserved to the member."}}
                            {/if}
                        {/if}
                    {/if}
                    {if $entry->field_id eq 'activite_adh'}
                        {assign var="template" value="account.tpl"}
                    {/if}
                    {if $entry->field_id eq 'id_statut'}
                        {assign var="template" value="status.tpl"}
                    {/if}

                    {if $entry->field_id eq 'gpgid'}
                        {assign var="size" value="8"}
                    {/if}
                    {if $entry->field_id eq 'email_adh'}
                        {assign var="size" value="30"}
                    {/if}
                    {if $entry->field_id eq 'fingerprint'}
                        {assign var="size" value="40"}
                    {/if}
                    {if $entry->field_id eq 'bool_display_info'}
                        {assign var="title" value={_T string="Do member want to appear publically?"}}
                        {assign var="tip" value={_T string="If you check this box (and if you are up to date with your contributions), your full name and other information will be publically visible on the members list.<br/>If you've uploaded a photo, it will be displayed on the trombinoscope page.<br/>Note that administrators can disabled public pages, this setting will have no effect in that case."}}
                        {assign var="checked" value=$member->appearsInMembersList()}
                    {/if}
                    {if $entry->field_id eq 'login_adh'}
                        {assign var="example" value={_T string="(at least %i characters)" pattern="/%i/" replace=2}}
                    {/if}

                    {if $entry->field_id eq 'bool_admin_adh'}
                        {assign var="checked" value=$member->isAdmin()}
                    {/if}
                    {if $entry->field_id eq 'bool_exempt_adh'}
                        {assign var="checked" value=$member->isDueFree()}
                    {/if}
                    {if $entry->field_id eq 'parent_id'}
                        {assign var="value" value=$member->parent->id}
                    {/if}
                    {if $entry->field_id eq 'activite_adh'}
                        {assign var="value" value=$member->isActive()}
                    {/if}

                    {* If value has not been set, take the generic value *}
                    {if !$value and $propname != 'password'}
                        {assign var="value" value=$member->$propname}
                    {/if}

                    {if !isset($masschange)}
                       {assign var="masschange" value=false}
                    {/if}

                    {include
                        file="forms_types/$template"
                        name=$entry->field_id
                        id=$entry->field_id
                        value=$value
                        required=$entry->required
                        readonly=$entry->readonly
                        disabled=$entry->disabled
                        label=$entry->label
                        title=$title
                        size=$size
                        tip=$tip
                        compile_id="input_{$entry->field_id}"
                        checked=$checked
                        masschange=$masschange
                    }

                {/foreach}
                {if isset($groups) and $groups|@count != 0 and $fieldset@last and (!isset($masschange) or $masschange == false)}
                    {include file="forms_types/groups.tpl"}
                {/if}
                </div>
            </fieldset>
            {/foreach}
