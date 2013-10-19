{if $navigate|@count != 0}
    <nav>
        <a id="prev" href="{if isset($navigate.prev)}?id_adh={$navigate.prev}{else}#{/if}" class="button{if !isset($navigate.prev)} selected{/if}">{_T string="Previous"}</a>
        {$navigate.pos}/{$navigate.count}
        <a id="next" href="{if isset($navigate.next)}?id_adh={$navigate.next}{else}#{/if}"class="button{if !isset($navigate.next)} selected{/if}">{_T string="Next"}</a>
    </nav>
{/if}
        <ul id="details_menu">
{if ($pref_card_self eq 1) or ($login->isAdmin() or $login->isStaff())}
            <li>
                <a class="button{if !$member->isUp2Date()} disabled{/if}" href="{if $member->isUp2Date()}carte_adherent.php?id_adh={$member->id}{else}#{/if}" id="btn_membercard">{_T string="Generate Member Card"}</a>
            </li>
    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
            <li>
                <a class="button" href="lostpasswd.php?id_adh={$member->id}" id="btn_lostpassword" title="{_T string="Send member a link to generate a new passord, as if had used the 'lost password' functionnality."}">{_T string="New password"}</a>
            </li>
    {/if}
{/if}
            <li>
                <a class="button" href="ajouter_adherent.php?id_adh={$member->id}" id="btn_edit">{_T string="Modification"}</a>
            </li>
{if $login->isAdmin() or $login->isStaff()}
            <li>
                <a class="button" href="gestion_contributions.php?id_adh={$member->id}" id="btn_contrib">{_T string="View contributions"}</a>
            </li>
            <li>
                <a class="button" href="ajouter_contribution.php?id_adh={$member->id}" id="btn_addcontrib">{_T string="Add a contribution"}</a>
            </li>
{/if}
{* If some additionnals actions should be added from plugins, we load the relevant template file
We have to use a template file, so Smarty will do its work (like replacing variables). *}
{if $plugin_detailled_actions|@count != 0}
  {foreach from=$plugin_detailled_actions item=action}
    {include file=$action}
  {/foreach}
{/if}

        </ul>
    <div class="bigtable wrmenu">
        <div id="member_stateofdue" class="{$member->getRowClass()}">{$member->getDues()}</div>
        <table class="details">
            <caption class="ui-state-active ui-corner-top">{_T string="Identity:"}</caption>
            <tr>
                <th>{_T string="Name:"}</th>
                <td>
                    {if $member->isCompany()}
                        <img src="{$template_subdir}images/icon-company.png" alt="{_T string="[C]"}" width="16" height="16"/>
                    {elseif $member->isMan()}
                        <img src="{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
                    {elseif $member->isWoman()}
                        <img src="{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
                    {/if}
                    {$member->sfullname}
                </td>
                <td rowspan="{if $member->isCompany()}7{else}6{/if}" style="width:{$member->picture->getOptimalWidth()}px;">
                    <img
                        src="{$galette_base_path}picture.php?id_adh={$member->id}&amp;rand={$time}"
                        width="{$member->picture->getOptimalWidth()}"
                        height="{$member->picture->getOptimalHeight()}"
                        alt="{_T string="Picture"}"
                        id="photo_adh"/>
                </td>
            </tr>
{if $visibles.societe_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.societe_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
    {if $member->isCompany()}
            <tr>
                <th>{_T string="Company:"}</th>
                <td>{$member->company_name}</td>
            </tr>
    {/if}
{/if}
{if $visibles.pseudo_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.pseudo_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Nickname:"}</th>
                <td>{$member->nickname|htmlspecialchars}</td>
            </tr>
{/if}
{if $visibles.ddn_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.ddn_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Birth date:"}</th>
                <td>{$member->birthdate}</td>
            </tr>
{/if}
{if $visibles.lieu_naissance eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.lieu_naissance eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Birthplace:"}</th>
                <td>{$member->birth_place}</td>
            </tr>
{/if}
{if $visibles.prof_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.prof_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Profession:"}</th>
                <td>{$member->job|htmlspecialchars}</td>
            </tr>
{/if}
{if $visibles.pref_lang eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.pref_lang eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Language:"}</th>
                <td><img src="{$pref_lang_img}" alt=""/> {$pref_lang}</td>
            </tr>
{/if}
        </table>

        <table class="details">
            <caption class="ui-state-active ui-corner-top">{_T string="Contact information:"}</caption>
{if $visibles.adresse_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.adresse_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Address:"}</th>
                <td>
                    {$member->adress|htmlspecialchars}
    {if $member->adress_continuation ne ''}
                    <br/>{$member->adress_continuation|htmlspecialchars}
    {/if}
                </td>
            </tr>
{/if}
{if $visibles.cp_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.cp_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Zip Code:"}</th>
                <td>{$member->zipcode}</td>
            </tr>
{/if}
{if $visibles.ville_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.ville_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="City:"}</th>
                <td>{$member->town|htmlspecialchars}</td>
            </tr>
{/if}
{if $visibles.pays_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.pays_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Country:"}</th>
                <td>{$member->country|htmlspecialchars}</td>
            </tr>
{/if}
{if $visibles.tel_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.tel_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Phone:"}</th>
                <td>{$member->phone}</td>
            </tr>
{/if}
{if $visibles.gsm_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.gsm_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Mobile phone:"}</th>
                <td>{$member->gsm}</td>
            </tr>
{/if}
{if $visibles.email_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.email_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="E-Mail:"}</th>
                <td>
    {if $member->email ne ''}
                    <a href="mailto:{$member->email}">{$member->email}</a>
    {/if}
                </td>
            </tr>
{/if}
{if $visibles.url_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.url_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Website:"}</th>
                <td>
    {if $member->website ne ''}
                    <a href="{$member->website}">{$member->website}</a>
    {/if}
                </td>
            </tr>
{/if}
{if $visibles.icq_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.icq_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="ICQ:"}</th>
                <td>{$member->icq}</td>
            </tr>
{/if}
{if $visibles.jabber_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.jabber_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Jabber:"}</th>
                <td>{$member->jabber}</td>
            </tr>
{/if}
{if $visibles.msn_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.msn_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="MSN:"}</th>
                <td>
    {if $member->msn ne ''}
                    <a href="mailto:{$member->msn}">{$member->msn}</a>
    {/if}
                </td>
            </tr>
{/if}
{if $visibles.gpgid eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.gpgid eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Id GNUpg (GPG):"}</th>
                <td>{$member->gnupgid}</td>
            </tr>
{/if}
{if $visibles.fingerprint eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.fingerprint eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="fingerprint:"}</th>
                <td>{$member->fingerprint}</td>
            </tr>
{/if}
        </table>

        <table class="details">
            <caption class="ui-state-active ui-corner-top">{_T string="Galette-related data:"}</caption>
{if $visibles.bool_display_info eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.bool_display_info eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Be visible in the members list:"}</th>
                <td>{$member->sappears_in_list}</td>
            </tr>
{/if}
{if $login->isAdmin() or $login->isStaff()}
    {if $visibles.activite_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or $visibles.activite_adh eq constant('Galette\Entity\FieldsConfig::ADMIN')}
            <tr>
                <th>{_T string="Account:"}</th>
                <td>{$member->sactive}</td>
            </tr>
    {/if}
{/if}
{if $visibles.id_statut eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.id_statut eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Status:"}</th>
                <td>{$member->sstatus}</td>
            </tr>
{/if}
{if $login->isAdmin() or $login->isStaff()}
    {if $visibles.bool_admin_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or $visibles.bool_admin_adh eq constant('Galette\Entity\FieldsConfig::ADMIN')}
            <tr>
                <th>{_T string="Galette Admin:"}</th>
                <td>{$member->sadmin}</td>
            </tr>
    {/if}
    {if $visibles.bool_exempt_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or $visibles.bool_exempt_adh eq constant('Galette\Entity\FieldsConfig::ADMIN')}
            <tr>
                <th>{_T string="Freed of dues:"}</th>
                <td>{$member->sdue_free}</td>
            </tr>
    {/if}
{/if}
{if $visibles.login_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.login_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Username:"}</th>
                <td>{$member->login}</td>
            </tr>
{/if}
{if $login->isAdmin() or $login->isStaff()}
    {if $visibles.date_crea_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or $visibles.date_crea_adh eq constant('Galette\Entity\FieldsConfig::ADMIN')}
            <tr>
                <th>{_T string="Creation date:"}</th>
                <td>{$member->creation_date}</td>
            </tr>
    {/if}
    {if $visibles.date_modif_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or $visibles.date_modif_adh eq constant('Galette\Entity\FieldsConfig::ADMIN')}
            <tr>
                <th>{_T string="Last modification date:"}</th>
                <td>{$member->modification_date}</td>
            </tr>
    {/if}
    {if $visibles.info_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or $visibles.info_adh eq constant('Galette\Entity\FieldsConfig::ADMIN')}
            <tr>
                <th>{_T string="Other informations (admin):"}</th>
                <td>{$member->others_infos_admin|htmlspecialchars|nl2br}</td>
            </tr>
    {/if}
{/if}
{if $visibles.info_public_adh eq constant('Galette\Entity\FieldsConfig::VISIBLE') or ($visibles.info_public_adh eq constant('Galette\Entity\FieldsConfig::ADMIN') and ($login->isStaff() or $login->isAdmin() or $login->isSuperAdmin()))}
            <tr>
                <th>{_T string="Other informations:"}</th>
                <td>{$member->others_infos|htmlspecialchars|nl2br}</td>
            </tr>
{/if}
{if $member->groups != false && $member->groups|@count != 0 || $member->managed_groups != false && $member->managed_groups|@count != 0}
            <tr>
                <th>{_T string="Groups:"}</th>
                <td>
    {foreach from=$groups item=group key=kgroup}
        {if $member->isGroupMember($group) or $member->isGroupManager($group)}
                    <a href="{if $login->isGroupManager($kgroup)}gestion_groupes.php?id_group={$kgroup}{else}#{/if}" class="button group-btn{if not $login->isGroupManager($kgroup)} notmanaged{/if}">
                        {$group}
            {if $member->isGroupMember($group)}
                        <img src="{$template_subdir}images/icon-user.png" alt="{_T string="[member]"}" width="16" height="16"/>
            {/if}
            {if $member->isGroupManager($group)}
                        <img src="{$template_subdir}images/icon-star.png" alt="{_T string="[manager]"}" width="16" height="16"/>
            {/if}
                    </a>
        {/if}
    {/foreach}
                </td>
            </tr>
{/if}
        </table>

{include file="display_dynamic_fields.tpl" is_form=false}
    </div>
{if $login->isAdmin() or $login->isStaff() or $login->login eq $member->login}
    <script type="text/javascript">
        $(function() {
            {include file="photo_dnd.tpl"}

            $('.notmanaged').click(function(){
                var _el = $('<div id="not_managed_group" title="{_T string="Not managed group" escape="js"}">{_T string="You are not part of managers for the requested group." escape="js"}</div>');
                _el.appendTo('body').dialog({
                    modal: true,
                    buttons: {
                        "{_T string="Ok" escape="js"}": function() {
                            $( this ).dialog( "close" );
                        }
                    },
                    close: function(event, ui){
                        _el.remove();
                    }
                });
                return false;
            });
        });
    </script>
{/if}
