        <table id="listing" class="select_members{if !$multiple} single{/if}">
            <thead>
                <tr> 
                    <th class="listing id_row">#</th>
                    <th class="listing left">
                        {_T string="Name"}
                    </th>
                    <th class="listing left">
                        {_T string="Nickname"}
                    </th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="3" class="center">
                        {_T string="Pages:"}<br/>
                        <ul class="pages">{$pagination}</ul>
                    </td>
                </tr>
            </tfoot>
            <tbody>
{foreach from=$members_list item=member}
                <tr>
                    <td class="right">{$member->id}</td>
                    <td class="nowrap username_row">
                    {if $member->isCompany()}
                        <img src="{$template_subdir}images/icon-company.png" alt="{_T string="[W]"}" width="16" height="16"/>
                    {elseif $member->isMan()}
                        <img src="{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
                    {elseif $member->isWoman()}
                        <img src="{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
                    {else}
                        <img src="{$template_subdir}images/icon-empty.png" alt="" width="10" height="12"/>
                    {/if}
                    {if $member->isAdmin()}
                        <img src="{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="16" height="16"/>
                    {elseif $member->isStaff()}
                        <img src="{$template_subdir}images/icon-staff.png" alt="{_T string="[staff]"}" width="16" height="16"/>
                    {else}
                        <img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                    {/if}
                    <a href="voir_adherent.php?id_adh={$member->id}">{$member->sfullname}</a>
                    </td>
                    <td class="nowrap">{$member->nickname|htmlspecialchars}</td>
                </tr>
{foreachelse}
                <tr><td colspan="3" class="emptylist">{_T string="no member"}</td></tr>
{/foreach}
            </tbody>
        </table>
{if $multiple}
        <section id="selected_members">
            <header class="ui-state-default ui-state-active"><h3>{_T string="Selected members"}</h3></header>
            <ul>
    {foreach from=$selected_members item=recipient}
                <li id="member_{$recipient->id}">{$recipient->sfullname}</li>
    {foreachelse}
                <li id="none_selected">{_T string="No members has been selected yet."}</li>
    {/foreach}
    {if $unreachables_members|@count gt 0}
        {foreach from=$unreachables_members item=recipient}
                <li id="member_{$recipient->id}" class="unreachables">{$recipient->sfullname}</li>
        {/foreach}
    {/if}
            </ul>
            <button class="button" id="btnvalid">{_T string="Validate"}</button>
            {if isset($the_id)}
                <input type="hidden" name="the_id" id="the_id" value="{$the_id}"/>
            {/if}
        </section>
{/if}
