        <table class="listing ui celled table">
            <thead>
                <tr>
                    <th class="id_row">#</th>
                    <th>{_T string="Name"}</th>
                    <th>{_T string="Permissions"}</th>
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
    {if not $field|is_a:'Galette\DynamicFields\Separator'}
        {if $field->isRequired()}{_T string="Yes"}{else}{_T string="No"}{/if}
    {/if}
                    </td>
                    <td class="center actions_row">
                        <a
                            href="{path_for name="editDynamicField" data=["form_name" => $form_name, "id" => $field->getId()]}"
                            class="tooltip action"
                        >
                            <i class="ui user edit blue icon" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="Edit '%s' field" pattern="/%s/" replace=$field->getName()|escape}</span>
                        </a>
                        <a
                            href="{path_for name="dynamicTranslations" data=["text_orig" => {$field->getName(false)|escape}]}"
                            class="tooltip"
                        >
                            <i class="ui language icon" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="Translate '%s' field" pattern="/%s/" replace=$field->getName()|escape}</span>
                        </a>
                        <a
                            href="{path_for name="removeDynamicField" data=["form_name" => $form_name, "id" => $field->getId()]}"
                            class="delete tooltip"
                        >
                            <i class="ui trash red icon" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="Delete '%s' field" pattern="/%s/" replace=$field->getName()|escape}</span>
                        </a>
    {if $field->getIndex() eq 1}
                        <i class="ui icon">&nbsp;</i>
    {else}
                        <a
                            href="{path_for name="moveDynamicField" data=["form_name" => $form_name, "direction" => "up", "id" => $field->getId()]}"
                            class="tooltip action"
                        >
                            <i class="ui caret up icon"></i>
                            <span class="sr-only">{_T string="Move up '%s' field" pattern="/%s/" replace=$field->getName()|escape}</span>
                        </a>
    {/if}
    {if $field->getIndex() eq $fields_list|@count}
                        <i class="ui icon">&nbsp;</i>
    {else}
                        <a
                            href="{path_for name="moveDynamicField" data=["form_name" => $form_name, "direction" => "down", "id" => $field->getId()]}"
                            class="tooltip"
                        >
                            <i class="ui caret down icon"></i>
                            <span class="sr-only">{_T string="Move down '%s' field" pattern="/%s/" replace=$field->getName()|escape}</span>
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
