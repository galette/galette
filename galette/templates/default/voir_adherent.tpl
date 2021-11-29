{extends file="page.tpl"}

{block name="content"}
{if isset($navigate) and $navigate|@count != 0}
    <nav>
        <a href="{if isset($navigate.prev)}{path_for name="member" data=["id" => $navigate.prev]}{else}#{/if}" class="ui icon button{if !isset($navigate.prev)} disabled{/if}">
            <i class="step backward icon"></i>
            {_T string="Previous"}
        </a>
        <div class="ui label">{$navigate.pos} / {$navigate.count}</div>
        <a href="{if isset($navigate.next)}{path_for name="member" data=["id" => $navigate.next]}{else}#{/if}" class="ui right icon button{if !isset($navigate.next)} disabled{/if}">
            {_T string="Next"}
            <i class="step forward icon"></i>
        </a>
    </nav>
{/if}
        <div class="ui basic center aligned fitted segment">
            <div class="ui compact small message {$member->getRowClass()}">
                <div class="content">
                    {$member->getDues()}
                    <div class="ui basic buttons">
                    {if $login->isAdmin() or $login->isStaff() || $login->id eq $member->id || ($member->hasParent() and $member->parent->id eq $login->id)}
                            <a
                                    href="{path_for name="contributions" data=["type" => "contributions", "option" => "member", "value" => $member->id]}"
                                    title="{_T string="View contributions"|escape}"
                                    class="ui icon button"
                            >
                                <i class="cookie icon"></i>
                            </a>
                    {/if}
                    {if $login->isAdmin() or $login->isStaff()}

                            <a
                                    href="{path_for name="addContribution" data=["type" => constant('Galette\Entity\Contribution::TYPE_FEE')]}?id_adh={$member->id}"
                                    class="ui icon button tooltip"
                                    title="{_T string="Add a membership fee"|escape}"
                            >
                                <i class="money bill alternate outline icon"></i>
                            </a>
                            <a
                                    href="{path_for name="addContribution" data=["type" => constant('Galette\Entity\Contribution::TYPE_DONATION')]}?id_adh={$member->id}"
                                    class="ui icon button tooltip"
                                    title="{_T string="Add a donation"|escape}"
                            >
                                <i class="gift icon"></i>
                            </a>
                    {/if}
                    </div>
                </div>
            </div>
        </div>
        <div class="ui basic vertically fitted segment">
            <div class="ui horizontal list">
{if ($pref_card_self eq 1) or ($login->isAdmin() or $login->isStaff())}
                <div class="item">
                    <a
                        href="{if $member->isUp2Date()}{path_for name="pdf-members-cards" data=['id_adh' => $member->id]}{else}#{/if}"
                        title="{_T string="Generate members's card"}"
                        class="ui icon button{if !$member->isUp2Date()} disabled{/if} tooltip"
                    >
                        <i class="id badge icon"></i>
                        {_T string="Generate Member Card"}
                    </a>
                </div>
                <div class="item">
                    <a
                        href="{path_for name="adhesionForm" data=["id_adh" => $member->id]}"
                        class="ui icon button"
                    >
                        <i class="id card icon"></i>
                        {_T string="Adhesion form"}
                    </a>
                </div>
    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED') && ($login->isAdmin() || $login->isStaff())}
                <div class="item">
                    <a
                        href="{path_for name="retrieve-pass" data=["id_adh" => $member->id]}"
                        id="btn_lostpassword"
                        title="{_T string="Send member a link to generate a new passord, as if had used the 'lost password' functionnality."}"
                        class="ui icon button"
                    >
                        <i class="unlock icon"></i>
                        {_T string="New password"}
                    </a>
                </div>
    {/if}
{/if}
{if $member->canEdit($login)}
                <div class="item">
                    <a
                        href="{path_for name="editMember" data=["id" => $member->id]}"
                        class="ui icon button"
                        title="{_T string="Edit member"}"
                    >
                        <i class="edit icon"></i>
                        {_T string="Modification"}
                    </a>
                </div>
{/if}
{if $login->isAdmin() or $login->isStaff()}
                <div class="item">
                    <a
                        href="{path_for name="duplicateMember" data=["id_adh" => $member->id]}"
                        title="{_T string="Create a new member with %name information." pattern="/%name/" replace=$member->sfullname}"
                        class="ui icon button"
                    >
                        <i class="clone icon" aria-hidden="true"></i>
                        {_T string="Duplicate"}
                    </a>
                </div>
{/if}
{* If some additionnals actions should be added from plugins, we load the relevant template file
We have to use a template file, so Smarty will do its work (like replacing variables). *}
{if $plugin_detailled_actions|@count != 0}
  {foreach from=$plugin_detailled_actions key=plugin_name item=action}
    {include file=$action module_id=$plugin_name|replace:'det_actions_':''}
  {/foreach}
{/if}
            </div>
        </div>

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
                <table class="ui very basic striped collapsing stackable padded table">
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
                        <th>{$element->label}</th>
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
                {if $display_element@last and $element@last and ($member->getGroups()|@count != 0 || $member->getManagedGroups()|@count != 0)}
                    <tr>
                        <th>{_T string="Groups:"}</th>
                        <td>
            {foreach from=$groups item=group key=kgroup}
                {if $member->isGroupMember($group) or $member->isGroupManager($group)}
                            <a href="{if $login->isGroupManager($kgroup)}{path_for name="groups" data=["id" => $kgroup]}{else}#{/if}" class="button {if not $login->isGroupManager($kgroup)} notmanaged{/if}">
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
