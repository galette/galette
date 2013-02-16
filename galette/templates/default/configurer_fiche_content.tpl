        <table class="listing">
            <thead>
                <tr>
                    <th class="id_row">#</th>
                    <th>{_T string="Name"}</th>
                    <th class="date_row">{_T string="Visibility"}</th>
                    <th class="date_row">{_T string="Type"}</th>
                    <th class="date_row">{_T string="Required"}</th>
                    <th>{_T string="Actions"}</th>
                </tr>
            </thead>
            <tbody>
{foreach from=$dyn_fields item=field name=alldyn}
                <tr class="{if $smarty.foreach.alldyn.iteration % 2 eq 0}even{else}odd{/if}">
                    <td>{$field.index}</td>
                    <td class="left">{$field.name|escape}</td>
                    <td class="left">{$field.perm}</td>
                    <td class="left">{$field.type_name}</td>
                    <td>
    {if $field.type neq constant('Galette\Entity\DynamicFields::SEPARATOR')}
        {if $field.required}{_T string="Yes"}{else}{_T string="No"}{/if}
    {/if}
                    </td>
                    <td class="center actions_row">
    {if isset($field.no_data)}
                        <img src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="16" height="16"/>
                        <img src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="16" height="16"/>
    {else}
                        <a href="editer_champ.php?form={$form_name}&amp;id={$field.id}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="Edit '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Edit '%s' field" pattern="/%s/" replace=$field.name}" width="16" height="16"/></a>
                        <a href="traduire_libelles.php?text_orig={$field.name|escape}"><img src="{$template_subdir}images/icon-i18n.png" alt="{_T string="Edit '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Translate '%s' field" pattern="/%s/" replace=$field.name}" width="16" height="16"/></a>
    {/if}
                        <a onclick="return confirm('{_T string="Do you really want to delete this field ?\n All associated data will be deleted as well."|escape:"javascript"}')" href="configurer_fiches.php?form={$form_name}&amp;del={$field.id}">
                            <img src="{$template_subdir}images/icon-trash.png" alt="{_T string="Delete '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Delete '%s' field" pattern="/%s/" replace=$field.name}" width="16" height="16"/>
                        </a>
    {if $field.index eq 1}
                        <img src="{$template_subdir}images/icon-empty.png" alt="" width="9" height="8"/>
    {else}
                        <a href="configurer_fiches.php?form={$form_name}&amp;up={$field.id}">
                            <img src="{$template_subdir}images/icon-up.png" alt="{_T string="Send up '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Send up '%s' field" pattern="/%s/" replace=$field.name}" width="9" height="8"/>
                        </a>
    {/if}
    {if $field.index eq $dyn_fields|@count}
                        <img src="{$template_subdir}images/icon-empty.png" alt="" width="9" height="8"/>
    {else}
                        <a href="configurer_fiches.php?form={$form_name}&amp;down={$field.id}">
                            <img src="{$template_subdir}images/icon-down.png" alt="{_T string="Send down '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Send down '%s' field" pattern="/%s/" replace=$field.name}" width="9" height="8"/>
                        </a>
    {/if}
                    </td>
                </tr>
{foreachelse}
                <tr>
                    <td colspan="7">
                        {_T string="There is not yet any dynamic field configured for '%formname'" pattern="/%formname/" replace=$form_name}
                    </td>
                </tr>
{/foreach}
            </tbody>
        </table>
