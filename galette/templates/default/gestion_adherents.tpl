{extends file="page.tpl"}


{function name=draw_actions}
                    <td class="{$rclass} center nowrap actions_row">
{if $member->canEdit($login)}
                        <a
                            href="{path_for name="editMember" data=["id" => $member->id]}"
                            class="tooltip action"
                        >
                            <i class="ui user edit blue icon" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="%membername: edit information" pattern="/%membername/" replace=$member->sname}</span>
                        </a>
{/if}
{if $login->isAdmin() or $login->isStaff()}
                        <a
                            href="{path_for name="contributions" data=["type" => "contributions", "option" => "member", "value" => $member->id]}"
                            class="tooltip"
                        >
                            <i class="ui cookie icon" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="%membername: contributions" pattern="/%membername/" replace=$member->sname}</span>
                        </a>
                        <a
                            href="{path_for name="removeMember" data=["id" => $member->id]}"
                            class="delete tooltip"
                        >
                            <i class="ui user times red icon" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="%membername: remove from database" pattern="/%membername/" replace=$member->sname}</span>
                        </a>
{/if}
{if $login->isSuperAdmin()}
                        <a
                            href="{path_for name="impersonate" data=["id" => $member->id]}"
                            class="tooltip"
                        >
                            <i class="ui user secret icon" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="Log in in as %membername" pattern="/%membername/" replace=$member->sname}</span>
                        </a>
{/if}
{* If some additionnals actions should be added from plugins, we load the relevant template file
We have to use a template file, so Smarty will do its work (like replacing variables). *}
{if $plugin_actions|@count != 0}
    {foreach from=$plugin_actions key=plugin_name item=action}
        {include file=$action module_id=$plugin_name|replace:'actions_':''}
    {/foreach}
{/if}
                    </td>
{/function}

{block name="content"}
        <form action="{path_for name="filter-memberslist"}" method="post" id="filtre" class="ui form">
            <div class="ui segment">
{if !isset($adv_filters) || !$adv_filters}
            <div class="five fields">
                <div class="field">
                    <label for="filter_str">{_T string="Search:"}</label>
                    <input type="text" name="filter_str" id="filter_str" value="{$filters->filter_str}" type="search" placeholder="{_T string="Enter a value"}"/>
                </div>
                <div class="field">
                    <label for="filter_str">{_T string="in:"}</label>
                    <select name="field_filter" class="ui search dropdown nochosen">
                        {html_options options=$field_filter_options selected=$filters->field_filter}
                    </select>
                </div>
                <div class="field">
                    <label for="filter_str">{_T string="among:"}</label>
                    <select name="membership_filter" onchange="form.submit()" class="ui search dropdown nochosen">
                        {html_options options=$membership_filter_options selected=$filters->membership_filter}
                    </select>
                </div>
                <div class="flexend field">
                    <label for="filter_account" class="hidden">{_T string="among:"}</label>
                    <select name="filter_account" onchange="form.submit()" class="ui search dropdown nochosen">
                        {html_options options=$filter_accounts_options selected=$filters->filter_account}
                    </select>
                </div>
                <div class="flexend field">
                    <label for="group_filter" class="hidden">{_T string="among:"}</label>
                    <select name="group_filter" onchange="form.submit()" class="ui search dropdown nochosen">
                        <option value="0">{_T string="Select a group"}</option>
    {foreach from=$filter_groups_options item=group}
                        <option value="{$group->getId()}"{if $filters->group_filter eq $group->getId()} selected="selected"{/if}>{$group->getIndentName()}</option>
    {/foreach}
                    </select>
                </div>
            </div>
            <div class="two fields">
                <div class="field">
                    <div class="inline fields">
                        <label for="email_filter">{_T string="Members that have an email address:"}</label>
                        <div class="field">
                            <div class="ui radio checkbox">
                                <input type="radio" name="email_filter" id="filter_dc_email" value="{Galette\Repository\Members::FILTER_DC_EMAIL}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_DC_EMAIL')} checked="checked"{/if}>
                                <label for="filter_dc_email">{_T string="Don't care"}</label>
                            </div>
                        </div>
                        <div class="field">
                            <div class="ui radio checkbox">
                                <input type="radio" name="email_filter" id="filter_with_email" value="{Galette\Repository\Members::FILTER_W_EMAIL}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_W_EMAIL')} checked="checked"{/if}>
                                <label for="filter_with_email">{_T string="With"}</label>
                            </div>
                        </div>
                        <div class="field">
                            <div class="ui radio checkbox">
                                <input type="radio" name="email_filter" id="filter_without_email" value="{Galette\Repository\Members::FILTER_WO_EMAIL}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_WO_EMAIL')} checked="checked"{/if}>
                                <label for="filter_without_email">{_T string="Without"}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="right aligned field">
                    <button type="submit"  class="tooltip action ui button" title="{_T string="Apply filters"}" name="filter">
                        <i class="search icon"></i>
                        {_T string="Filter"}
                    </button>
                    <button type="submit"  class="tooltip action ui button" title="{_T string="Save selected criteria"}" name="savesearch" id="savesearch">
                        <i class="save icon"></i>
                        {_T string="Save"}
                    </button>
                    <input type="submit" name="clear_filter" class="tooltip ui button" value="{_T string="Clear filter"}" title="{_T string="Reset all filters to defaults"}"/>
                </div>
            </div>
{else}
            <div class="field">
                <span class="ui blue ribbon label">{_T string="Advanced search mode"}</span>
                <button type="submit" class="tooltip action ui button" title="{_T string="Change search criteria"}" name="adv_criteria">
                    <i class="edit icon"></i>
                    {_T string="Change criteria"}
                </button>
                <button type="submit"  class="tooltip action ui button" title="{_T string="Save current advanced search criteria"}" name="savesearch" id="savesearch">
                    <i class="save icon"></i>
                    {_T string="Save"}
                </button>
                <input type="hidden" name="advanced_search" value="1" class="ui button"/>
                <input type="submit" name="clear_filter" class="tooltip ui button" value="{_T string="Clear filter"}" title="{_T string="Reset all filters to defaults"}"/>
                <div class="ui basic fluid accordion">
                    <div class="title">
                        <i class="dropdown icon"></i>
                        {_T string="Show/hide query"}
                    </div>
                    <div class="content">
                        <pre id="sql_qry" class="hidden">{$filters->query}</pre>
                    </div>
                </div>
            </div>
{/if}
            {include file="forms_types/csrf.tpl"}
        </div>


        <div class="infoline">
            <div class="ui basic horizontal segments">
                <div class="ui basic fitted segment">
                    <div class="ui label">{_T string="%count member" plural="%count members" count=$nb_members pattern="/%count/" replace=$nb_members}</div>
                </div>
                <div class="ui basic right aligned fitted segment">
                    <div class="inline field">
                        <label for="nbshow">{_T string="Records per page:"}</label>
                        <select name="nbshow" id="nbshow" class="ui dropdown nochosen">
                            {html_options options=$nbshow_options selected=$numrows}
                        </select>
                        <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                    </div>
                </div>
            </div>
        </div>
        </form>
        <form action="{path_for name="batch-memberslist"}" method="post" id="listform" class="ui form">
        <div class="ui basic fitted segment">
            <table class="listing ui celled table">
                <thead>
                    <tr>
{foreach item=column from=$galette_list}
    {if $column->field_id eq 'id_adh'}
        {if $preferences->pref_show_id}
                        <th class="id_row">
                            <a href="{path_for name="members" data=["option" => "order", "value" => "Galette\Repository\Members::ORDERBY_ID"|constant]}">
                            {_T string="Mbr id"}
                            {if $filters->orderby eq constant('galette\Repository\Members::ORDERBY_ID')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                                <i class="ui angle down icon tooltip"></i>
                                {else}
                                <i class="ui angle up icon tooltip"></i>
                                {/if}
                            {/if}
                            </a>
                        </th>
        {else}
                        <th class="id_row">#</th>
        {/if}
    {else}
                        <th class="left">
                            <a href="{path_for name="members" data=["option" => "order", "value" => $column->field_id]}">
                            {$column->label}
                            {if $filters->orderby eq $column->field_id}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                                <i class="ui angle down icon tooltip"></i>
                                {else}
                                <i class="ui angle up icon tooltip"></i>
                                {/if}
                            {/if}
                            </a>
                        </th>
    {/if}
{/foreach}
                        <th class="actions_row">{_T string="Actions"}</th>
                    </tr>
                </thead>
                <tbody>
{foreach from=$members item=member key=ordre}
    {assign var=rclass value=$member->getRowClass() }
                    <tr>
    {foreach item=column from=$galette_list}
        {if $column->field_id eq 'id_adh'}
                        <td class="{$rclass} right" data-scope="id">
            {if $preferences->pref_show_id}
                        {$member->id}
            {else}
                        {$ordre+1+($filters->current_page - 1)*$numrows}
            {/if}
                        </td>
        {elseif $column->field_id eq 'list_adh_name'}
                        <td class="{$rclass} nowrap username_row" data-scope="row">
                            <input type="checkbox" name="member_sel[]" value="{$member->id}"/>
            {if $member->isCompany()}
                            <i class="ui building outline icon tooltip"><span class="sr-only">{_T string="Is a company"}</span></i>
            {elseif $member->isMan()}
                            <i class="ui male icon tooltip"><span class="sr-only">{_T string="Is a man"}</span></i>
            {elseif $member->isWoman()}
                            <i class="ui female icon tooltip"><span class="sr-only">{_T string="Is a woman"}</span></i>
            {else}
                            <i class="ui icon"></i>
            {/if}
            {if $member->email != ''}
                            <a href="mailto:{$member->email}" class="tooltip">
                                <i class="ui envelope outline teal icon" aria-hidden="true"></i>
                                <span class="sr-only">{_T string="Mail"}</span>
                            </a>
            {else}
                            <i class="ui icon"></i>
            {/if}
            {if $member->isAdmin()}
                            <span class="tooltip">
                                <i class="ui user shield red icon" aria-hidden="true"></i>
                                <span class="sr-only">{_T string="Admin"}</span>
                            </span>
            {elseif $member->isStaff()}
                            <span class="tooltip">
                                <i class="ui id card alternate orange icon" aria-hidden="true"></i>
                                <span class="sr-only">{_T string="Staff member"}</span>
                            </span>
            {else}
                            <i class="ui icon"></i>
            {/if}
                        {assign var="mid" value=$member->id}
                            <a href="{path_for name="member" data=["id" => $member->id]}">{$member->sname}{if $member->company_name} ({$member->company_name}){/if}</a>
                        </td>
        {else}
            {assign var="lrclass" value=$rclass}
            {assign var="propname" value=$column->propname}
            {assign var="propvalue" value=$member->$propname}
            {assign var="value" value=null}

            {if $column->field_id eq 'nom_adh'}
                {assign var="value" value=$member->sfullname}
            {elseif $column->field_id eq 'pseudo_adh'}
                {assign var="lrclass" value="$rclass nowrap"}
                {assign var=value value=$member->$propname}
            {elseif $column->field_id eq 'tel_adh' or $column->field_id eq 'gsm_adh'}
                {assign var="lrclass" value="$rclass nowrap"}
            {elseif $column->field_id eq 'id_statut'}
                {assign var="lrclass" value="$rclass nowrap"}
                {assign var=value value={statusLabel id=$member->$propname}}
            {elseif $column->field_id eq 'titre_adh'}
                {if is_object($member->title)}
                    {assign var=value value=$member->title->long}
                {/if}
            {elseif $column->field_id eq 'pref_lang'}
                {assign var="value" value=$i18n->getNameFromId($member->language)}
            {elseif $column->field_id eq 'adresse_adh'}
                {assign var="value" value=$member->saddress|escape|nl2br}
                {assign var="escaped" value=true}
            {elseif $column->field_id eq 'bool_display_info'}
                {assign var="value" value=$member->sappears_in_list}
            {elseif $column->field_id eq 'activite_adh'}
                {assign var="value" value=$member->sactive}
            {elseif $column->field_id eq 'id_statut'}
                {assign var="value" value=$member->sstatus}
            {elseif $column->field_id eq 'bool_admin_adh'}
                {assign var="value" value=$member->sadmin}
            {elseif $column->field_id eq 'bool_exempt_adh'}
                {assign var="value" value=$member->sdue_free}
            {elseif $column->field_id eq 'sexe_adh'}
                {assign var="value" value=$member->sgender}
            {/if}

            {* If value has not been set, take the generic value *}
            {if !$value}
                {if $propvalue}
                    {assign var=value value=$propvalue|escape}
                {else}
                    {assign var=value value=$propvalue}
                {/if}
            {elseif !isset($escaped)}
                {assign var=value value=$value|escape}
            {/if}

                        <td class="{$lrclass}" data-title="{$column->label}">
            {* Display column.
                A check is done here to adapt display, this is may not the best way to go
                but for notw, that works as excpected.
            *}
            {if not empty($value)}
                {if $column->field_id eq 'email_adh'}
                                <a href="mailto:{$value}">{$value}</a>
                {elseif $column->field_id eq 'tel_adh' or $column->field_id eq 'gsm_adh'}
                                <a href="tel:{$value}">{$value}</a>
                {elseif $column->field_id eq 'parent_id'}
                                <a href="{path_for name="member" data=["id" => $member->parent]}">{memberName id=$member->parent}</a>
                {elseif $column->field_id eq 'ddn_adh'}
                                {$value} {$member->getAge()}
                {else}
                                {$value}
                {/if}
            {/if}
                        </td>
        {/if}
    {/foreach}
                    {draw_actions class=$rclass member=$member login=$login plugin_actions=$plugin_actions}
                    </tr>
{foreachelse}
                    <tr><td colspan="{$galette_list|count}" class="emptylist">{_T string="No member has been found"}</td></tr>
{/foreach}
                </tbody>
            </table>
        </div>
{if $nb_members != 0 && ($login->isGroupManager() && $preferences->pref_bool_groupsmanagers_exports || $login->isAdmin() || $login->isStaff())}
        <div class="ui bottom attached segment">
            <div class="ui horizontal list">
                <span class="ui blue ribbon label">{_T string="For the selection:"}</span>
    {if $login->isAdmin() or $login->isStaff()}
                <div class="item">
                    <button type="submit" id="delete" name="delete" class="ui labeled icon tiny button">
                        <i class="user times icon"></i> {_T string="Delete"}
                    </button>
                </div>
                <div class="item">
                    <button type="submit" id="masschange" name="masschange" class="action ui labeled icon tiny button">
                        <i class="user edit icon"></i> {_T string="Mass change"}
                    </button>
                </div>
                <div class="item">
                    <button type="submit" id="masscontributions" name="masscontributions" class="action ui labeled icon tiny button">
                        <i class="ui cookie bite icon"></i> {_T string="Mass add contributions"}
                    </button>
                </div>
    {/if}
    {if $login->isAdmin() or $login->isStaff() or $login->isGroupManager() and $preferences->pref_bool_groupsmanagers_mailings}
        {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
                <div class="item">
                    <button type="submit" id="sendmail" name="mailing" class="ui labeled icon tiny button">
                        <i class="mail bulk icon"></i> {_T string="Mail"}
                    </button>
                </div>
        {/if}
    {/if}

    {if $login->isGroupManager() && $preferences->pref_bool_groupsmanagers_exports || $login->isAdmin() || $login->isStaff()}
                <div class="item">
                    <button type="submit" id="attendance_sheet" name="attendance_sheet" class="ui labeled icon tiny button">
                        <i class="file alternate icon"></i> {_T string="Attendance sheet"}
                    </button>
                </div>
                <div class="item">
                    <button type="submit" id="labels" name="labels" class="ui labeled icon tiny button">
                        <i class="address card icon"></i> {_T string="Generate labels"}
                    </button>
                </div>
                <div class="item">
                    <button type="submit" id="cards" name="cards" class="ui labeled icon tiny button">
                        <i class="id badge icon"></i> {_T string="Generate Member Cards"}
                    </button>
                </div>
                <div class="item">
                    <button type="submit" id="csv" name="csv" class="ui labeled icon tiny button">
                        <i class="file csv icon"></i> {_T string="Export as CSV"}
                    </button>
                </div>
    {/if}
    {if $plugin_batch_actions|@count != 0}
        {foreach from=$plugin_batch_actions key=plugin_name item=action}
            {include file=$action module_id=$plugin_name|replace:'batch_action_':''}
        {/foreach}
    {/if}
            </div>
        </div>
        <div class="ui basic center aligned fitted segment">
            <div class="ui pagination menu">
                <div class="header item">
                    {_T string="Pages:"}
                </div>
                {$pagination}
            </div>
        </div>
{/if}
            {include file="forms_types/csrf.tpl"}
        </form>
{if $nb_members != 0}
        <div id="legende" title="{_T string="Legend"}" class="ui modal">
            <div class="header">{_T string="Legend"}</div>
            <div class="content">
                <table class="ui stripped table">
                    <thead>
                        <tr>
                            <th class="" colspan="4">{_T string="Reading the list"}</th>
                        </tr>
                    <thead>
                    <tbody>
                        <tr>
                            <th class="back">{_T string="Name"}</th>
                            <td class="back">{_T string="Active account"}</td>
                            <th class="inactif back">{_T string="Name"}</th>
                            <td class="back">{_T string="Inactive account"}</td>
                        </tr>
                        <tr>
                            <th class="cotis-ok color-sample">&nbsp;</th>
                            <td class="back">{_T string="Membership in order"}</td>
                            <th class="cotis-soon color-sample">&nbsp;</th>
                            <td class="back">{_T string="Membership will expire soon (&lt;30d)"}</td>
                        </tr>
                        <tr>
                            <th class="cotis-never color-sample">&nbsp;</th>
                            <td class="back">{_T string="Never contributed"}</td>
                            <th class="cotis-late color-sample">&nbsp;</th>
                            <td class="back">{_T string="Lateness in fee"}</td>
                        </tr>
                    </tbody>
                </table>
                <table class="ui stripped table">
                    <thead>
                        <tr>
                            <th class="" colspan="4">{_T string="Actions"}</th>
                        </tr>
                    <thead>
                    <tbody>
                        <tr>
                            <th class="action">
                                <i class="ui user edit blue icon"></i>
                            </th>
                            <td class="back">{_T string="Modification"}</td>
                            <th>
                                <i class="ui cookie icon"></i>
                            </th>
                            <td class="back">{_T string="Contributions"}</td>
                        </tr>
                        <tr>
                            <th class="delete">
                                <i class="ui user times red icon"></i>
                            </th>
                            <td class="back">{_T string="Deletion"}</td>
                        </tr>
                    </tbody>
                </table>
                <table class="ui stripped table">
                    <thead>
                        <tr>
                            <th colspan="4">{_T string="User status/interactions"}</th>
                        </tr>
                    <thead>
                    <tbody>
                        <tr>
                            <th><i class="ui envelope outline teal icon"></i></th>
                            <td class="back">{_T string="Send an email"}</td>
                            <th><i class="ui building icon"></i></th>
                            <td class="back">{_T string="Is a company"}</td>
                        </tr>

                        <tr>
                            <th><i class="ui male icon"></i></th>
                            <td class="back">{_T string="Is a man"}</td>
                            <th><i class="ui female icon"></i></th>
                            <td class="back">{_T string="Is a woman"}</td>
                        </tr>
                        <tr>
                            <th><i class="ui user shield red icon"></i></th>
                            <td class="back">{_T string="Admin"}</td>
                            <th><i class="ui id card alternate orange icon"></i></th>
                            <td class="back">{_T string="Staff member"}</td>

                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="actions"><div class="ui labeled icon deny button"><i class="times icon"></i> {_T string="Close"}</div></div>
        </div>
{/if}
{/block}

{block name="javascripts"}
        <script type="text/javascript">
{if $nb_members != 0}
        var _checkselection = function() {
            var _checkeds = $('table.listing').find('input[type=checkbox]:checked').length;
            if ( _checkeds == 0 ) {
                var _el = $('<div id="pleaseselect" title="{_T string="No member selected" escape="js"}">{_T string="Please make sure to select at least one member from the list to perform this action." escape="js"}</div>');
                _el.appendTo('body').dialog({
                    modal: true,
                    buttons: {
                        Ok: function() {
                            $(this).dialog( "close" );
                        }
                    },
                    close: function(event, ui){
                        _el.remove();
                    }
                });
                return false;
            }
            return true;
        }
{/if}
        {* Use of Javascript to draw specific elements that are not relevant is JS is inactive *}
        $(function(){
{if $nb_members != 0}
            var _checklinks = '<div class="checkboxes ui basic horizontal segments"><div class="ui basic fitted segment"><a href="#" class="checkall ui blue tertiary button">{_T string="(Un)Check all" escape="js"}</a> | <a href="#" class="checkinvert ui blue tertiary button">{_T string="Invert selection" escape="js"}</a></div><div class="ui basic right aligned fitted segment"><a href="#" class="show_legend ui blue tertiary button">{_T string="Show legend" escape="js"}</a></div></div>';
            $('.listing').before(_checklinks);
            $('.listing').after(_checklinks);
            _bind_check();
            _bind_legend();

            $('.selection_menu *[type="submit"], .selection_menu *[type="button"]').click(function(event){
                if ( this.id == 'delete' || this.id == 'masschange' ) {
                    //mass removal is handled from 2 steps removal
                    //mass change is specifically handled below
                    return;
                }

                if (!_checkselection()) {
                    return false;
                } else {
    {if $existing_mailing eq true}
                    if (this.id == 'sendmail') {
                        var _el = $('<div id="existing_mailing" title="{_T string="Existing mailing" escape="js"}">{_T string="A mailing already exists. Do you want to create a new one or resume the existing?" escape="js"}</div>');
                        _el.appendTo('body').dialog({
                            modal: true,
                            hide: 'fold',
                            width: '25em',
                            height: 150,
                            close: function(event, ui){
                                _el.remove();
                            },
                            buttons: {
                                '{_T string="Resume"}': function() {
                                    $(this).dialog( "close" );
                                    location.href = '{path_for name="mailing"}';
                                },
                                '{_T string="New"}': function() {
                                    $(this).dialog( "close" );
                                    //add required controls to the form, change its action URI, and send it.
                                    var _form = $('#listform');
                                    _form.append($('<input type="hidden" name="mailing_new" value="true"/>'));
                                    _form.append($('<input type="hidden" name="mailing" value="true"/>'));
                                    _form.submit();
                                }
                            }
                        });
                        return false;
                    }
    {/if}
                    if (this.id == 'attendance_sheet') {
                        _attendance_sheet_details();
                        return false;
                    }

                    if (this.id == 'masscontributions') {
                        event.preventDefault();
                        $.ajax({
                            url: '{path_for name="batch-memberslist"}',
                            type: "POST",
                            data: {
                                ajax: true,
                                masscontributions: true,
                                member_sel: $('#listform input[type=\"checkbox\"]:checked').map(function(){
                                    return $(this).val();
                                }).get()
                            },
                            datatype: 'json',
                            {include file="js_loader.tpl"},
                            success: function(res){
                                var _res = $(res);
                                _bindmassres(_res);
                                $('body').append(_res);

                                _initTooltips('#mass_contributions');
                                _massCheckboxes('#mass_contributions');

                                _res.dialog({
                                    width: 'auto',
                                    modal: true,
                                    close: function(event, ui){
                                        $(this).dialog('destroy').remove()
                                    }
                                });
                            },
                            error: function() {
                                alert("{_T string="An error occurred :(" escape="js"}");
                            }
                        });
                    }

                    return true;
                }
            });
{/if}
            if ( _shq = $('#showhideqry') ) {
                _shq.click(function(){
                    $('#sql_qry').toggleClass('hidden');
                    return false;
                });
            }

            $('#savesearch').on('click', function(e) {
                e.preventDefault();

                var _el = $('<div id="savedsearch_details" title="{_T string="Search title" escape="js"}"><input type="text" name="search_title" id="search_title"/></div>');
                _el.appendTo('body').dialog({
                    modal: true,
                    hide: 'fold',
                    width: '40%',
                    height: 200,
                    close: function(event, ui){
                        _el.remove();
                    },
                    buttons: {
                        '{_T string="Ok" escape="js"}': function() {
                            var _form = $('#filtre');
                            var _data = _form.serialize();
                            _data = _data + "&search_title=" + $('#search_title').val();
                            $.ajax({
                                url: '{path_for name="saveSearch"}',
                                type: "POST",
                                data: _data,
                                datatype: 'json',
                                {include file="js_loader.tpl"},
                                success: function(res) {
                                    $.ajax({
                                        url: '{path_for name="ajaxMessages"}',
                                        method: "GET",
                                        success: function (message) {
                                            $('#asso_name').after(message);
                                        }
                                    });
                                }
                            });

                            $(this).dialog( "close" );
                        },
                        '{_T string="Cancel" escape="js"}': function() {
                            $(this).dialog( "close" );
                        }
                    }
                });
            });

        });
{if $nb_members != 0}
        {include file="js_removal.tpl"}
        {include file="js_removal.tpl" selector="#delete" deleteurl="'{path_for name="batch-memberslist"}'" extra_check="if (!_checkselection()) {ldelim}return false;{rdelim}" extra_data="delete: true, member_sel: $('#listform input[type=\"checkbox\"]:checked').map(function(){ return $(this).val(); }).get()" method="POST"}

        var _bindmassres = function(res) {
            res.find('#btncancel')
                .button()
                .on('click', function(e) {
                    e.preventDefault();
                    res.dialog('close');
                });

            res.find('input[type=submit]')
                .button();

            res.find('select:not(.nochosen)').selectize({
                maxItems: 1
            });
        }

        $('#masschange').off('click').on('click', function(event) {
            event.preventDefault();
            var _this = $(this);

            if (!_checkselection()) {
                return false;
            }
            $.ajax({
                url: '{path_for name="batch-memberslist"}',
                type: "POST",
                data: {
                    ajax: true,
                    masschange: true,
                    member_sel: $('#listform input[type=\"checkbox\"]:checked').map(function(){
                        return $(this).val();
                    }).get()
                },
                datatype: 'json',
                {include file="js_loader.tpl"},
                success: function(res){
                    var _res = $(res);
                    _bindmassres(_res);

                    _res.find('form').on('submit', function(e) {
                        e.preventDefault();
                        var _form = $(this);
                        var _data = _form.serialize();
                        $.ajax({
                            url: _form.attr('action'),
                            type: "POST",
                            data: _data,
                            datatype: 'json',
                            {include file="js_loader.tpl"},
                            success: function(html) {
                                var _html = $(html);
                                _bindmassres(_html);

                                $('#mass_change').remove();
                                $('body').append(_html);

                                //_initTooltips('#mass_change');
                                //_massCheckboxes('#mass_change');

                                _html.dialog({
                                    width: 'auto',
                                    modal: true,
                                    close: function(event, ui){
                                        $(this).dialog('destroy').remove()
                                    }
                                });

                                _html.find('form').on('submit', function(e) {
                                    e.preventDefault();
                                    var _form = $(this);
                                    var _data = _form.serialize();
                                    $.ajax({
                                        url: _form.attr('action'),
                                        type: "POST",
                                        data: _data,
                                        datatype: 'json',
                                        {include file="js_loader.tpl"},
                                        success: function(res) {
                                            if (res.success) {
                                                window.location.href = _form.find('input[name=redirect_uri]').val();
                                            } else {
                                                $.ajax({
                                                    url: '{path_for name="ajaxMessages"}',
                                                    method: "GET",
                                                    success: function (message) {
                                                        $('#asso_name').after(message);
                                                    }
                                                });
                                            }
                                        }
                                    });
                                });
                            },
                            error: function() {
                                alert("{_T string="An error occurred :(" escape="js"}");
                            }
                        });
                    });

                    $('body').append(_res);

                    //_initTooltips('#mass_change');
                    _massCheckboxes('#mass_change');

                    _res.dialog({
                        width: 'auto',
                        modal: true,
                        close: function(event, ui){
                            $(this).dialog('destroy').remove()
                        }
                    });
                },
                error: function() {
                    alert("{_T string="An error occurred :(" escape="js"}");
                }
            });
        });

        var _attendance_sheet_details = function(){
            var _selecteds = [];
            $('table.listing').find('input[type=checkbox]:checked').each(function(){
                _selecteds.push($(this).val());
            });
            $.ajax({
                url: '{path_for name="attendance_sheet_details"}',
                type: "POST",
                data: {
                    ajax: true,
                    selection: _selecteds
                },
                dataType: 'html',
                success: function(res){
                    var _el = $('<div id="attendance_sheet_details" title="{_T string="Attendance sheet details" escape="js"}"> </div>');
                    _el.appendTo('body').dialog({
                        modal: true,
                        hide: 'fold',
                        width: '60%',
                        height: 400,
                        close: function(event, ui){
                            _el.remove();
                        },
                        buttons: {
                            Ok: function() {
                                $('#sheet_details_form').submit();
                                $(this).dialog( "close" );
                            },
                            Cancel: function() {
                                $(this).dialog( "close" );
                            }
                        }
                    }).append(res);
                    /*$('#sheet_date').datepicker({
                        changeMonth: true,
                        changeYear: true,
                        showOn: 'button',
                        yearRange: 'c:c+5',
                        buttonText: '<i class="ui calendar alt icon"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                    });*/
                },
                error: function() {
                    alert("{_T string="An error occurred displaying attendance sheet details interface :(" escape="js"}");
                }
            });
        }
{/if}
    </script>
{/block}
