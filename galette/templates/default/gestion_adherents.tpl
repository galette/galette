{extends file="page.tpl"}


{function name=draw_actions}
                    <td class="{$rclass} center nowrap actions_row">
                        <a
                            href="{path_for name="editMember" data=["id" => $member->id]}"
                            class="tooltip action"
                        >
                            <i class="fas fa-user-edit fa-fw" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="%membername: edit information" pattern="/%membername/" replace=$member->sname}</span>
                        </a>
{if $login->isAdmin() or $login->isStaff()}
                        <a
                            href="{path_for name="contributions" data=["type" => "contributions", "option" => "member", "value" => $member->id]}"
                            class="tooltip"
                        >
                            <i class="fas fa-cookie fa-fw" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="%membername: contributions" pattern="/%membername/" replace=$member->sname}</span>
                        </a>
                        <a
                            href="{path_for name="removeMember" data=["id" => $member->id]}"
                            class="delete tooltip"
                        >
                            <i class="fas fa-user-times fa-fw" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="%membername: remove from database" pattern="/%membername/" replace=$member->sname}</span>
                        </a>
{/if}
{if $login->isSuperAdmin()}
                        <a
                            href="{path_for name="impersonate" data=["id" => $member->id]}"
                            class="tooltip"
                        >
                            <i class="fas fa-user-secret fa-fw" aria-hidden="true"></i>
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
        <form action="{path_for name="filter-memberslist"}" method="post" id="filtre">
        <div id="listfilter">
{if !isset($adv_filters) || !$adv_filters}
            <label for="filter_str">{_T string="Search:"}&nbsp;</label>
            <input type="text" name="filter_str" id="filter_str" value="{$filters->filter_str}" type="search" placeholder="{_T string="Enter a value"}"/>&nbsp;
             {_T string="in:"}&nbsp;
            <select name="field_filter">
                {html_options options=$field_filter_options selected=$filters->field_filter}
            </select>
             {_T string="among:"}&nbsp;
            <select name="membership_filter" onchange="form.submit()">
                {html_options options=$membership_filter_options selected=$filters->membership_filter}
            </select>
            <select name="filter_account" onchange="form.submit()">
                {html_options options=$filter_accounts_options selected=$filters->filter_account}
            </select>
            <select name="group_filter" onchange="form.submit()">
                <option value="0">{_T string="Select a group"}</option>
    {foreach from=$filter_groups_options item=group}
                <option value="{$group->getId()}"{if $filters->group_filter eq $group->getId()} selected="selected"{/if}>{$group->getIndentName()}</option>
    {/foreach}
            </select>
            <button type="submit"  class="tooltip action" title="{_T string="Apply filters"}" name="filter">
                <i class="fa fa-search"></i>
                {_T string="Filter"}
            </button>
            <button type="submit"  class="tooltip action" title="{_T string="Save selected criteria"}" name="savesearch" id="savesearch">
                <i class="fa fa-fw fa-save"></i>
                {_T string="Save"}
            </button>
            <input type="submit" name="clear_filter" class="inline tooltip" value="{_T string="Clear filter"}" title="{_T string="Reset all filters to defaults"}"/>
            <div>
                {_T string="Members that have an email address:"}
                <input type="radio" name="email_filter" id="filter_dc_email" value="{Galette\Repository\Members::FILTER_DC_EMAIL}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_DC_EMAIL')} checked="checked"{/if}>
                <label for="filter_dc_email" >{_T string="Don't care"}</label>
                <input type="radio" name="email_filter" id="filter_with_email" value="{Galette\Repository\Members::FILTER_W_EMAIL}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_W_EMAIL')} checked="checked"{/if}>
                <label for="filter_with_email" >{_T string="With"}</label>
                <input type="radio" name="email_filter" id="filter_without_email" value="{Galette\Repository\Members::FILTER_WO_EMAIL}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_WO_EMAIL')} checked="checked"{/if}>
                <label for="filter_without_email" >{_T string="Without"}</label>
            </div>
{else}
            <p>
                <strong>{_T string="Advanced search mode"}</strong>
                <button type="submit" class="tooltip action" title="{_T string="Change search criteria"}" name="adv_criteria">
                    <i class="fa fa-edit"></i>
                    {_T string="Change criteria"}
                </button>
                <button type="submit"  class="tooltip action" title="{_T string="Save current advanced search criteria"}" name="savesearch" id="savesearch">
                    <i class="fa fa-fw fa-save"></i>
                    {_T string="Save"}
                </button>
                <input type="hidden" name="advanced_search" value="1"/>
                <input type="submit" name="clear_filter" class="inline tooltip" value="{_T string="Clear filter"}" title="{_T string="Reset all filters to defaults"}"/>
                <br/>
                <a href="#" id="showhideqry">{_T string="Show/hide query"}</a>
            </p>
            <pre id="sql_qry" class="hidden">{$filters->query}</pre>
{/if}
        </div>
        <div class="infoline">
            {_T string="%count member" plural="%count members" count=$nb_members pattern="/%count/" replace=$nb_members}
            <div class="fright">
                <label for="nbshow">{_T string="Records per page:"}</label>
                <select name="nbshow" id="nbshow">
                    {html_options options=$nbshow_options selected=$numrows}
                </select>
                <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
            </div>
        </div>
        </form>
        <form action="{path_for name="batch-memberslist"}" method="post" id="listform">

        <table class="listing">
            <thead>
                <tr>
{foreach item=column from=$galette_list}
    {if $column->field_id eq 'id_adh'}
        {if $preferences->pref_show_id}
                    <th class="id_row">
                        <a href="{path_for name="members" data=["option" => "order", "value" => "Galette\Repository\Members::ORDERBY_ID"|constant]}">
                            {_T string="Mbr num"}
                            {if $filters->orderby eq constant('galette\Repository\Members::ORDERBY_ID')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                            <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                            <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
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
                                    <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                                    <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
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
                        <span class="tooltip">
                            <img src="{base_url}/{$template_subdir}images/icon-company.png" alt="" width="16" height="16"/>
                            <span class="sr-only">{_T string="Is a company"}</span>
                        </span>
            {elseif $member->isMan()}
                        <span class="tooltip">
                            <img src="{base_url}/{$template_subdir}images/icon-male.png" alt="" width="16" height="16"/>
                            <span class="sr-only">{_T string="Is a man"}</span>
                        </span>
            {elseif $member->isWoman()}
                        <span class="tooltip">
                            <img src="{base_url}/{$template_subdir}images/icon-female.png" alt="" width="16" height="16"/>
                            <span class="sr-only">{_T string="Is a woman"}</span>
                        </span>
            {else}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
            {/if}
            {if $member->email != ''}
                        <a href="mailto:{$member->email}" class="tooltip">
                            <img src="{base_url}/{$template_subdir}images/icon-mail.png" alt="" width="16" height="16"/>
                            <span class="sr-only">{_T string="Mail"}</span>
                        </a>
            {else}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
            {/if}
            {if $member->website != ''}
                        <a href="{$member->website}" class="tooltip">
                            <img src="{base_url}/{$template_subdir}images/icon-website.png" alt="" width="16" height="16"/>
                            <span class="sr-only">{_T string="Website"}<span>
                        </a>
            {else}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
            {/if}
            {if $member->isAdmin()}
                        <span class="tooltip">
                            <img src="{base_url}/{$template_subdir}images/icon-star.png" alt="" width="16" height="16"/>
                            <span class="sr-only">{_T string="Admin"}</span>
                        </span>
            {elseif $member->isStaff()}
                        <span class="tooltip">
                            <img src="{base_url}/{$template_subdir}images/icon-staff.png" alt="" width="16" height="16"/>
                            <span class="sr-only">{_T string="Staff member"}</span>
                        </span>
            {else}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
            {/if}
                        {assign var="mid" value=$member->id}
                        <a href="{path_for name="member" data=["id" => $member->id]}">{$member->sname}{if $member->company_name} ({$member->company_name|escape}){/if}</a>
                    </td>
        {else}
            {assign var="lrclass" value=$rclass}
            {assign var="propname" value=$column->propname}
            {assign var=value value=$member->$propname|escape}

            {if $column->field_id eq 'nom_adh'}
                {assign var="value" value=$member->sfullname}
            {elseif $column->field_id eq 'pseudo_adh'}
                {assign var="lrclass" value="$rclass nowrap"}
                {assign var=value value=$member->$propname|escape}
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
                    <td class="{$lrclass}" data-title="{$column->label}">
            {* Display column.
                A check is done here to adapt display, this is may not the best way to go
                but for notw, that works as excpected.
            *}
            {if not empty($value)}
                {if $column->field_id eq 'email_adh' or $column->field_id eq 'msn_adh'}
                                <a href="mailto:{$value}">{$value}</a>
                {elseif $column->field_id eq 'tel_adh' or $column->field_id eq 'gsm_adh'}
                                <a href="tel:{$value}">{$value}</a>
                {elseif $column->field_id eq 'url_adh'}
                                <a href="{$value}">{$value}</a>
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
                {* colspan +1 for actions column *}
                <tr><td colspan="{$galette_list|count + 1}" class="emptylist">{_T string="No member has been found"}</td></tr>
{/foreach}
            </tbody>
        </table>
{if $nb_members != 0}
        <div class="center cright">
            {_T string="Pages:"}<br/>
            <ul class="pages">{$pagination}</ul>
        </div>
        <ul class="selection_menu">
            <li>{_T string="For the selection:"}</li>
    {if $login->isAdmin() or $login->isStaff()}
            <li>
                <button type="submit" id="delete" name="delete">
                    <i class="fas fa-user-times fa-fw"></i> {_T string="Delete"}
                </button>
            </li>
            <li>
                <button type="submit" id="masschange" name="masschange" class="action">
                    <i class="fas fa-user-edit fa-fw"></i> {_T string="Mass change"}
                </button>
            </li>
        {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
            <li>
                <button type="submit" id="sendmail" name="mailing">
                    <i class="fas fa-mail-bulk fa-fw"></i> {_T string="Mail"}
                </button>
            </li>
        {/if}
    {/if}
            <li>
                <button type="submit" id="attendance_sheet" name="attendance_sheet">
                    <i class="fas fa-file-alt fa-fw"></i> {_T string="Attendance sheet"}
                </button>
            </li>
            <li>
                <button type="submit" id="labels" name="labels">
                    <i class="far fa-address-card fa-fw"></i> {_T string="Generate labels"}
                </button>
            </li>
            <li>
                <button type="submit" id="cards" name="cards">
                    <i class="fas fa-id-badge fa-fw"></i> {_T string="Generate Member Cards"}
                </button>
            </li>
    {if $login->isAdmin() or $login->isStaff()}
            <li>
                <button type="submit" id="csv" name="csv">
                    <i class="fas fa-file-csv fa-fw"></i> {_T string="Export as CSV"}
                </button>
            </li>
    {/if}
    {if $plugin_batch_actions|@count != 0}
        {foreach from=$plugin_batch_actions key=plugin_name item=action}
            {include file=$action module_id=$plugin_name|replace:'batch_action_':''}
        {/foreach}
    {/if}
        </ul>
{/if}

        </form>
{if $nb_members != 0}
        <div id="legende" title="{_T string="Legend"}">
            <h1>{_T string="Legend"}</h1>
            <table>
                <tbody>
                    <tr>
                        <th class="" colspan="4">{_T string="Reading the list"}</th>
                    </tr>
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
                <tbody>
                    <tr>
                        <th class="" colspan="4">{_T string="Actions"}</th>
                    </tr>
                    <tr>
                        <th class="action">
                            <i class="fas fa-user-edit fa-fw"></i>
                        </th>
                        <td class="back">{_T string="Modification"}</td>
                        <th>
                            <i class="fas fa-cookie fa-fw"></i>
                        </th>
                        <td class="back">{_T string="Contributions"}</td>
                    </tr>
                    <tr>
                        <th class="delete">
                            <i class="fas fa-user-times fa-fw"></i>
                        </th>
                        <td class="back">{_T string="Deletion"}</td>
                    </tr>
                </tbody>
                <tbody>
                    <tr>
                        <th colspan="4">{_T string="User status/interactions"}</th>
                    </tr>
                    <tr>
                        <th><img src="{base_url}/{$template_subdir}images/icon-mail.png" alt="{_T string="Mail"}" width="16" height="16"/></th>
                        <td class="back">{_T string="Send an email"}</td>
                        <th><img src="{base_url}/{$template_subdir}images/icon-website.png" alt="{_T string="Website"}" width="16" height="16"/></th>
                        <td class="back">{_T string="Visit website"}</td>
                    </tr>

                    <tr>
                        <th><img src="{base_url}/{$template_subdir}images/icon-male.png" alt="{_T string="Is a man"}" width="16" height="16"/></th>
                        <td class="back">{_T string="Is a man"}</td>
                        <th><img src="{base_url}/{$template_subdir}images/icon-female.png" alt="{_T string="Is a woman"}" width="16" height="16"/></th>
                        <td class="back">{_T string="Is a woman"}</td>
                    </tr>
                    <tr>
                        <th><img src="{base_url}/{$template_subdir}images/icon-company.png" alt="{_T string="Is a company"}" width="16" height="16"/></th>
                        <td class="back">{_T string="Is a company"}</td>
                    </tr>
                    <tr>
                        <th><img src="{base_url}/{$template_subdir}images/icon-star.png" alt="{_T string="Admin"}" width="16" height="16"/></th>
                        <td class="back">{_T string="Admin"}</td>
                        <th><img src="{base_url}/{$template_subdir}images/icon-staff.png" alt="{_T string="Staff member"}" width="16" height="16"/></th>
                        <td class="back">{_T string="Staff member"}</td>

                    </tr>
                </tbody>
            </table>
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
            var _checklinks = '<div class="checkboxes"><span class="fleft"><a href="#" class="checkall tooltip"><i class="fas fa-check-square"></i> {_T string="(Un)Check all" escape="js"}</a> | <a href="#" class="checkinvert tooltip"><i class="fas fa-exchange-alt"></i> {_T string="Invert selection" escape="js"}</a></span><a href="#" class="show_legend fright">{_T string="Show legend" escape="js"}</a></div>';
            $('.listing').before(_checklinks);
            $('.listing').after(_checklinks);
            _bind_check();
            _bind_legend();

            $('.selection_menu *[type="submit"], .selection_menu *[type="button"]').click(function(){
                if ( this.id == 'delete' ) {
                    //mass removal is handled from 2 steps removal
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

                                _initTooltips('#mass_change');
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

                    _initTooltips('#mass_change');
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
                    $('#sheet_date').datepicker({
                        changeMonth: true,
                        changeYear: true,
                        showOn: 'button',
                        yearRange: 'c:c+5',
                        buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                    });
                },
                error: function() {
                    alert("{_T string="An error occurred displaying attendance sheet details interface :(" escape="js"}");
                }
            });
        }
{/if}
    </script>
{/block}
