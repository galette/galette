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
                                <td class="username_row">
                                <input type="hidden" name="{$person_mode}[]" value="{$person->id}"/>
                                {if $person->isCompany()}
                                    <i class="ui building outline icon tooltip"><span class="sr-only">{_T string="[C]"}</span></i>
                                {elseif $person->isMan()}
                                    <i class="ui male icon tooltip"><span class="sr-only">{_T string="[M]"}</span></i>
                                {elseif $person->isWoman()}
                                    <i class="ui female icon tooltip"><span class="sr-only">{_T string="[W]"}</span></i>
                                {else}
                                    <i class="ui icon"></i>
                                {/if}
                                {if $person->isAdmin()}
                                    <i class="ui user shield red icon"><span class="sr-only">{_T string="[admin]"}</span></i>
                                {elseif $person->isStaff()}
                                    <i class="ui id card alternate orange icon"><span class="sr-only">{_T string="[staff]"}</span></i>
                                {else}
                                    <i class="ui icon"></i>
                                {/if}
                                <a href="{path_for name="member" data=["id" => $person->id]}">{$person->sfullname}</a>
                                </td>
                                <td class="">{$person->nickname|htmlspecialchars}</td>
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

