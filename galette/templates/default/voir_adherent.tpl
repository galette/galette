{extends file="page.tpl"}

{block name="content"}
{if isset($navigate) and $navigate|@count != 0}
    <nav>
        <a href="{if isset($navigate.prev)}{path_for name="member" data=["id" => $navigate.prev]}{else}#{/if}" class="button{if !isset($navigate.prev)} disabled{/if}">
            <i class="fas fa-step-backward"></i>
            {_T string="Previous"}
        </a>
        {$navigate.pos}/{$navigate.count}
        <a href="{if isset($navigate.next)}{path_for name="member" data=["id" => $navigate.next]}{else}#{/if}" class="button{if !isset($navigate.next)} disabled{/if}">
            {_T string="Next"}
            <i class="fas fa-step-forward"></i>
        </a>
    </nav>
{/if}
    <div class="bigtable">
        <div id="member_stateofdue" class="{$member->getRowClass()}">{$member->getDues()}</div>
        <ul id="details_menu">
{if ($pref_card_self eq 1) or ($login->isAdmin() or $login->isStaff())}
            <li>
                <a
                    href="{if $member->isUp2Date()}{path_for name="pdf-members-cards" data=['id_adh' => $member->id]}{else}#{/if}"
                    title="{_T string="Generate members's card"}"
                    class="button bigbutton{if !$member->isUp2Date()} disabled{/if} tooltip"
                >
                    <i class="fas fa-id-badge fa-fw fa-2x"></i>
                    {_T string="Generate Member Card"}
                </a>
            </li>
            <li>
                <a
                    href="{path_for name="adhesionForm" data=["id_adh" => $member->id]}"
                    class="button bigbutton tooltip"
                >
                    <i class="fas fa-id-card fa-fw fa-2x"></i>
                    {_T string="Adhesion form"}
                </a>
            </li>
    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED') && ($login->isAdmin() || $login->isStaff())}
            <li>
                <a
                    href="{path_for name="retrieve-pass" data=["id_adh" => $member->id]}"
                    id="btn_lostpassword"
                    title="{_T string="Send member a link to generate a new passord, as if had used the 'lost password' functionnality."}"
                    class="button bigbutton tooltip"
                >
                    <i class="fas fa-unlock fa-fw fa-2x"></i>
                    {_T string="New password"}
                </a>
            </li>
    {/if}
{/if}
            <li>
                <a
                    href="{path_for name="editMember" data=["id" => $member->id]}"
                    class="button bigbutton tooltip"
                    title="{_T string="Edit member"}"
                >
                    <i class="fas fa-user-edit fa-fw fa-2x"></i>
                    {_T string="Modification"}
                </a>
            </li>
{if $login->isAdmin() or $login->isStaff()}
            <li>
                <a
                    href="{path_for name="contributions" data=["type" => "contributions", "option" => "member", "value" => $member->id]}"
                    title="{_T string="View member's contributions"}"
                    class="button bigbutton tooltip"
                >
                    <i class="fas fa-cookie fa-fw fa-2x"></i>
                    {_T string="View contributions"}
                </a>
            </li>
            <li>
                <a
                    href="{path_for name="addContribution" data=["type" => "fee"]}?id_adh={$member->id}"
                    class="button bigbutton tooltip"
                >
                    <i class="fas fa-user-check fa-fw fa-2x"></i>
                    {_T string="Add a membership fee"}
                </a>
            </li>
            <li>
                <a
                    href="{path_for name="addContribution" data=["type" => "donation"]}?id_adh={$member->id}"
                    class="button bigbutton tooltip"
                >
                    <i class="fas fa-gift fa-fw fa-2x"></i>
                    {_T string="Add a donation"}
                </a>
            </li>
            <li>
                <a
                    href="{path_for name="duplicateMember" data=["id_adh" => $member->id]}"
                    title="{_T string="Create a new member with %name information." pattern="/%name/" replace=$member->sfullname}"
                    class="button bigbutton tooltip"
                >
                    <i class="fas fa-clone fa-fw fa-2x" aria-hidden="true"></i>
                    {_T string="Duplicate"}
                </a>
            </li>
{/if}
{* If some additionnals actions should be added from plugins, we load the relevant template file
We have to use a template file, so Smarty will do its work (like replacing variables). *}
{if $plugin_detailled_actions|@count != 0}
  {foreach from=$plugin_detailled_actions key=plugin_name item=action}
    {include file=$action module_id=$plugin_name|replace:'det_actions_':''}
  {/foreach}
{/if}

        </ul>
{if $member->hasParent() or $member->hasChildren()}
        <table class="details">
            <caption class="ui-state-active ui-corner-top">{_T string="Family"}</caption>
    {if $member->hasParent()}
            <tr>
                <th>{_T string="Attached to:"}</th>
                <td><a href="{path_for name="member" data=["id" => $member->parent->id]}">{$member->parent->sfullname}</a></td>
            </tr>
    {/if}
    {if $member->hasChildren()}
            <tr>
                <th>{_T string="Parent of:"}</th>
                <td>
        {foreach from=$member->children item=child}
                    <a href="{path_for name="member" data=["id" => $child->id]}">{$child->sfullname}</a>{if not $child@last}, {/if}
        {/foreach}
                </td>
            </tr>
    {/if}

{/if}
{foreach from=$display_elements item=display_element}
    {assign var="elements" value=$display_element->elements}
        <table class="details">
            <caption class="ui-state-active ui-corner-top">{_T string=$display_element->label}</caption>
    {foreach from=$elements item=element}
        {if $element->field_id eq 'parent_id'}
            {continue}
        {/if}
        {assign var="propname" value=$element->propname}
        {assign var="value" value=$member->$propname|escape}

        {if $element->field_id eq 'nom_adh'}
            {assign var="value" value=$member->sfullname}
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
                    <i class="fas fa-industry fa-fw"></i>
            {elseif $member->isMan()}
                    <i class="fas fa-mars fa-fw"></i>
            {elseif $member->isWoman()}
                    <i class="fas fa-venus fa-fw"></i>
            {/if}
        {/if}
        {if $element->field_id eq 'email_adh' or $element->field_id eq 'msn_adh'}
                        <a href="mailto:{$value}">{$value}</a>
        {elseif $element->field_id eq 'tel_adh' or $element->field_id eq 'gsm_adh'}
                        <a href="tel:{$value}">{$value}</a>
        {elseif $element->field_id eq 'url_adh'}
                        <a href="{$value}">{$value}</a>
        {elseif $element->field_id eq 'ddn_adh'}
                        {$value} {$member->getAge()}
        {else}
                        {$value}
        {/if}
                </td>
        {if $display_element@first and $element@first}
            {assign var="mid" value=$member->id}
                <td rowspan="{$elements|count}" style="width:{$member->picture->getOptimalWidth()}px;">
                    <img
                        src="{path_for name="photo" data=["id" => $mid, "rand" => $time]}"
                        width="{$member->picture->getOptimalWidth()}"
                        height="{$member->picture->getOptimalHeight()}"
                        alt="{_T string="Picture"}"
                        {if $login->isAdmin() or $login->isStaff() or $login->login eq $member->login} title="{_T string="You can drop new image here to get photo changed"}" class="tooltip"{/if}
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
                    <a href="{if $login->isGroupManager($kgroup)}{path_for name="groups" data=["id" => $kgroup]}{else}#{/if}" class="button {if not $login->isGroupManager($kgroup)} notmanaged{/if}">
                        {$group}
            {if $member->isGroupMember($group)}
                        <i class="fas fa-user fa-w" title="{_T string="Member of group"}"></i>
            {/if}
            {if $member->isGroupManager($group)}
                        <i class="fas fa-user-tie fa-w" title="{_T string="Group manager"}"></i>
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

{include file="display_dynamic_fields.tpl" object=$member}
        <a href="#" id="back2top">{_T string="Back to top"}</a>
    </div>
{/block}
{block name="javascripts"}
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
{/block}
