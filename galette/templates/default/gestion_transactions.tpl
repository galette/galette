{extends file="page.tpl"}
{block name="content"}
        <form action="{path_for name="payments_filter" data=["type" => "transactions"]}" method="post" id="filtre" class="ui form">
        <div class="ui segment">
            <div class="two fields">
                <div class="two fields">
                    <div class="field">
                        <label for="start_date_filter">{_T string="Show transactions since"}</label>
                        <div class="ui calendar" id="contrib-rangestart">
                            <div class="ui input left icon">
                                <i class="calendar icon"></i>
                                <input placeholder="{_T string="(yyyy-mm-dd format)"}" type="text" name="start_date_filter" id="start_date_filter" maxlength="10" size="10" value="{$filters->start_date_filter}"/>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label for="end_date_filter">{_T string="until"}</label>
                        <div class="ui calendar" id="contrib-rangeend">
                            <div class="ui input left icon">
                                <i class="calendar icon"></i>
                                <input placeholder="{_T string="(yyyy-mm-dd format)"}" type="text" name="end_date_filter" id="end_date_filter" maxlength="10" size="10" value="{$filters->end_date_filter}"/>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="flexend center aligned field">
                    <input type="submit" class="ui primary button" value="{_T string="Filter"}"/>
                    <input type="submit" name="clear_filter" class="ui button" value="{_T string="Clear filter"}"/>
                </div>
            </div>
        </div>
{if isset($member)}
    {if $login->isAdmin() or $login->isStaff() or $member->canShow($login)}
        <div class="ui compact vertically fitted segment">
            <div class="ui horizontal list">
                <span class="ui primary ribbon label">
                    <a
                        href="{path_for name="contributions" data=["type" => "transactions", "option" => "member", "value" => "all"]}"
                        class="tooltip"
                    >
                        <i class="icon eraser"></i>
                        <span class="hidden">{_T string="Show all members transactions"}</span>
                    </a>
    {/if}
                    {$member->sname}{if not $member->isActive() } ({_T string="Inactive"}){/if}
                </span>
    {if $login->isAdmin() or $login->isStaff()}
                <div class="item">
                    <a href="{path_for name="member" data=["id" => $member->id]}" class="ui tiny button">{_T string="See member profile"}</a>
                </div>
                <div class="item">
                    <a href="{path_for name="addTransaction"}?id_adh={$member->id}" class="ui tiny button">{_T string="Add a transaction"}</a>
                </div>
    {/if}
            </div>
        </div>
{/if}
        <div class="infoline">
            <div class="ui basic horizontal segments">
                <div class="ui basic fitted segment">
                    <div class="ui label">{_T string="%count transaction" plural="%count transactions" count=$nb pattern="/%count/" replace=$nb}</div>
                </div>
                <div class="ui basic right aligned fitted segment">
                    <div class="inline field">
                        <label for="nbshow">{_T string="Records per page:"}</label>
                        <select name="nbshow" id="nbshow" class="ui dropdown nochosen">
                            {html_options options=$nbshow_options selected=$numrows}
                        </select>
                        <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                    </div>
                    {include file="forms_types/csrf.tpl"}
                </div>
            </div>
        </div>
        </form>
        <table class="listing ui celled table">
            <thead>
                <tr>
                    <th class="id_row">
                        <a href="{path_for name="contributions" data=["type" => "transactions", "option" => "order", "value" => "Galette\Filters\TransactionsList::ORDERBY_ID"|constant]}">#
                        {if $filters->orderby eq constant('Galette\Filters\TransactionsList::ORDERBY_ID')}
                            {if $filters->ordered eq constant('Galette\Filters\TransactionsList::ORDER_ASC')}
                            <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                            <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="left date_row">
                        <a href="{path_for name="contributions" data=["type" => "transactions", "option" => "order", "value" => "Galette\Filters\TransactionsList::ORDERBY_DATE"|constant]}">{_T string="Date"}
                        {if $filters->orderby eq constant('Galette\Filters\TransactionsList::ORDERBY_DATE')}
                            {if $filters->ordered eq constant('Galette\Filters\TransactionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    {if (($login->isAdmin() or $login->isStaff()) and !isset($member)) or isset($pmember)}
                        <th class="left">
                            <a href="{path_for name="contributions" data=["type" => "transactions", "option" => "order", "value" => "Galette\Filters\TransactionsList::ORDERBY_MEMBER"|constant]}">{_T string="Member"}
                                {if $filters->orderby eq constant('Galette\Filters\TransactionsList::ORDERBY_MEMBER')}
                                    {if $filters->ordered eq constant('Galette\Filters\TransactionsList::ORDER_ASC')}
                                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                    {else}
                                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                    {/if}
                                {/if}
                            </a>
                        </th>
                    {/if}
                    <th class="left">{_T string="Description"}</th>
{if $login->isAdmin() or $login->isStaff()}
                    <th class="left">
                        <a href="{path_for name="contributions" data=["type" => "transactions", "option" => "order", "value" => "Galette\Filters\TransactionsList::ORDERBY_MEMBER"|constant]}">{_T string="Originator"}
                        {if $filters->orderby eq constant('Galette\Filters\TransactionsList::ORDERBY_MEMBER')}
                            {if $filters->ordered eq constant('Galette\Filters\TransactionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
{/if}
                    <th class="left">
                        <a href="{path_for name="contributions" data=["type" => "transactions", "option" => "order", "value" => "Galette\Filters\TransactionsList::ORDERBY_AMOUNT"|constant]}">{_T string="Amount"}
                        {if $filters->orderby eq constant('Galette\Filters\TransactionsList::ORDERBY_AMOUNT')}
                            {if $filters->ordered eq constant('Galette\Filters\TransactionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
{if $login->isAdmin() or $login->isStaff()}
                    <th class="actions_row">{_T string="Actions"}</th>
{/if}
                </tr>
            </thead>
            <tbody>
{foreach from=$list item=transaction name=transactions_list key=ordre}
    {assign var="mid" value=$transaction->member}
    {assign var="cclass" value=$transaction->getRowClass()}
                <tr>
                    <td class="{$cclass} nowrap" data-scope="row">
    {if $preferences->pref_show_id}
                        {$transaction->id}
    {else}
                        {$ordre+1+($filters->current_page - 1)*$numrows}
    {/if}
                        <span class="row-title">
                            <a href="{path_for name="editTransaction" data=["id" => $transaction->id]}">
                                {_T string="Transaction %id" pattern="/%id/" replace=$transaction->id}
                            </a>
                        </span>
                    </td>
                    <td class="{$cclass} nowrap" data-title="{_T string="Date"}">{$transaction->date}</td>
                    {if (($login->isAdmin() or $login->isStaff()) && !isset($member)) or isset($pmember)}
                        <td class="{$cclass}" data-title="{_T string="Member"}">
                            {if isset($member)}
                                {assign var="mname" value=$member->sname}
                            {else}
                                {assign var="mname" value={memberName id=$mid}}
                            {/if}
                            {if $filters->filtre_cotis_adh eq ""}
                                <a
                                        href="{path_for name="contributions" data=["type" => "transactions", "option" => "member", "value" => $mid]}"
                                        title="{_T string="Show only '%name' transactions" pattern="/%name/" replace=$mname}"
                                >
                                    <i class="fa fa-filter"></i>
                                </a>
                            {/if}
                            <a
                                    href="{path_for name="member" data=["id" => $mid]}"
                                    title="{_T string="Show '%name' card" pattern="/%name/" replace=$mname}"
                            >
                                {if isset($member)}{$member->sname}{else}{memberName id="$mid"}{/if}
                            </a>
                        </td>
                    {/if}
                    <td class="{$cclass} nowrap" data-title="{_T string="Description"}">{$transaction->description|escape}</td>
{if $login->isAdmin() or $login->isStaff()}
                    <td class="{$cclass}" data-title="{_T string="Originator"}">
    {if $filters->filtre_cotis_adh eq ""}
                        <a href="{path_for name="contributions" data=["type" => "transactions", "option" => "member", "value" => $mid]}">
                            {if isset($member)}{$member->sname}{else}{memberName id="$mid"}{/if}
                        </a>
    {else}
                        <a href="{path_for name="member" data=["id" => $mid]}">
                            {if isset($member)}{$member->sname}{else}{memberName id="$mid"}{/if}
                        </a>
    {/if}
                    </td>
{/if}
                    <td class="{$cclass} nowrap" data-title="{_T string="Amount"}">{$transaction->amount}</td>
{if $login->isAdmin() or $login->isStaff()}
                    <td class="{$cclass} center nowrap">
                        <a
                            href="{path_for name="editTransaction" data=["id" => $transaction->id]}"
                            class="tooltip action"
                        >
                            <i class="ui edit blue icon"></i>
                            <span class="sr-only">{_T string="Edit transaction #%id" pattern="/%id/" replace=$transaction->id}</span>
                        </a>
                        <a
                            href="{path_for name="removeContribution" data=["type" => "transactions", "id" => $transaction->id]}"
                            class="delete tooltip"
                        >
                            <i class="ui trash red icon"></i>
                            <span class="sr-only">{_T string="Remove transaction #%id" pattern="/%id/" replace=$transaction->id}</span>
                        </a>
                    </td>
{/if}
                </tr>
{foreachelse}
                <tr><td colspan="{if $login->isAdmin() or $login->isStaff()}7{else}5{/if}" class="emptylist">{_T string="no transaction"}</td></tr>
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
{/if}
        <div id="legende" title="{_T string="Legend"}" class="ui modal">
            <div class="header">{_T string="Legend"}</div>
            <div class="content">
                <table class="ui stripped table">
                    <tr>
                        <th class="transaction-normal color-sample"><img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/></th>
                        <td class="back">{_T string="Completely dispatched transaction"}</td>
                    </tr>
                    <tr>
                        <th class="transaction-uncomplete color-sample"><img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/></th>
                        <td class="back">{_T string="Uncomplete dispatched transaction"}</td>
                    </tr>
                </table>
            </div>
            <div class="actions"><div class="ui labeled icon deny button"><i class="times icon"></i> {_T string="Close"}</div></div>
        </div>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $(function(){
                var _checklinks = '<div class="checkboxes ui basic horizontal segments"><div class="ui basic right aligned fitted segment"><a href="#" class="show_legend  ui blue tertiary button">{_T string="Show legend"}</a></div></div>';
                $('.listing').before(_checklinks);
                $('.listing').after(_checklinks);

                //$('#table_footer').parent().before('<td class="right" colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}9{elseif $login->isAdmin() or $login->isStaff()}8{else}7{/if}"><a href="#" class="show_legend">{_T string="Show legend"}</a></td>');

                _bind_legend();

                /*$('#start_date_filter, #end_date_filter').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    buttonText: '<i class="ui calendar alt icon"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                });*/

                {include file="js_removal.tpl"}
            });
        </script>
{/block}
