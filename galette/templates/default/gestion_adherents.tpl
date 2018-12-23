{extends file="page.tpl"}

{block name="content"}
        <form action="{path_for name="filter-memberslist"}" method="post" id="filtre">
        <div id="listfilter">
{if !isset($adv_filters) || !$adv_filters}
            <label for="filter_str">{_T string="Search:"}&nbsp;</label>
            <input type="text" name="filter_str" id="filter_str" value="{$filters->filter_str}" type="search" placeholder="{_T string="Enter a value"}"/>&nbsp;
             {_T string="in:"}&nbsp;
            <select name="filter_field">
                {html_options options=$filter_field_options selected=$filters->field_filter}
            </select>
             {_T string="among:"}&nbsp;
            <select name="filter_membership" onchange="form.submit()">
                {html_options options=$filter_membership_options selected=$filters->membership_filter}
            </select>
            <select name="filter_account" onchange="form.submit()">
                {html_options options=$filter_accounts_options selected=$filters->account_status_filter}
            </select>
            <select name="group_filter" onchange="form.submit()">
                <option value="0">{_T string="Select a group"}</option>
{foreach from=$filter_groups_options item=group}
                <option value="{$group->getId()}"{if $filters->group_filter eq $group->getId()} selected="selected"{/if}>{$group->getIndentName()}</option>
{/foreach}
            </select>
            <input type="submit" class="inline" value="{_T string="Filter"}"/>
            <input type="submit" name="clear_filter" class="inline" value="{_T string="Clear filter"}"/>
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
                <input type="submit" name="adv_criterias" class="inline" value="{_T string="Change search criterias"}"/>
                <input type="submit" name="clear_filter" class="inline" value="{_T string="Clear filter"}"/>
                <br/>
                <a href="#" id="showhideqry">{_T string="Show/hide query"}</a>
            </p>
            <pre id="sql_qry" class="hidden">{$filters->query}</pre>
{/if}
        </div>
        <div class="infoline">
            {$nb_members} {if $nb_members != 1}{_T string="members"}{else}{_T string="member"}{/if}
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
                    <th class="left">
                        <a href="{path_for name="members" data=["option" => "order", "value" => "Galette\Repository\Members::ORDERBY_NAME"|constant]}">
                            {_T string="Name"}
                            {if $filters->orderby eq constant('galette\Repository\Members::ORDERBY_NAME')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                                    <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                                    <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="{path_for name="members" data=["option" => "order"}, "value" => "Galette\Repository\Members::ORDERBY_NICKNAME"|constant]}">
                            {_T string="Nickname"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_NICKNAME')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                                    <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                                    <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="{path_for name="members" data=["option" => "order", "value" => "Galette\Repository\Members::ORDERBY_STATUS"|constant]}">
                            {_T string="Status"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_STATUS')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                                    <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                                    <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
{if $login->isAdmin() or $login->isStaff()}
                    <th class="left">
                        <a href="{path_for name="members" data=["option" => "order", "value" => "Galette\Repository\Members::ORDERBY_FEE_STATUS"|constant]}">
                            {_T string="State of dues"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_FEE_STATUS')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                                    <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                                    <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="{path_for name="members" data=["option" => "order", "value" => "Galette\Repository\Members::ORDERBY_MODIFDATE"|constant]}">
                            {_T string="Modified"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_MODIFDATE')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                                    <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                                    <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
{/if}
                    <th class="actions_row">{_T string="Actions"}</th>
                </tr>
            </thead>
            <tbody>
{foreach from=$members item=member key=ordre}
    {assign var=rclass value=$member->getRowClass() }
                <tr>
{if $preferences->pref_show_id}
                    <td class="{$rclass} right" data-scope="id">{$member->id}</td>
{else}
                    <td class="{$rclass} right" data-scope="id">{$ordre+1+($filters->current_page - 1)*$numrows}</td>
{/if}
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
                            <span class="sr-only">{_T string="Is a women"}</span>
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
                        <a href="{path_for name="member" data=["id" => $member->id]}">{$member->sname}{if $member->company_name} ({$member->company_name}){/if}</a>
                    </td>
                    <td class="{$rclass} nowrap" data-title="{_T string="Nickname"}">{$member->nickname|htmlspecialchars}</td>
                    <td class="{$rclass} nowrap" data-title="{_T string="Status"}">{statusLabel id=$member->status}</td>
{if $login->isAdmin() or $login->isStaff()}
                    <td class="{$rclass}" data-title="{_T string="State of dues"}">{$member->getDues()}</td>
                    <td class="{$rclass}" data-title="{_T string="Modified"}">{$member->modification_date}</td>
{/if}
                    <td class="{$rclass} center nowrap actions_row">
                        <a
                            href="{path_for name="editmember" data=["action" => "edit", "id" => $mid]}"
                            class="tooltip action"
                        >
                            <i class="fas fa-user-edit fa-fw" aria-hidden="true"></i>
                            <span class="sr-only">{_T string="%membername: edit informations" pattern="/%membername/" replace=$member->sname}</span>
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
                            href="{path_for name="impersonate" data=["id" => $mid]}"
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
                </tr>
{foreachelse}
                <tr><td colspan="7" class="emptylist">{_T string="No member has been found"}</td></tr>
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
                        <td class="back">{_T string="Send a mail"}</td>
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

            _initTooltips('#listform');
{if $nb_members != 0}
            var _checklinks = '<div class="checkboxes"><span class="fleft"><a href="#" class="checkall tooltip"><i class="fas fa-check-square"></i> {_T string="(Un)Check all"}</a> | <a href="#" class="checkinvert tooltip"><i class="fas fa-exchange-alt"></i> {_T string="Invert selection"}</a></span><a href="#" class="show_legend fright">{_T string="Show legend"}</a></div>';
            $('.listing').before(_checklinks);
            $('.listing').after(_checklinks);
            _bind_check();
            _bind_legend();
            $('#nbshow').change(function() {
                this.form.submit();
            });
            $('.selection_menu input[type="submit"], .selection_menu input[type="button"]').click(function(){

                if ( this.id == 'delete' ) {
                    //mass removal is handled from 2 steps removal
                    return;
                }

                if (!_checkselection()) {
                    return false;
                } else {
    {if $existing_mailing eq true}
                    if (this.id == 'sendmail') {
                        var _el = $('<div id="existing_mailing" title="{_T string="Existing mailing"}">{_T string="A mailing already exists. Do you want to create a new one or resume the existing?"}</div>');
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
