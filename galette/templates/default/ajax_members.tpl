        <table id="listing" class="select_members{if !$multiple} single{/if}">
            <thead>
                <tr>
                    <th class="listing id_row">#</th>
                    <th class="listing left">
                        {_T string="Name"}
                    </th>
                    <th class="listing left">
                        {_T string="Zip - Town"}
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
    {if !isset($excluded) or $excluded != $member->id}
        {assign var=rclass value=$member->getRowClass() }
                <tr>
                    <td class="{$rclass} right">{$member->id}</td>
                    <td class="{$rclass} username_row">
                    {if $member->isCompany()}
                        <i class="ui building outline icon tooltip"><span class="sr-only">{_T string="[C]"}</span></i>
                    {elseif $member->isMan()}
                        <i class="ui male icon tooltip"><span class="sr-only">{_T string="[M]"}</span></i>
                    {elseif $member->isWoman()}
                        <i class="ui female icon tooltip"><span class="sr-only">{_T string="[W]"}</span></i>
                    {else}
                        <i class="ui icon"></i>
                    {/if}
                    {if $member->isAdmin()}
                        <i class="ui user shield red icon"><span class="sr-only">{_T string="[admin]"}</span></i>
                    {elseif $member->isStaff()}
                        <i class="ui id card alternate orange icon"><span class="sr-only">{_T string="[staff]"}</span></i>
                    {else}
                        <i class="ui icon"></i>
                    {/if}
                    <a href="{path_for name="member" data=["id" => $member->id]}">{$member->sfullname}</a>
                    </td>
                    <td class="{$rclass}">{$member->zipcode} {$member->town}</td>
                </tr>
    {/if}
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
                <li id="member_{$recipient->id}">
                    <i class="ui user minus icon" aria-hidden="true"></i>
                    {$recipient->sfullname}
                </li>
    {foreachelse}
                <li id="none_selected">{_T string="No members has been selected yet."}</li>
    {/foreach}
    {if isset($unreachables_members) and $unreachables_members|@count gt 0}
        {foreach from=$unreachables_members item=recipient}
                <li id="member_{$recipient->id}" class="unreachables">
                    <i class="ui user minus icon" aria-hidden="true"></i>
                    {$recipient->sfullname}
                </li>
        {/foreach}
    {/if}
            </ul>
            <button class="button" id="btnvalid">{_T string="Validate"}</button>
            {if isset($the_id)}
                <input type="hidden" name="the_id" id="the_id" value="{$the_id}"/>
            {/if}
        </section>
{/if}
