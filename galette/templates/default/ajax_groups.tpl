        <table id="listing" class="select_members">
            <thead>
                <tr>
                    <th class="listing id_row">#</th>
                    <th class="listing left">
                        {_T string="Name"}
                    </th>
                    <th class="listing left">
                        {_T string="Manager(s)"}
                    </th>
                    <th class="listing"></th>
                </tr>
            </thead>
            <tbody>
{foreach from=$groups_list item=group}
    {assign var="managers" value=$group->getManagers()}
                <tr>
                    <td class="right">{$group->getId()}</td>
                    <td class="nowrap username_row">
                        <a href="voir_groupe.php?id_group={$group->getId()}">{$group->getName()}</a>
                    </td>
                    <td class="nowrap username_row">
    {foreach from=$managers item=manager name="managersiterate"}
        {if not $smarty.foreach.managersiterate.first}, {/if}
                        {$manager->sname}
    {/foreach}
                    </td>
                    <td class="right nowrap">{_T string="%membercount members" pattern="/%membercount/" replace=$group->getMemberCount(true)}</td>
                </tr>
{foreachelse}
                <tr><td colspan="3" class="emptylist">{_T string="no group"}</td></tr>
{/foreach}
            </tbody>
        </table>
        <section id="selected_groups">
            <header class="ui-state-default ui-state-active"><h3>{_T string="Selected groups"}</h3></header>
            <ul>
{foreach from=$selected_groups item=group}
                <li id="group_{$group.id}">{$group.name}</li>
{foreachelse}
                <li id="none_selected">{_T string="No groups has been selected yet."}</li>
{/foreach}
            </ul>
            <button class="button" id="btnvalid">{_T string="Validate"}</button>
        </section>
