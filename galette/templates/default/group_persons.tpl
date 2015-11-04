                     <table class="listing">
                        <thead>
                            <tr>
                                <th class="left">
                                    {_T string="Name"}
                                </th>
                                <th class="left">
                                    {_T string="Nickname"}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
{foreach from=$persons item=person name=allpersons}
                            <tr class="{if $smarty.foreach.allpersons.iteration % 2 eq 0}even{else}odd{/if}">
                                <td class="nowrap username_row">
                                <input type="hidden" name="{$person_mode}[]" value="{$person->id}"/>
                                {if $person->isCompany()}
                                    <img src="{$galette_base_path}{$template_subdir}images/icon-company.png" alt="{_T string="[W]"}" width="16" height="16"/>
                                {elseif $person->isMan()}
                                    <img src="{$galette_base_path}{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
                                {elseif $person->isWoman()}
                                    <img src="{$galette_base_path}{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
                                {else}
                                    <img src="{$galette_base_path}{$template_subdir}images/icon-empty.png" alt="" width="10" height="12"/>
                                {/if}
                                {if $person->isAdmin()}
                                    <img src="{$galette_base_path}{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="16" height="16"/>
                                {elseif $person->isStaff()}
                                    <img src="{$galette_base_path}{$template_subdir}images/icon-staff.png" alt="{_T string="[staff]"}" width="16" height="16"/>
                                {else}
                                    <img src="{$galette_base_path}{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                                {/if}
                                <a href="{urlFor name="member" options=["id" => $person->id]}">{$person->sfullname}</a>
                                </td>
                                <td class="nowrap">{$person->nickname|htmlspecialchars}</td>
                            </tr>
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

