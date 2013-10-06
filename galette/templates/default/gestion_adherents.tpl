        <form action="gestion_adherents.php" method="get" id="filtre">
        <div id="listfilter">
{if !$adv_filters}
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
                <option value="{$group->getId()}"{if $filters->group_filter eq $group->getId()} selected="selected"{/if}>{$group->getName()}</option>
{/foreach}
            </select>
            <input type="submit" class="inline" value="{_T string="Filter"}"/>
            <input type="submit" name="clear_filter" class="inline" value="{_T string="Clear filter"}"/>
            <div>
                {_T string="Members that have an email adress:"}
                <input type="radio" name="email_filter" id="filter_dc_email" value="{php}echo Galette\Repository\Members::FILTER_DC_EMAIL;{/php}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_DC_EMAIL')} checked="checked"{/if}>
                <label for="filter_dc_email" >{_T string="Don't care"}</label>
                <input type="radio" name="email_filter" id="filter_with_email" value="{php}echo Galette\Repository\Members::FILTER_W_EMAIL;{/php}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_W_EMAIL')} checked="checked"{/if}>
                <label for="filter_with_email" >{_T string="With"}</label>
                <input type="radio" name="email_filter" id="filter_without_email" value="{php}echo Galette\Repository\Members::FILTER_WO_EMAIL;{/php}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_WO_EMAIL')} checked="checked"{/if}>
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
        <table class="infoline">
            <tr>
                <td class="left">{$nb_members} {if $nb_members != 1}{_T string="members"}{else}{_T string="member"}{/if}</td>
                <td class="right">
                    <label for="nbshow">{_T string="Records per page:"}</label>
                    <select name="nbshow" id="nbshow">
                        {html_options options=$nbshow_options selected=$numrows}
                    </select>
                    <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                </td>
            </tr>
        </table>
        </form>
        <form action="gestion_adherents.php" method="post" id="listform">
        <table class="listing">
            <thead>
                <tr>
                    <th class="id_row">#</th>
                    <th class="left">
                        <a href="gestion_adherents.php?tri={php}echo Galette\Repository\Members::ORDERBY_NAME;{/php}">
                            {_T string="Name"}
                            {if $filters->orderby eq constant('galette\Repository\Members::ORDERBY_NAME')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="gestion_adherents.php?tri={php}echo Galette\Repository\Members::ORDERBY_NICKNAME;{/php}">
                            {_T string="Nickname"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_NICKNAME')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="gestion_adherents.php?tri={php}echo Galette\Repository\Members::ORDERBY_STATUS;{/php}">
                            {_T string="Status"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_STATUS')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
{if $login->isAdmin() or $login->isStaff()}
                    <th class="left">
                        <a href="gestion_adherents.php?tri={php}echo Galette\Repository\Members::ORDERBY_FEE_STATUS;{/php}">
                            {_T string="State of dues"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_FEE_STATUS')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="gestion_adherents.php?tri={php}echo Galette\Repository\Members::ORDERBY_MODIFDATE;{/php}">
                            {_T string="Modified"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_MODIFDATE')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
{/if}
                    <th class="actions_row">{_T string="Actions"}</th>
                </tr>
            </thead>
{if $nb_members != 0}
            <tfoot>
                <tr>
                    <td colspan="7" id="table_footer">
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
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td colspan="7" class="center">
                        {_T string="Pages:"}<br/>
                        <ul class="pages">{$pagination}</ul>
                    </td>
                </tr>
            </tfoot>
{/if}
            <tbody>
{foreach from=$members item=member key=ordre}
    {assign var=rclass value=$member->getRowClass() }
                <tr>
                    <td class="{$rclass} right">{$ordre+1+($filters->current_page - 1)*$numrows}</td>
                    <td class="{$rclass} nowrap username_row">
                        <input type="checkbox" name="member_sel[]" value="{$member->id}"/>
                    {if $member->isCompany()}
                        <img src="{$template_subdir}images/icon-company.png" alt="{_T string="[C]"}" width="16" height="16"/>
                    {elseif $member->isMan()}
                        <img src="{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
                    {elseif $member->isWoman()}
                        <img src="{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
                    {else}
                        <img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                    {/if}
                    {if $member->email != ''}
                        <a href="mailto:{$member->email}"><img src="{$template_subdir}images/icon-mail.png" alt="{_T string="[Mail]"}" width="16" height="16"/></a>
                    {else}
                        <img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                    {/if}
                    {if $member->isAdmin()}
                        <img src="{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="16" height="16"/>
                    {elseif $member->isStaff()}
                        <img src="{$template_subdir}images/icon-staff.png" alt="{_T string="[staff]"}" width="16" height="16"/>
                    {else}
                        <img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                    {/if}
                        <a href="voir_adherent.php?id_adh={$member->id}">{$member->sname}{if $member->company_name} ({$member->company_name}){/if}</a>
                    </td>
                    <td class="{$rclass} nowrap">{$member->nickname|htmlspecialchars}</td>
                    <td class="{$rclass} nowrap">{statusLabel id=$member->status}</td>
{if $login->isAdmin() or $login->isStaff()}
                    <td class="{$rclass}">{$member->getDues()}</td>
                    <td class="{$rclass}">{$member->modification_date}</td>
{/if}
                    <td class="{$rclass} center nowrap actions_row">
                        <a href="ajouter_adherent.php?id_adh={$member->id}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16" title="{_T string="%membername: edit informations" pattern="/%membername/" replace=$member->sname}"/></a>
{if $login->isAdmin() or $login->isStaff()}
                        <a href="gestion_contributions.php?id_adh={$member->id}"><img src="{$template_subdir}images/icon-money.png" alt="{_T string="[$]"}" width="16" height="16" title="{_T string="%membername: contributions" pattern="/%membername/" replace=$member->sname}"/></a>
                        <a onclick="return confirm('{_T string="Do you really want to delete this member from the base? This will also delete the history of his fees. You could instead disable the account.\\n\\nDo you still want to delete this member ?" escape="js"}')" href="gestion_adherents.php?sup={$member->id}"><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16" title="{_T string="%membername: remove from database" pattern="/%membername/" replace=$member->sname}"/></a>
{/if}
            {* If some additionnals actions should be added from plugins, we load the relevant template file
            We have to use a template file, so Smarty will do its work (like replacing variables). *}
            {if $plugin_actions|@count != 0}
              {foreach from=$plugin_actions item=action}
                {include file=$action}
              {/foreach}
            {/if}
                    </td>
                </tr>
{foreachelse}
                <tr><td colspan="7" class="emptylist">{_T string="No member has been found"}</td></tr>
{/foreach}
            </tbody>
        </table>
        </form>
{if $nb_members != 0}
        <div id="legende" title="{_T string="Legend"}">
            <h1>{_T string="Legend"}</h1>
            <table>
                <tr>
                    <th><img src="{$template_subdir}images/icon-male.png" alt="{_T string="Mister"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Man"}</td>
                    <th class="back">{_T string="Name"}</th>
                    <td class="back">{_T string="Active account"}</td>
                </tr>
                <tr>
                    <th><img src="{$template_subdir}images/icon-female.png" alt="{_T string="Miss"} / {_T string="Mrs."}" width="16" height="16"/></th>
                    <td class="back">{_T string="Woman"}</td>
                    <th class="inactif back">{_T string="Name"}</th>
                    <td class="back">{_T string="Inactive account"}</td>
                </tr>
                <tr>
                    <th><img src="{$template_subdir}images/icon-company.png" alt="{_T string="Society"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Society"}</td>
                    <th class="cotis-never color-sample">&nbsp;</th>
                    <td class="back">{_T string="Never contributed"}</td>
                </tr>
                <tr>
                    <th><img src="{$template_subdir}images/icon-staff.png" alt="{_T string="[staff]"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Staff member"}</td>
                    <th class="cotis-ok color-sample">&nbsp;</th>
                    <td class="back">{_T string="Membership in order"}</td>
                </tr>
                <tr>
                    <th><img src="{$template_subdir}images/icon-star.png" alt="{_T string="Admin"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Admin"}</td>
                    <th class="cotis-soon color-sample">&nbsp;</th>
                    <td class="back">{_T string="Membership will expire soon (&lt;30d)"}</td>
                </tr>
                <tr>
                    <th><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="Modify"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Modification"}</td>
                    <th class="cotis-late color-sample">&nbsp;</th>
                    <td class="back">{_T string="Lateness in fee"}</td>
                </tr>
                <tr>
                    <th><img src="{$template_subdir}images/icon-money.png" alt="{_T string="Contribution"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Contributions"}</td>
                </tr>
                <tr>
                    <th><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="Delete"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Deletion"}</td>
                </tr>
                <tr>
                    <th><img src="{$template_subdir}images/icon-mail.png" alt="{_T string="E-mail"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Send a mail"}</td>
                </tr>
            </table>
        </div>
{/if}
        <script type="text/javascript">
{if $nb_members != 0}
        var _is_checked = true;
        var _bind_check = function(){
            $('#checkall').click(function(){
                $('table.listing :checkbox[name="member_sel[]"]').each(function(){
                    this.checked = _is_checked;
                });
                _is_checked = !_is_checked;
                return false;
            });
            $('#checkinvert').click(function(){
                $('table.listing :checkbox[name="member_sel[]"]').each(function(){
                    this.checked = !$(this).is(':checked');
                });
                return false;
            });
        }
{/if}
        {* Use of Javascript to draw specific elements that are not relevant is JS is inactive *}
        $(function(){
{if $nb_members != 0}
            $('#table_footer').parent().before('<tr><td id="checkboxes" colspan="4"><span class="fleft"><a href="#" id="checkall">{_T string="(Un)Check all"}</a> | <a href="#" id="checkinvert">{_T string="Invert selection"}</a></span></td></tr>');
            _bind_check();
            $('#nbshow').change(function() {
                this.form.submit();
            });
            $('#checkboxes').after('<td class="right" colspan="3"><a href="#" id="show_legend">{_T string="Show legend"}</a></td>');
            $('#legende h1').remove();
            $('#legende').dialog({
                autoOpen: false,
                modal: true,
                hide: 'fold',
                width: '40%'
            }).dialog('close');

            $('#show_legend').click(function(){
                $('#legende').dialog('open');
                return false;
            });
            $('.selection_menu input[type="submit"], .selection_menu input[type="button"]').click(function(){
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
                                    location.href = 'mailing_adherents.php';
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

                    if ( this.id == 'delete' ) {
                        return confirm('{_T string="Do you really want to delete all selected accounts (and related contributions)?" escape="js"}');
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
        var _attendance_sheet_details = function(){
            var _selecteds = [];
            $('table.listing').find('input[type=checkbox]:checked').each(function(){
                _selecteds.push($(this).val());
            });
            $.ajax({
                url: 'ajax_attendance_sheet_details.php',
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
                        buttonImage: '{$template_subdir}images/calendar.png',
                        buttonImageOnly: true,
                        yearRange: 'c:c+5'
                    });
                },
                error: function() {
                    alert("{_T string="An error occured displaying attendance sheet details interface :(" escape="js"}");
                }
            });
        }
{/if}
        </script>
