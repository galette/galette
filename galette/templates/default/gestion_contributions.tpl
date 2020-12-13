{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}
{block name="content"}
        <form action="{path_for name="payments_filter" data=["type" => "contributions"]}" method="post" id="filtre">
        <div id="listfilter">
            <label for="date_field_filter">{_T string="Show contributions by"}</label>&nbsp;
            <select name="date_field_filter" id="date_field_filter">
                <option value="{Galette\Filters\ContributionsList::DATE_BEGIN}"{if $filters->date_field eq constant('Galette\Filters\ContributionsList::DATE_BEGIN')} selected="selected"{/if}>{_T string="Begin"}</option>
                <option value="{Galette\Filters\ContributionsList::DATE_END}"{if $filters->date_field eq constant('Galette\Filters\ContributionsList::DATE_END')} selected="selected"{/if}>{_T string="End"}</option>
                <option value="{Galette\Filters\ContributionsList::DATE_RECORD}"{if $filters->date_field eq constant('Galette\Filters\ContributionsList::DATE_RECORD')} selected="selected"{/if}>{_T string="Record"}</option>
            </select>
            <label for="start_date_filter">{_T string="since"}</label>&nbsp;
            <input type="text" name="start_date_filter" id="start_date_filter" maxlength="10" size="10" value="{$filters->start_date_filter}"/>
            <label for="end_date_filter">{_T string="until"}</label>&nbsp;
            <input type="text" name="end_date_filter" id="end_date_filter" maxlength="10" size="10" value="{$filters->end_date_filter}"/>
            {include file="forms_types/payment_types.tpl"
                current=$filters->payment_type_filter varname="payment_type_filter"
                show_inline=""
                classname=""
                empty=['value' => -1, 'label' => {_T string="Select"}]
            }
            <input type="submit" class="inline" value="{_T string="Filter"}"/>
            <input type="submit" name="clear_filter" class="inline" value="{_T string="Clear filter"}"/>
        </div>
{if isset($member)}
        <div id="member_stateofdue" class="{$member->getRowClass()}{if not $member->isActive()} inactive{/if}">{$member->getDues()}</div>
{/if}
        <div class="infoline">
{if isset($member) && $mode neq 'ajax'}
    {if $login->isAdmin() or $login->isStaff()}
            <a
                href="{path_for name="contributions" data=["type" => "contributions", "option" => "member", "value" => "all"]}"
                class="tooltip"
            >
                <i class="fas fa-eraser"></i>
                <span class="sr-only">{_T string="Show all members contributions"}</span>
            </a>
    {/if}
            <strong>{$member->sname}</strong>
    {if not $member->isActive() } ({_T string="Inactive"}){/if}
    {if $login->isAdmin() or $login->isStaff()}
            (<a href="{path_for name="member" data=["id" => $member->id]}">{_T string="See member profile"}</a> -
            <a href="{path_for name="addContribution" data=["type" => "fee"]}?id_adh={$member->id}">{_T string="Add a membership fee"}</a> -
            <a href="{path_for name="addContribution" data=["type" => "donation"]}?id_adh={$member->id}">{_T string="Add a donation"}</a>)
    {/if}
            -
{/if}
            {_T string="%count contribution" plural="%count contributions" count=$nb pattern="/%count/" replace=$nb}
            <div class="fright">
{if $mode eq 'ajax'}
                <input type="hidden" name="ajax" value="true"/>
                <input type="hidden" name="max_amount" value="{$filters->max_amount}"/>
{/if}
                <label for="nbshow">{_T string="Records per page:"}</label>
                <select name="nbshow" id="nbshow">
                    {html_options options=$nbshow_options selected=$numrows}
                </select>
                <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
            </div>
        </div>
        </form>
        <form action="" method="post" id="listform">
        <table class="listing">
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
{if $mode neq 'ajax'}
                    <th class="nowrap actions_row">{_T string="Actions"}</th>
{/if}
                </tr>
            </thead>
{if $nb != 0}
            <tfoot>
                <tr>
                    <th class="right" colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}10{elseif $login->isAdmin() or $login->isStaff()}9{else}8{/if}">
                        {_T string="Found contributions total %f" pattern="/%f/" replace=$contribs->getSum()}
                    </th>
                </tr>
            </tfoot>
{/if}
            <tbody>
{foreach from=$list item=contribution key=ordre}
    {assign var="mid" value=$contribution->member}
    {assign var="cclass" value=$contribution->getRowClass()}
    {if $contribution->isCotis()}
        {assign var="ctype" value="fee"}
    {else}
        {assign var="ctype" value="donation"}
    {/if}

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
    {if ($login->isAdmin() or $login->isStaff()) and $mode neq 'ajax'}
                        <span class="row-title">
                            <a href="{path_for name="editContribution" data=["type" => $ctype, "id" => $contribution->id]}">
                                {_T string="Contribution %id" pattern="/%id/" replace=$contribution->id}
                            </a>
                        </span>
        {if $contribution->isTransactionPart() }
                        <a
                            href="{path_for name="editTransaction" data=["id" => $contribution->transaction->id]}"
                            class="tooltip"
                        >
                            <i class="fas fa-link"></i>
                            <span class="sr-only">{_T string="Transaction: %s" pattern="/%s/" replace=$contribution->transaction->description}</span>
                        </a>
        {/if}
    {else}
                        <span class="row-title">
                            {_T string="Contribution %id" pattern="/%id/" replace=$contribution->id}
                        </span>
        {if $contribution->isTransactionPart() }
                        <i class="fas fa-link"></i>
                        <span class="sr-only">{_T string="Transaction: %s" pattern="/%s/" replace=$contribution->transaction->description}</span>
        {/if}
    {/if}
        {if !$contribution->isTransactionPart() }
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
        {if $filters->filtre_cotis_adh eq ""}
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
    {if $mode neq 'ajax'}
                    <td class="{$cclass} center nowrap">
                        <a
                            href="{path_for name="printContribution" data=["id" => $contribution->id]}"
                            class="tooltip"
                        >
                            <i class="fas fa-file-pdf"></i>
                            <span class="sr-only">{_T string="Print an invoice or a receipt (depending on contribution type)"}</span>
                        </a>
        {if ($login->isAdmin() or $login->isStaff()) and $mode neq 'ajax'}
                        <a
                            href="{path_for name="editContribution" data=["type" => $ctype, "id" => $contribution->id]}"
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
        {/if}
                    </td>
    {/if}
                </tr>
{foreachelse}
                <tr><td colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}10{elseif $login->isAdmin() or $login->isStaff()}9{else}8{/if}" class="emptylist">{_T string="no contribution"}</td></tr>
{/foreach}
            </tbody>
        </table>
{if $nb != 0}
        <div class="center cright">
            {_T string="Pages:"}<br/>
            <ul class="pages">{$pagination}</ul>
        </div>
    {if ($login->isAdmin() or $login->isStaff()) && $mode neq 'ajax'}
        <ul class="selection_menu">
            <li>{_T string="For the selection:"}</li>
            <li>
                <button type="submit" id="delete" name="delete">
                    <i class="fas fa-trash fa-fw"></i> {_T string="Delete"}
                </button>
            </li>
        </ul>
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
                    var _checklinks = '<div class="checkboxes"><span class="fleft"><a href="#" class="checkall tooltip"><i class="fas fa-check-square"></i> {_T string="(Un)Check all" escape="js"}</a> | <a href="#" class="checkinvert tooltip"><i class="fas fa-exchange-alt"></i> {_T string="Invert selection" escape="js"}</a></span><a href="#" class="show_legend fright">{_T string="Show legend" escape="js"}</a></div>';

                    $('.listing').before(_checklinks);
                    $('.listing').after(_checklinks);
                    _bind_check('contrib_sel');
                    _bind_legend();

                    $('#start_date_filter, #end_date_filter').datepicker({
                        changeMonth: true,
                        changeYear: true,
                        showOn: 'button',
                        buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                    });
                }
                _init_contribs_page();

                {include file="js_removal.tpl"}
                {include file="js_removal.tpl" selector="#delete" deleteurl="'{path_for name="removeContributions" data=["type" => "contributions"]}'" extra_check="if (!_checkselection()) {ldelim}return false;{rdelim}" extra_data="delete: true, contrib_sel: $('#listform input[type=\"checkbox\"]:checked').map(function(){ return $(this).val(); }).get()" method="POST"}
            });
        </script>
{/block}
