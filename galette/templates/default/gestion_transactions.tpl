{extends file="page.tpl"}
{block name="content"}
        <form action="{path_for name="payments_filter" data=["type" => {_T string="transactions" domain="routes"}]}" method="post" id="filtre">
        <div id="listfilter">
            <label for="start_date_filter">{_T string="Show transactions since"}</label>&nbsp;
            <input type="text" name="start_date_filter" id="start_date_filter" maxlength="10" size="10" value="{$filters->start_date_filter}"/>
            <label for="end_date_filter">{_T string="until"}</label>&nbsp;
            <input type="text" name="end_date_filter" id="end_date_filter" maxlength="10" size="10" value="{$filters->end_date_filter}"/>
            <input type="submit" class="inline" value="{_T string="Filter"}"/>
            <input type="submit" name="clear_filter" class="inline" value="{_T string="Clear filter"}"/>
        </div>
        <table class="infoline">
            <tr>
                <td class="left nowrap">
{if isset($member)}
    {if $login->isAdmin() or $login->isStaff()}
                    <a id="clearfilter" href="{path_for name="contributions" data=["type" => {_T string="transactions" domain="routes"}, "option" => {_T string="member" domain="routes"}, "value" => "all"]}" title="{_T string="Show all members transactions"}">{_T string="Show all members transactions"}</a>
    {/if}
                    <strong>{$member->sname}</strong>
    {if $login->isAdmin() or $login->isStaff()}
                    (<a href="{path_for name="member" data=["id" => $member->id]}">{_T string="See member profile"}</a> -
                    <a href="{path_for name="transaction" data=["action" => {_T string="add" domain="routes"}]}?id_adh={$member->id}">{_T string="Add a transaction"}</a>)
    {/if}
                    &nbsp;:
{/if}
                    {$nb} {if $nb > 1}{_T string="transactions"}{else}{_T string="transaction"}{/if}
                </td>
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
        <table class="listing">
            <thead>
                <tr>
                    <th class="id_row">#</th>
                    <th class="left date_row">
                        <a href="{path_for name="contributions" data=["type" => {_T string="transactions" domain="routes"}, "option" => {_T string="order" domain="routes"}, "value" => "Galette\Filters\TransactionsList::ORDERBY_DATE"|constant]}">{_T string="Date"}
                        {if $filters->orderby eq constant('Galette\Filters\TransactionsList::ORDERBY_DATE')}
                            {if $filters->ordered eq constant('Galette\Filters\TransactionsList::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="left">{_T string="Description"}</th>
{if $login->isAdmin() or $login->isStaff()}
                    <th class="left">
                        <a href="{path_for name="contributions" data=["type" => {_T string="transactions" domain="routes"}, "option" => {_T string="order" domain="routes"}, "value" => "Galette\Filters\TransactionsList::ORDERBY_MEMBER"|constant]}">{_T string="Originator"}
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
                        <a href="{path_for name="contributions" data=["type" => {_T string="transactions" domain="routes"}, "option" => {_T string="order" domain="routes"}, "value" => "Galette\Filters\TransactionsList::ORDERBY_AMOUNT"|constant]}">{_T string="Amount"}
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
{foreach from=$list item=transaction name=transactions_list}
    {assign var="mid" value=$transaction->member}
    {assign var="cclass" value=$transaction->getRowClass()}
                <tr>
                    <td class="{$cclass} nowrap" data-scope="row">
                        {$ordre+1+($filters->current_page - 1)*$numrows}
                        <span class="row-title">
                            <a href="{path_for name="transaction" data=["action" => {_T string="edit" domain="routes"}, "id" => $transaction->id]}">
                                {_T string="Transaction %id" pattern="/%id/" replace=$transaction->id}
                            </a>
                        </span>
                    </td>
                    <td class="{$cclass} nowrap" data-title="{_T string="Date"}">{$transaction->date}</td>
                    <td class="{$cclass} nowrap" data-title="{_T string="Description"}">{$transaction->description}</td>
{if $login->isAdmin() or $login->isStaff()}
                    <td class="{$cclass}" data-title="{_T string="Originator"}">
    {if $filters->filtre_cotis_adh eq ""}
                        <a href="{path_for name="contributions" data=["type" => {_T string="transactions" domain="routes"}, "option" => {_T string="member" domain="routes"}, "value" => $mid]}">
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
                        <a href="{path_for name="transaction" data=["action" => {_T string="edit" domain="routes"}, "id" => $transaction->id]}">
                            <img src="{base_url}/{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16"/>
                        </a>
                        <a class="delete" href="{path_for name="removeContributions" data=["type" => {_T string="transactions" domain="routes"}, "id" => $transaction->id]}">
                            <img src="{base_url}/{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/>
                        </a>
                    </td>
{/if}
                </tr>
{foreachelse}
                <tr><td colspan="{if $login->isAdmin() or $login->isStaff()}6{else}4{/if}" class="emptylist">{_T string="no transaction"}</td></tr>
{/foreach}
            </tbody>
        </table>
{if $nb != 0}
        <div class="center cright">
            {_T string="Pages:"}<br/>
            <ul class="pages">{$pagination}</ul>
        </div>
{/if}
        <div id="legende" title="{_T string="Legend"}">
            <h1>{_T string="Legend"}</h1>
            <table>
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
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $(function(){
                $('#nbshow').change(function() {
                    this.form.submit();
                });

                var _checklinks = '<div class="checkboxes"><a href="#" class="show_legend fright">{_T string="Show legend"}</a></div>';
                $('.listing').before(_checklinks);
                $('.listing').after(_checklinks);

                //$('#table_footer').parent().before('<td class="right" colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}9{elseif $login->isAdmin() or $login->isStaff()}8{else}7{/if}"><a href="#" class="show_legend">{_T string="Show legend"}</a></td>');

                _bind_legend();

                $.datepicker.setDefaults($.datepicker.regional['{$galette_lang}']);
                $('#start_date_filter, #end_date_filter').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    buttonImage: '{base_url}/{$template_subdir}images/calendar.png',
                    buttonImageOnly: true,
                    buttonText: '{_T string="Select a date" escape="js"}'
                });

                {include file="js_removal.tpl"}
            });
        </script>
{/block}
