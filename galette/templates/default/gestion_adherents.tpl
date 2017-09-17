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
                        <a href="{path_for name="members" data=["option" => {_T string='order' domain="routes"}, "value" => "Galette\Repository\Members::ORDERBY_ID"|constant]}">
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
                        <a href="{path_for name="members" data=["option" => {_T string='order' domain="routes"}, "value" => "Galette\Repository\Members::ORDERBY_NAME"|constant]}">
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
                        <a href="{path_for name="members" data=["option" => {_T string='order' domain="routes"}, "value" => "Galette\Repository\Members::ORDERBY_NICKNAME"|constant]}">
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
                        <a href="{path_for name="members" data=["option" => {_T string='order' domain="routes"}, "value" => "Galette\Repository\Members::ORDERBY_STATUS"|constant]}">
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
                        <a href="{path_for name="members" data=["option" => {_T string='order' domain="routes"}, "value" => "Galette\Repository\Members::ORDERBY_FEE_STATUS"|constant]}">
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
                        <a href="{path_for name="members" data=["option" => {_T string='order' domain="routes"}, "value" => "Galette\Repository\Members::ORDERBY_MODIFDATE"|constant]}">
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
                        <img src="{base_url}/{$template_subdir}images/icon-company.png" alt="{_T string="[W]"}" width="16" height="16"/>
                    {elseif $member->isMan()}
                        <img src="{base_url}/{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
                    {elseif $member->isWoman()}
                        <img src="{base_url}/{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
                    {else}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                    {/if}
                    {if $member->email != ''}
                        <a href="mailto:{$member->email}"><img src="{base_url}/{$template_subdir}images/icon-mail.png" alt="{_T string="[Mail]"}" width="16" height="16"/></a>
                    {else}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                    {/if}
                    {if $member->website != ''}
                        <a href="{$member->website}"><img src="{base_url}/{$template_subdir}images/icon-website.png" alt="{_T string="[Website]"}" width="16" height="16"/></a>
                    {else}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                    {/if}
                    {if $member->isAdmin()}
                        <img src="{base_url}/{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="16" height="16"/>
                    {elseif $member->isStaff()}
                        <img src="{base_url}/{$template_subdir}images/icon-staff.png" alt="{_T string="[staff]"}" width="16" height="16"/>
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
                        <a href="{path_for name="editmember" data=["action" => {_T string="edit" domain="routes"}, "id" => $mid]}"><img src="{base_url}/{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16" title="{_T string="%membername: edit informations" pattern="/%membername/" replace=$member->sname}"/></a>
{if $login->isAdmin() or $login->isStaff()}
                        <a href="{path_for name="contributions" data=["type" => {_T string="contributions" domain="routes"}, "option" => {_T string="member" domain="routes"}, "value" => $member->id]}"><img src="{base_url}/{$template_subdir}images/icon-money.png" alt="{_T string="[$]"}" width="16" height="16" title="{_T string="%membername: contributions" pattern="/%membername/" replace=$member->sname}"/></a>
                        <a class="delete" href="{path_for name="removeMember" data=["id" => $member->id]}"><img src="{base_url}/{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16" title="{_T string="%membername: remove from database" pattern="/%membername/" replace=$member->sname}"/></a>
{/if}
{if $login->isSuperAdmin()}
                        <a href="{path_for name="impersonate" data=["id" => $mid]}"><img src="{base_url}/{$template_subdir}images/icon-impersonate.png" alt="{_T string="Impersonate"}" width="16" height="16" title="{_T string="Log in in as %membername" pattern="/%membername/" replace=$member->sname}"/></a>
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
            <li><input type="submit" id="delete" name="delete" value="{_T string="Delete"}"/></li>
        {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
            <li><input type="submit" id="sendmail" name="mailing" value="{_T string="Mail"}"/></li>
        {/if}
    {/if}
            <li>
                <input type="submit" id="attendance_sheet" name="attendance_sheet" value="{_T string="Attendance sheet"}"/>
            </li>
            <li><input type="submit" name="labels" value="{_T string="Generate labels"}"/></li>
            <li><input type="submit" name="cards" value="{_T string="Generate Member Cards"}"/></li>
    {if $login->isAdmin() or $login->isStaff()}
            <li><input type="submit" name="csv" value="{_T string="Export as CSV"}"/></li>
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
                <tr>
                    <th><img src="{base_url}/{$template_subdir}images/icon-male.png" alt="{_T string="Mister"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Man"}</td>
                    <th class="back">{_T string="Name"}</th>
                    <td class="back">{_T string="Active account"}</td>
                </tr>
                <tr>
                    <th><img src="{base_url}/{$template_subdir}images/icon-female.png" alt="{_T string="Miss"} / {_T string="Mrs"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Woman"}</td>
                    <th class="inactif back">{_T string="Name"}</th>
                    <td class="back">{_T string="Inactive account"}</td>
                </tr>
                <tr>
                    <th><img src="{base_url}/{$template_subdir}images/icon-company.png" alt="{_T string="Society"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Society"}</td>
                    <th class="cotis-never color-sample">&nbsp;</th>
                    <td class="back">{_T string="Never contributed"}</td>
                </tr>
                <tr>
                    <th><img src="{base_url}/{$template_subdir}images/icon-staff.png" alt="{_T string="[staff]"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Staff member"}</td>
                    <th class="cotis-ok color-sample">&nbsp;</th>
                    <td class="back">{_T string="Membership in order"}</td>
                </tr>
                <tr>
                    <th><img src="{base_url}/{$template_subdir}images/icon-star.png" alt="{_T string="Admin"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Admin"}</td>
                    <th class="cotis-soon color-sample">&nbsp;</th>
                    <td class="back">{_T string="Membership will expire soon (&lt;30d)"}</td>
                </tr>
                <tr>
                    <th><img src="{base_url}/{$template_subdir}images/icon-edit.png" alt="{_T string="Modify"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Modification"}</td>
                    <th class="cotis-late color-sample">&nbsp;</th>
                    <td class="back">{_T string="Lateness in fee"}</td>
                </tr>
                <tr>
                    <th><img src="{base_url}/{$template_subdir}images/icon-money.png" alt="{_T string="Contribution"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Contributions"}</td>
                    <th><img src="{base_url}/{$template_subdir}images/icon-mail.png" alt="{_T string="E-mail"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Send a mail"}</td>
                </tr>
                <tr>
                    <th><img src="{base_url}/{$template_subdir}images/icon-trash.png" alt="{_T string="Delete"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Deletion"}</td>
                    <th><img src="{base_url}/{$template_subdir}images/icon-website.png" alt="{_T string="Website"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Website URL"}</td>
                </tr>
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
            var _checklinks = '<div class="checkboxes"><span class="fleft"><a href="#" class="checkall">{_T string="(Un)Check all"}</a> | <a href="#" class="checkinvert">{_T string="Invert selection"}</a></span><a href="#" class="show_legend fright">{_T string="Show legend"}</a></div>';
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
                        buttonImage: '{base_url}/{$template_subdir}images/calendar.png',
                        buttonImageOnly: true,
                        yearRange: 'c:c+5',
                        buttonText: '{_T string="Select a date" escape="js"}'
                    });
                },
                error: function() {
                    alert("{_T string="An error occured displaying attendance sheet details interface :(" escape="js"}");
                }
            });
        }
{/if}
    </script>
{/block}
