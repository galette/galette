    <table class="listing">
{if $class eq 'Status'}
        <caption>
            {_T string="Note: members with a status priority lower than %priority are staff members." pattern="/%priority/" replace=$non_staff_priority}
        </caption>
{/if}
        <thead>
            <tr>
                <th class="id_row">#</th>
                <th>{_T string="Name"}</th>
{if $class == 'ContributionsTypes'}
                <th>{_T string="Extends membership?"}</th>
{elseif $class == 'Status'}
                <th>{_T string="Priority"}</th>
{/if}
                <th>{_T string="Actions"}</th>
            </tr>
        </thead>
        <tfoot>
            <tr>
                <td data-scope="row">
                    <span class="row-title">
{if $class eq 'Status'}
                        {_T string="New status"}
{else}
                        {_T string="New contribution type"}
{/if}
                    </span>
                </td>
                <td class="left" data-title="{_T string="Name"}">
                    <input size="40" type="text" name="{$fields.libelle}"/>
                </td>
                <td class="left" data-title="{if $class == 'ContributionsTypes'}{_T string="Extends membership?"}{else}{_T string="Priority"}{/if}">
{if $class == 'ContributionsTypes'}
                    <select name="{$fields.third}">
                        <option value="0" selected="selected">{_T string="No"}</option>
                        <option value="1">{_T string="Yes"}</option>
                    </select>
{elseif $class == 'Status'}
                    <input size="4" type="text" name="{$fields.third}" value="99" />
{/if}
                </td>
                <td class="center actions_row">
                    <input type="hidden" name="new" value="1" />
                    <input type="hidden" name="class" value="{$class}" />
                    <button type="submit" name="valid">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        {_T string="Add"}
                    </button>
                </td>
            </tr>
        </tfoot>
        <tbody>
{foreach from=$entries item=entry key=eid name=allentries}
            <tr class="{if $smarty.foreach.allentries.iteration % 2 eq 0}even{else}odd{/if}">
                <td data-scope="row">
                    {$eid}
                    <span class="row-title">
                        <a href="{path_for name="editEntitled" data=["class" => $url_class, "action" => "edit", "id" => $eid]}">
                            {_T string="%s field" pattern="/%s/" replace=$entry.name|escape}
                        </a>
                    </span>
                </td>
                <td class="left" data-title="{_T string="Name"}">

                    {if $class == 'Status'}
                        {if $entry.extra < 30}
                            <img src="{base_url}/{$template_subdir}images/icon-staff.png" alt="{_T string="[staff]"}" width="16" height="16"/>
                        {else}
                            <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                        {/if}
                    {/if}
                    {$entry.name|escape}
                </td>
                <td data-title="{if $class == 'ContributionsTypes'}{_T string="Extends membership?"}{else}{_T string="Priority"}{/if}">
    {if $class == 'ContributionsTypes'}
                    {if $entry.extra eq 1}
                        {_T string="Yes"}
                    {else}
                        {_T string="No"}
                    {/if}
    {elseif $class == 'Status'}
                    {$entry.extra}
    {/if}
                </td>
                <td class="center actions_row">
                    <a
                        href="{path_for name="editEntitled" data=["class" => $url_class, "action" => "edit", "id" => $eid]}"
                        class="action tooltip"
                    >
                        <i class="fas fa-edit fa-fw"></i>
                        <span class="sr-only">{_T string="Edit '%s' field" pattern="/%s/" replace=$entry.name|escape}</span>
                    </a>
                    <a
                        href="{path_for name="removeEntitled" data=["class" => $url_class, "id" => $eid]}"
                        class="delete tooltip"
                    >
                        <i class="fas fa-trash fa-fw"></i>
                        <span class="sr-only">{_T string="Delete '%s' field" pattern="/%s/" replace=$entry.name|escape}</span>
                    </a>
                </td>
            </tr>
{/foreach}
        </tbody>
    </table>
