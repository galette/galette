                     <table class="listing ui celled table">
                        <thead>
                            <tr>
                                <th class="left">
                                    {_T string="Name"}
                                </th>
                                <th class="left">
                                    {_T string="Nickname"}
                                </th>
                                {if $login->isSuperAdmin()}
                                <th>&nbsp;</th>
                                {/if}
                            </tr>
                        </thead>
                        <tbody>
{foreach from=$persons item=person name=allpersons}
                            <tr class="{if $smarty.foreach.allpersons.iteration % 2 eq 0}even{else}odd{/if}">
                                <td class="nowrap username_row">
                                <input type="hidden" name="{$person_mode}[]" value="{$person->id}"/>
                                {if $person->isCompany()}
                                    <img src="{base_url}/{$template_subdir}images/icon-company.png" alt="{_T string="[W]"}" width="16" height="16"/>
                                {elseif $person->isMan()}
                                    <img src="{base_url}/{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
                                {elseif $person->isWoman()}
                                    <img src="{base_url}/{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
                                {else}
                                    <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="10" height="12"/>
                                {/if}
                                {if $person->isAdmin()}
                                    <img src="{base_url}/{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="16" height="16"/>
                                {elseif $person->isStaff()}
                                    <img src="{base_url}/{$template_subdir}images/icon-staff.png" alt="{_T string="[staff]"}" width="16" height="16"/>
                                {else}
                                    <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                                {/if}
                                <a href="{path_for name="member" data=["id" => $person->id]}">{$person->sfullname}</a>
                                </td>
                                <td class="nowrap">{$person->nickname|htmlspecialchars}</td>
    {if $login->isSuperAdmin()}
                                <td class="actions_row">
                                    <a
                                            href="{path_for name="impersonate" data=["id" => $person->id]}"
                                            class="tooltip"
                                    >
                                        <i class="fas fa-user-secret fa-fw" aria-hidden="true"></i>
                                        <span class="sr-only">{_T string="Log in in as %membername" pattern="/%membername/" replace=$person->sname}</span>
                                    </a>
                                </td>
                            </tr>
    {/if}
{foreachelse}
                            <tr>
                                <td colspan="2">
    {if $person_mode == 'members'}
                                    {_T string="No member attached"}
    {else}
                                    {_T string="No manager attached"}
    {/if}
                                </td>
                            </tr>
{/foreach}
                        </tbody>
                    </table>

