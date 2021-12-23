{extends file="page.tpl"}

{block name="page_title" prepend}
    <div class="right aligned bottom floating ui  {$member->getRowClass()} label">{$member->getDues()}</div>
{/block}

{block name="content"}

    <div class="ui vertical compact menu right floated">
        {if $member->canEdit($login)}
            <a
                    href="{path_for name="editMember" data=["id" => $member->id]}"
                    class="ui item action"
            >
                <i class="edit icon"></i>
                {_T string="Modification"}
            </a>
        {/if}

        <div class="ui simple dropdown item">
            <i class="dropdown icon"></i>
            ...
            <div class="left menu">
                {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED') && ($login->isAdmin() || $login->isStaff())}
                    <a
                            href="{path_for name="retrieve-pass" data=["id_adh" => $member->id]}"
                            id="btn_lostpassword"
                            title="{_T string="Send member a link to generate a new password, as if had used the 'lost password' functionality."}"
                            class="ui item"
                    >
                        <i class="unlock icon"></i>
                        {_T string="New password"}
                    </a>
                {/if}
                {if ($pref_card_self eq 1) or ($login->isAdmin() or $login->isStaff())}
                    <a
                            href="{if $member->isUp2Date()}{path_for name="pdf-members-cards" data=['id_adh' => $member->id]}{else}#{/if}"
                            class="ui item{if !$member->isUp2Date()} disabled{/if} tooltip"
                    >
                        <i class="id badge icon"></i>
                        {_T string="Generate Member Card"}
                    </a>
                    <a
                            href="{path_for name="adhesionForm" data=["id_adh" => $member->id]}"
                            class="ui item"
                    >
                        <i class="id card icon"></i>
                        {_T string="Adhesion form"}
                    </a>
                {/if}
                {if $login->isAdmin() or $login->isStaff() || $login->id eq $member->id || ($member->hasParent() and $member->parent->id eq $login->id)}
                    <a
                            href="{path_for name="contributions" data=["type" => "contributions", "option" => "member", "value" => $member->id]}"
                            class="ui item"
                    >
                        <i class="cookie icon"></i>
                        {_T string="View contributions"}
                    </a>
                {/if}
                {if $login->isAdmin() or $login->isStaff()}

                    <a
                            href="{path_for name="addContribution" data=["type" => constant('Galette\Entity\Contribution::TYPE_FEE')]}?id_adh={$member->id}"
                            class="ui item"
                    >
                        <i class="money bill alternate outline icon"></i>
                        {_T string="Add a membership fee"}
                    </a>
                    <a
                            href="{path_for name="addContribution" data=["type" => constant('Galette\Entity\Contribution::TYPE_DONATION')]}?id_adh={$member->id}"
                            class="ui item"
                    >
                        <i class="gift icon"></i>
                        {_T string="Add a donation"}
                    </a>

                    {if $login->isAdmin() or $login->isStaff()}
                        <a
                                href="{path_for name="duplicateMember" data=["id_adh" => $member->id]}"
                                title="{_T string="Create a new member with %name information." pattern="/%name/" replace=$member->sfullname}"
                                class="ui item"
                        >
                            <i class="clone icon" aria-hidden="true"></i>
                            {_T string="Duplicate"}
                        </a>
                    {/if}
                    {* If some additionnals actions should be added from plugins, we load the relevant template file
                    We have to use a template file, so Smarty will do its work (like replacing variables). *}
                    {if $plugin_detailled_actions|@count != 0}
                        {foreach from=$plugin_detailled_actions key=plugin_name item=action}
                            {include file=$action module_id=$plugin_name|replace:'det_actions_':''}
                        {/foreach}
                    {/if}

                {/if}
            </div>
        </div>
    </div>
    {include file="ui_elements/navigate.tpl"}
    {include file="ui_elements/member_card.tpl"}

    {if $member->hasParent() or $member->hasChildren()}
    <div class="ui basic fitted segment">
        <div class="ui styled fluid accordion row">
            <div class="active title">
                <i class="icon dropdown"></i>
                {_T string="Family"}
            </div>
            <div class="active content field">
                <table class="ui very basic striped collapsing stackable padded table">
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
                </table>
            </div>
        </div>
    </div>

{/if}
{foreach from=$display_elements item=display_element}
    {assign var="elements" value=$display_element->elements}
    <div class="ui basic fitted segment">
        <div class="ui styled fluid accordion row">
            <div class="active title">
                <i class="icon dropdown"></i>
                {_T string=$display_element->label}
            </div>
            <div class="active content field">
                <table class="ui very basic striped stackable padded table">
            {foreach from=$elements item=element}
                {if $element->field_id eq 'parent_id'}
                    {continue}
                {/if}
                {assign var="propname" value=$element->propname}

        {assign var="propvalue" value=$member->$propname}
        {if $propvalue}
            {assign var=value value=$propvalue|escape}
        {else}
            {assign var=value value=$propvalue}
        {/if}

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
                        <th class="three wide column">{$element->label}</th>
                        <td>
                {if $element->field_id eq 'nom_adh'}
                    {if $member->isCompany()}
                            <i class="ui industry icon"></i>
                    {elseif $member->isMan()}
                            <i class="ui mars icon"></i>
                    {elseif $member->isWoman()}
                            <i class="ui venus icon"></i>
                    {/if}
                {/if}
        {if $element->field_id eq 'email_adh'}
                                <a href="mailto:{$value}">{$value}</a>
                {elseif $element->field_id eq 'tel_adh' or $element->field_id eq 'gsm_adh'}
                                <a href="tel:{$value}">{$value}</a>
                {elseif $element->field_id eq 'ddn_adh'}
                                {$value} {$member->getAge()}
                {else}
                                {$value}
                {/if}
                        </td>
                    </tr>
                {if $display_element@last and $element@last and ($member->getGroups()|@count != 0 || $member->getManagedGroups()|@count != 0)}
                    <tr>
                        <th class="three wide column">{_T string="Groups:"}</th>
                        <td>
            {foreach from=$groups item=group key=kgroup}
                {if $member->isGroupMember($group) or $member->isGroupManager($group)}
                            <a href="{if $login->isGroupManager($kgroup)}{path_for name="groups" data=["id" => $kgroup]}{else}#{/if}" class="ui button {if not $login->isGroupManager($kgroup)} notmanaged{/if}">
                                {$group}
                    {if $member->isGroupMember($group)}
                                <i class="ui user icon" title="{_T string="Member of group"}"></i>
                    {/if}
                    {if $member->isGroupManager($group)}
                                <i class="ui user tie icon" title="{_T string="Group manager"}"></i>
                    {/if}
                            </a>
                {/if}
            {/foreach}
                        </td>
                    </tr>
                {/if}
            {/foreach}
                </table>
            </div>
        </div>
    </div>
{/foreach}

{include file="display_dynamic_fields.tpl" object=$member}
{include file="display_socials.tpl" socials=$member->socials}

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
