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
{foreach from=$fields_list item=field name=allfields}
                <tr class="{if $smarty.foreach.allfields.iteration % 2 eq 0}even{else}odd{/if}">
                    <td>{$field->getIndex()}</td>
                    <td class="left">{$field->getName()|escape}</td>
                    <td class="left">{$field->getPermName()}</td>
                    <td class="left">{$field->getTypeName()}</td>
                    <td>
    {if not $field|is_a:'Galette\DynamicFieldsTypes\Separator'}
        {if $field->isRequired()}{_T string="Yes"}{else}{_T string="No"}{/if}
    {/if}
                    </td>
                    <td class="center actions_row">
                        <a href="{path_for name="editDynamicField" data=["action" => {_T string="edit" domain="routes"}, "form" => $form_name, "id" => $field->getId()]}"><img src="{base_url}/{$template_subdir}images/icon-edit.png" alt="{_T string="Edit '%s' field" pattern="/%s/" replace=$field->getName()}" title="{_T string="Edit '%s' field" pattern="/%s/" replace=$field->getName()}" width="16" height="16"/></a>
                        <a href="{path_for name="dynamicTranslations" data=["text_orig" => {$field->getName()|escape}]}"><img src="{base_url}/{$template_subdir}images/icon-i18n.png" alt="{_T string="Edit '%s' field" pattern="/%s/" replace=$field->getName()}" title="{_T string="Translate '%s' field" pattern="/%s/" replace=$field->getName()}" width="16" height="16"/></a>
                        <a href="{path_for name="removeDynamicField" data=["form" => $form_name, "id" => $field->getId()]}" class="delete">
                            <img src="{base_url}/{$template_subdir}images/icon-trash.png" alt="{_T string="Delete '%s' field" pattern="/%s/" replace=$field->getName()}" title="{_T string="Delete '%s' field" pattern="/%s/" replace=$field->getName()}" width="16" height="16"/>
                        </a>
    {if $field->getIndex() eq 1}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="9" height="8"/>
    {else}
                        <a href="{path_for name="moveDynamicField" data=["form" => $form_name, "direction" => {_T string="up" domain="routes"}, "id" => $field->getId()]}">
                            <img src="{base_url}/{$template_subdir}images/icon-up.png" alt="{_T string="Move up '%s' field" pattern="/%s/" replace=$field->getName()}" title="{_T string="Move up '%s' field" pattern="/%s/" replace=$field->getName()}" width="9" height="8"/>
                        </a>
    {/if}
    {if $field->getIndex() eq $fields_list|@count}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="9" height="8"/>
    {else}
                        <a href="{path_for name="moveDynamicField" data=["form" => $form_name, "direction" => {_T string="down" domain="routes"}, "id" => $field->getId()]}">
                            <img src="{base_url}/{$template_subdir}images/icon-down.png" alt="{_T string="Move down '%s' field" pattern="/%s/" replace=$field->getName()}" title="{_T string="Move down '%s' field" pattern="/%s/" replace=$field->getName()}" width="9" height="8"/>
                        </a>
    {/if}
                    </td>
                </tr>
{foreachelse}
                <tr>
                    <td colspan="7">
                        {_T string="There is not yet any dynamic field configured for '%formname'" pattern="/%formname/" replace=$form_title}
                    </td>
                </tr>
{/foreach}
            </tbody>
        </table>
        <script type="text/javascript">
            {include file="js_removal.tpl"}
        </script>
