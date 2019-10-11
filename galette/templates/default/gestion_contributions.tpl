{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}
{block name="content"}
        <form action="{path_for name="payments_filter" data=["type" => "contributions"]}" method="post" id="filtre" class="ui form">
        <div class="ui segment">
            <div class="four fields">
                <div class="field">
                    <label for="date_field_filter">{_T string="Show contributions by"}</label>
                    <select name="date_field_filter" id="date_field_filter" class="ui search dropdown">
                        <option value="{Galette\Filters\ContributionsList::DATE_BEGIN}"{if $filters->date_field eq constant('Galette\Filters\ContributionsList::DATE_BEGIN')} selected="selected"{/if}>{_T string="Begin"}</option>
                        <option value="{Galette\Filters\ContributionsList::DATE_END}"{if $filters->date_field eq constant('Galette\Filters\ContributionsList::DATE_END')} selected="selected"{/if}>{_T string="End"}</option>
                        <option value="{Galette\Filters\ContributionsList::DATE_RECORD}"{if $filters->date_field eq constant('Galette\Filters\ContributionsList::DATE_RECORD')} selected="selected"{/if}>{_T string="Record"}</option>
                    </select>
                </div>
                <div class="two fields">
                    <div class="field">
                        <label for="start_date_filter">{_T string="since"}</label>
                        <div class="ui calendar" id="contrib-rangestart">
                            <div class="ui input left icon">
                                <i class="calendar icon"></i>
                                <input placeholder="{_T string="jj/mm/yyyy"}" type="text" name="start_date_filter" id="start_date_filter" maxlength="10" size="10" value="{$filters->start_date_filter}"/>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label for="end_date_filter">{_T string="until"}</label>
                        <div class="ui calendar" id="contrib-rangeend">
                            <div class="ui input left icon">
                                <i class="calendar icon"></i>
                                <input placeholder="{_T string="jj/mm/yyyy"}" type="text" name="end_date_filter" id="end_date_filter" maxlength="10" size="10" value="{$filters->end_date_filter}"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="field">
                    {include file="forms_types/payment_types.tpl"
                        current=$filters->payment_type_filter varname="payment_type_filter"
                        show_inline=""
                        classname=""
                        empty=['value' => -1, 'label' => {_T string="Select"}]
                    }
                </div>
                <div class="actions center aligned field">
                    <input type="submit" class="ui button" value="{_T string="Filter"}"/>
                    <input type="submit" name="clear_filter" class="ui button" value="{_T string="Clear filter"}"/>
                </div>
            </div>
        </div>
{if isset($member)}
        <div class="{$member->getRowClass()}{if not $member->isActive()} inactive{/if} ui center aligned segment ">
            {$member->getDues()}
        </div>
{/if}
{if isset($member) && $mode neq 'ajax'}
        <div class="ui basic left aligned fitted segment">
            <div class="ui borderless stackable menu">
                <div class="header item">
    {if $login->isAdmin() or $login->isStaff()}
                <div class="ui image large label">
                    <a
                        href="{path_for name="contributions" data=["type" => "contributions", "option" => "member", "value" => "all"]}"
                        class="tooltip"
                    >
                        <i class="icon eraser"></i>
                        <span class="sr-only">{_T string="Show all members contributions"}</span>
                    </a>
    {/if}
                    {$member->sname}
                </div>
                </div>
    {if not $member->isActive() } ({_T string="Inactive"}){/if}
    {if $login->isAdmin() or $login->isStaff()}
                <div class="item">
                    <a href="{path_for name="member" data=["id" => $member->id]}" class="ui button">{_T string="See member profile"}</a>
                </div>
                <div class="item">
                    <a href="{path_for name="contribution" data=["type" => "fee", "action" => "add"]}?id_adh={$member->id}" class="ui button">{_T string="Add a membership fee"}</a>
                </div>
                <div class="item">
                    <a href="{path_for name="contribution" data=["type" => "donation", "action" => "add"]}?id_adh={$member->id}" class="ui button">{_T string="Add a donation"}</a>
                </div>
    {/if}
        </div>
{/if}
        <div class="infoline">
            <div class="ui basic horizontal segments">
                <div class="ui basic fitted segment">
                    <div class="ui label">{$nb} {if $nb != 1}{_T string="contributions"}{else}{_T string="contribution"}{/if}</div>
                </div>
                <div class="ui basic right aligned fitted segment">
                    <div class="inline field">
{if $mode eq 'ajax'}
                        <input type="hidden" name="ajax" value="true"/>
                        <input type="hidden" name="max_amount" value="{$filters->max_amount}"/>
{/if}
                        <label for="nbshow">{_T string="Records per page:"}</label>
                        <select name="nbshow" id="nbshow" class="ui dropdown">
                            {html_options options=$nbshow_options selected=$numrows}
                        </select>
                        <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                    </div>
                </div>
            </div>
        </div>
        </form>
        <form action="" method="post" id="listform" class="ui form">
        <table class="listing ui celled table">
            <thead>
                <tr>
                    <th class="id_row">#</th>
                    <th class="left date_row">
                        <a href="{path_for name="contributions" data=["type" => "contributions", "option" => "order", "value" => "Galette\Filters\ContributionsList::ORDERBY_DATE"|constant]}">{_T string="Date"}
                        {if $filters->orderby eq constant('Galette\Filters\ContributionsList::ORDERBY_DATE')}
                            {if $filters->ordered eq constant('Galette\Filters\ContributionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="left date_row">
                        <a href="{path_for name="contributions" data=["type" => "contributions", "option" => "order", "value" => "Galette\Filters\ContributionsList::ORDERBY_BEGIN_DATE"|constant]}">{_T string="Begin"}
                        {if $filters->orderby eq constant('Galette\Filters\ContributionsList::ORDERBY_BEGIN_DATE')}
                            {if $filters->ordered eq constant('Galette\Filters\ContributionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="left date_row">
                        <a href="{path_for name="contributions" data=["type" => "contributions", "option" => "order", "value" => "Galette\Filters\ContributionsList::ORDERBY_END_DATE"|constant]}">{_T string="End"}
                        {if $filters->orderby eq constant('Galette\Filters\ContributionsList::ORDERBY_END_DATE')}
                            {if $filters->ordered eq constant('Galette\Filters\ContributionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
{if ($login->isAdmin() or $login->isStaff()) and !isset($member)}
                    <th class="left">
                        <a href="{path_for name="contributions" data=["type" => "contributions", "option" => "order", "value" => "Galette\Filters\ContributionsList::ORDERBY_MEMBER"|constant]}">{_T string="Member"}
                        {if $filters->orderby eq constant('Galette\Filters\ContributionsList::ORDERBY_MEMBER')}
                            {if $filters->ordered eq constant('Galette\Filters\ContributionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
{/if}
                    <th class="left">
                        <a href="{path_for name="contributions" data=["type" => "contributions", "option" => "order", "value" => "Galette\Filters\ContributionsList::ORDERBY_TYPE"|constant]}">{_T string="Type"}
                        {if $filters->orderby eq constant('Galette\Filters\ContributionsList::ORDERBY_TYPE')}
                            {if $filters->ordered eq constant('Galette\Filters\ContributionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="{path_for name="contributions" data=["type" => "contributions", "option" => "order", "value" => "Galette\Filters\ContributionsList::ORDERBY_AMOUNT"|constant]}">{_T string="Amount"}
                        {if $filters->orderby eq constant('Galette\Filters\ContributionsList::ORDERBY_AMOUNT')}
                            {if $filters->ordered eq constant('Galette\Filters\ContributionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="{path_for name="contributions" data=["type" => "contributions", "option" => "order", "value" => "Galette\Filters\ContributionsList::ORDERBY_PAYMENT_TYPE"|constant]}">{_T string="Payment type"}
                        {if $filters->orderby eq constant('Galette\Filters\ContributionsList::ORDERBY_PAYMENT_TYPE')}
                            {if $filters->ordered eq constant('Galette\Filters\ContributionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="left">
                        {_T string="Duration"}
                    </th>
{if ($login->isAdmin() or $login->isStaff()) and $mode neq 'ajax'}
                    <th class="nowrap actions_row">{_T string="Actions"}</th>
{/if}
                </tr>
            </thead>
{if $nb != 0}
            <tfoot>
                <tr>
                    <td class="right" colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}10{elseif $login->isAdmin() or $login->isStaff()}9{else}8{/if}">
                        {_T string="Found contributions total %f" pattern="/%f/" replace=$contribs->getSum()}
                    </td>
                </tr>
            </tfoot>
{/if}
            <tbody>
{foreach from=$list item=contribution key=ordre}
    {assign var="mid" value=$contribution->member}
    {assign var="cclass" value=$contribution->getRowClass()}
                <tr{if $mode eq 'ajax'} class="contribution_row" id="row_{$contribution->id}"{/if}>
                    <td class="{$cclass} nowrap" data-scope="row">
                        {if $mode neq 'ajax'}
                            <input type="checkbox" name="contrib_sel[]" value="{$contribution->id}"/>
                        {else}
                            <input type="hidden" name="contrib_id" value="{$contribution->id}"/>
                        {/if}
    {if $preferences->pref_show_id}
                        {$contribution->id}
    {else}
                        {$ordre+1+($filters->current_page - 1)*$numrows}
    {/if}
                        <span class="row-title">
                            <a href="{path_for name="contribution" data=["type" => $ctype, "action" => "edit", "id" => $contribution->id]}">
                                {_T string="Contribution %id" pattern="/%id/" replace=$contribution->id}
                            </a>
                        </span>
        {if $contribution->isTransactionPart() }
                        <a
                            href="{path_for name="transaction" data=["action" => "edit", "id" => $contribution->transaction->id]}"
                            class="tooltip"
                        >
                            <i class="fas fa-link"></i>
                            <span class="sr-only">{_T string="Transaction: %s" pattern="/%s/" replace=$contribution->transaction->description}</span>
                        </a>
        {else}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png"
                            alt=""
                            width="16"
                            height="16"/>
        {/if}
                    </td>
                    <td class="{$cclass} nowrap" data-title="{_T string="Date"}">{$contribution->date}</td>
                    <td class="{$cclass} nowrap" data-title="{_T string="Begin"}">{$contribution->begin_date}</td>
                    <td class="{$cclass} nowrap" data-title="{_T string="End"}">{$contribution->end_date}</td>
    {if ($login->isAdmin() or $login->isStaff()) && !isset($member)}
                    <td class="{$cclass}" data-title="{_T string="Member"}">
        {if $contribution->filtre_cotis_adh eq ""}
                        <a href="{path_for name="contributions" data=["type" => "contributions", "option" => "member", "value" => $mid]}">{if isset($member)}{$member->sname}{else}{memberName id="$mid"}{/if}</a>
        {else}
                        <a href="{path_for name="member" data=["id" => $mid]}">{if isset($member)}{$member->sname}{else}{memberName id="$mid"}{/if}</a>
        {/if}
                    </td>
    {/if}
                    <td class="{$cclass}" data-title="{_T string="Type"}">{$contribution->type->libelle}</td>
                    <td class="{$cclass} nowrap" data-title="{_T string="Amount"}">{$contribution->amount}</td>
                    <td class="{$cclass} nowrap" data-title="{_T string="Payment type"}">{$contribution->spayment_type}</td>
                    <td class="{$cclass} nowrap" data-title="{_T string="Duration"}">{$contribution->duration}</td>
    {if ($login->isAdmin() or $login->isStaff()) and $mode neq 'ajax'}
                    <td class="{$cclass} center nowrap">
                        <a
                            href="{path_for name="printContribution" data=["id" => $contribution->id]}"
                            class="tooltip"
                        >
                            <i class="fas fa-file-pdf"></i>
                            <span class="sr-only">{_T string="Print an invoice or a receipt (depending on contribution type)"}</span>
                        </a>
                        {if $contribution->isCotis()}
                            {assign var="ctype" value="fee"}
                        {else}
                            {assign var="ctype" value="donation"}
                        {/if}
                        <a
                            href="{path_for name="contribution" data=["type" => $ctype, "action" => "edit", "id" => $contribution->id]}"
                            class="tooltip action"
                        >
                            <i class="fas fa-edit"></i>
                            <span class="sr-only">{_T string="Edit the contribution"}</span>
                        </a>
                        <a
                            href="{path_for name="removeContribution" data=["type" => "contributions", "id" => $contribution->id]}"
                            class="tooltip delete"
                        >
                            <i class="fas fa-trash"></i>
                            <span class="sr-only">{_T string="Delete the contribution"}</span>
                        </a>
                    </td>
    {/if}
                </tr>
{foreachelse}
                <tr><td colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}10{elseif $login->isAdmin() or $login->isStaff()}9{else}8{/if}" class="emptylist">{_T string="no contribution"}</td></tr>
{/foreach}
            </tbody>
        </table>
{if $nb != 0}
        <div class="ui basic center aligned fitted segment">
            <div class="ui pagination menu">
                <div class="header item">
                    {_T string="Pages:"}
                </div>
                {$pagination}
            </div>
        </div>
    {if ($login->isAdmin() or $login->isStaff()) && $mode neq 'ajax'}
        <div class="ui basic left aligned fitted segment">
            <div class="ui compact borderless stackable menu">
                <div class="header item">
                    {_T string="For the selection:"}
                </div>
                <div class="item">
                    <button type="submit" id="delete" name="delete" class="ui labeled icon button">
                        <i class="trash icon"></i> {_T string="Delete"}
                    </button>
                </div>
            </div>
        </div>
    {/if}
{/if}
        </form>
        <div id="legende" title="{_T string="Legend"}">
            <h1>{_T string="Legend"}</h1>
            <table>
{if ($login->isAdmin() or $login->isStaff()) and $mode neq 'ajax'}
                <tr>
                    <th class="action">
                        <i class="fas fa-edit fa-fw"></i>
                    </th>
                    <td class="back">{_T string="Modification"}</td>
                </tr>
                <tr>
                    <th class="delete">
                        <i class="fas fa-trash fa-fw"></i>
                    </th>
                    <td class="back">{_T string="Deletion"}</td>
                </tr>
{/if}
                <tr>
                    <th class="cotis-normal color-sample">&nbsp;</th>
                    <td class="back">{_T string="Contribution"}</td>
                </tr>
                <tr>
                    <th class="cotis-give color-sample">&nbsp;</th>
                    <td class="back">{_T string="Gift"}</td>
                </tr>
            </table>
        </div>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
        var _checkselection = function() {
            var _checkeds = $('table.listing').find('input[type=checkbox]:checked').length;
            if ( _checkeds == 0 ) {
                var _el = $('<div id="pleaseselect" title="{_T string="No contribution selected" escape="js"}">{_T string="Please make sure to select at least one contribution from the list to perform this action." escape="js"}</div>');
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
            $(function(){
                var _init_contribs_page = function(res){
                    $('#nbshow').change(function() {
                        this.form.submit();
                    });

                    var _checklinks = '<div class="checkboxes ui basic horizontal segments"><div class="ui basic fitted segment"><a href="#" class="checkall ui blue tertiary button">{_T string="(Un)Check all"}</a> | <a href="#" class="checkinvert ui blue tertiary button">{_T string="Invert selection"}</a></div><div class="ui basic right aligned fitted segment"><a href="#" class="show_legend ui blue tertiary button">{_T string="Show legend"}</a></div></div>';
                    $('.listing').before(_checklinks);
                    $('.listing').after(_checklinks);
                    _bind_check('contrib_sel');
                    _bind_legend();

                    /*$('#start_date_filter, #end_date_filter').datepicker({
                        changeMonth: true,
                        changeYear: true,
                        showOn: 'button',
                        buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                    });*/
                }
                _init_contribs_page();

                {include file="js_removal.tpl"}
                {include file="js_removal.tpl" selector="#delete" deleteurl="'{path_for name="removeContributions" data=["type" => "contributions"]}'" extra_check="if (!_checkselection()) {ldelim}return false;{rdelim}" extra_data="delete: true, contrib_sel: $('#listform input[type=\"checkbox\"]:checked').map(function(){ return $(this).val(); }).get()" method="POST"}
            });
        </script>
{/block}
