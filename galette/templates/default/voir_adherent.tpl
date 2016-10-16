{if $navigate|@count != 0}
    <nav>
        <a id="prev" href="{if isset($navigate.prev)}?id_adh={$navigate.prev}{else}#{/if}" class="button{if !isset($navigate.prev)} selected{/if}">{_T string="Previous"}</a>
        {$navigate.pos}/{$navigate.count}
        <a id="next" href="{if isset($navigate.next)}?id_adh={$navigate.next}{else}#{/if}" class="button{if !isset($navigate.next)} selected{/if}">{_T string="Next"}</a>
    </nav>
{/if}
        <ul id="details_menu">
{if ($pref_card_self eq 1) or ($login->isAdmin() or $login->isStaff())}
            <li>
                <a class="button{if !$member->isUp2Date()} disabled{/if}" href="{if $member->isUp2Date()}carte_adherent.php?id_adh={$member->id}{else}#{/if}" id="btn_membercard">{_T string="Generate Member Card"}</a>
            </li>
            <li>
                <a class="button" href="{$preferences->pref_adhesion_form_url}?id_adh={$member->id}" id="btn_adhesionform">{_T string="Adhesion form"}</a>
            </li>
    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED') && ($login->isAdmin() || $login->isStaff())}
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
{if $member->hasParent() or $member->hasChildren()}
        <table class="details">
            <caption class="ui-state-active ui-corner-top">{_T string="Family"}</caption>
    {if $member->hasParent()}
            <tr>
                <th>{_T string="Attached to:"}</th>
                <td><a href="voir_adherent.php?id_adh={$member->parent->id}">{$member->parent->sfullname}</a></td>
            </tr>
    {/if}
    {if $member->hasChildren()}
            <tr>
                <th>{_T string="Parent of:"}</th>
                <td>
        {foreach from=$children item=child key=cid}
                    <a href="voir_adherent.php?id_adh={$cid}">{$child}</a>{if not $child@last}, {/if}
        {/foreach}
                </td>
            </tr>
    {/if}

{/if}
{foreach from=$display_elements item=display_element}
    {assign var="elements" value=$display_element->elements}
        <table class="details">
            <caption class="ui-state-active ui-corner-top">{$display_element->label}</caption>
    {foreach from=$elements item=element}
        {assign var="propname" value=$element->propname}
        {assign var="value" value=$member->$propname|escape}

        {if $element->field_id eq 'nom_adh'}
            {assign var="value" value=$member->sfullname|escape}
        {elseif $element->field_id eq 'pref_lang'}
            {assign var="value" value=$pref_lang}
        {elseif $element->field_id eq 'adresse_adh'}
            {assign var="value" value=$member->saddress|escape|nl2br}
        {elseif $element->field_id eq 'bool_display_info'}
            {assign var="value" value=$member->sappears_in_list}
        {elseif $element->field_id eq 'activite_adh'}
            {assign var="value" value=$member->sactive}
        {elseif $element->field_id eq 'id_statut'}
            {assign var="value" value=$member->sstatus}
        {elseif $element->field_id eq 'bool_admin_adh'}
            {assign var="value" value=$member->sadmin}
        {elseif $element->field_id eq 'bool_exempt_adh'}
            {assign var="value" value=$member->sdue_free}
        {elseif $element->field_id eq 'info_adh'}
            {assign var="value" value=$member->others_infos_admin|escape|nl2br}
        {elseif $element->field_id eq 'info_public_adh'}
            {assign var="value" value=$member->others_infos|escape|nl2br}
        {/if}
            <tr>
                <th>{$element->label}</th>
                <td>
                    {if $element->field_id eq 'nom_adh'}
                        {if $member->isCompany()}
                            <img src="{$template_subdir}images/icon-company.png" alt="{_T string="[C]"}" width="16" height="16"/>
                        {elseif $member->isMan()}
                            <img src="{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
                        {elseif $member->isWoman()}
                            <img src="{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
                        {/if}
                    {elseif $element->field_id eq 'pref_lang'}
                        <img src="{$pref_lang_img}" alt=""/>
                    {/if}
                    {if $element->field_id eq 'email_adh' or $element->field_id eq 'msn_adh'}
                        <a href="mailto:{$value}">{$value}</a>
                    {elseif $element->field_id eq 'url_adh'}
                        <a href="{$value}">{$value}</a>
                    {else}
                        {$value}
                    {/if}
                </td>
        {if $display_element@first and $element@first}
                <td rowspan="{$elements|count}" style="width:{$member->picture->getOptimalWidth()}px;">
                    <img
                        src="{$galette_base_path}picture.php?id_adh={$member->id}&amp;rand={$time}"
                        width="{$member->picture->getOptimalWidth()}"
                        height="{$member->picture->getOptimalHeight()}"
                        alt="{_T string="Picture"}"
                        id="photo_adh"/>
                </td>
        {/if}
            </tr>
        {if $display_element@last and $element@last and ($member->groups != false && $member->groups|@count != 0 || $member->managed_groups != false && $member->managed_groups|@count != 0)}
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
    {/foreach}
        </table>
{/foreach}

{include file="display_dynamic_fields.tpl" is_form=false}
        <a href="#" id="back2top">{_T string="Back to top"}</a>
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
