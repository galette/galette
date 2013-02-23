        <form action="gestion_transactions.php" method="get" id="filtre">
        <table class="infoline">
            <tr>
                <td class="left nowrap">
{if isset($member)}
    {if $login->isAdmin() or $login->isStaff()}
                    <a id="clearfilter" href="?id_adh=all" title="{_T string="Show all members transactions"}">{_T string="Show all members transactions"}</a>
    {/if}
                    <strong>{$member->sname}</strong>
    {if $login->isAdmin() or $login->isStaff()}
                    (<a href="voir_adherent.php?id_adh={$member->id}">{_T string="See member profile"}</a> -
                    <a href="ajouter_transaction.php?id_adh={$member->id}">{_T string="Add a transaction"}</a>)
    {/if}
                    &nbsp;:
{/if}
                    {$nb_transactions} {if $nb_transactions > 1}{_T string="transactions"}{else}{_T string="transaction"}{/if}
                </td>
                <td class="right">
                    <label for="nbshow">{_T string="Show:"}</label>
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
                    <th class="listing id_row">#</th>
                    <th class="listing left date_row">
                        <a href="gestion_transactions.php?tri={php}echo Galette\Repository\Transactions::ORDERBY_DATE;{/php}" class="listing">{_T string="Date"}
                        {if $transactions->orderby eq constant('Galette\Repository\Transactions::ORDERBY_DATE')}
                            {if $transactions->ordered eq constant('galette\Repository\Transactions::ORDER_ASC')}
                        <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="listing left">{_T string="Description"}</th>
{if $login->isAdmin() or $login->isStaff()}
                    <th class="listing left">
                        <a href="gestion_transactions.php?tri={php}echo Galette\Repository\Transactions::ORDERBY_MEMBER;{/php}" class="listing">{_T string="Originator"}
                        {if $transactions->orderby eq constant('Galette\Repository\Transactions::ORDERBY_MEMBER')}
                            {if $transactions->ordered eq constant('Galette\Repository\Transactions::ORDER_ASC')}
                        <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
{/if}
                    <th class="listing left">
                        <a href="gestion_transactions.php?tri={php}echo Galette\Repository\Transactions::ORDERBY_AMOUNT;{/php}" class="listing">{_T string="Amount"}
                        {if $transactions->orderby eq constant('Galette\Repository\Transactions::ORDERBY_AMOUNT')}
                            {if $transactions->ordered eq constant('Galette\Repository\Transactions::ORDER_ASC')}
                        <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
{if $login->isAdmin() or $login->isStaff()}
                    <th class="listing actions_row">{_T string="Actions"}</th>
{/if}
                </tr>
            </thead>
{if $nb_transactions != 0}
            <tfoot>
                <tr>
                    <td colspan="{if $login->isAdmin() or $login->isStaff()}6{else}4{/if}" class="center" id="table_footer">
                        {_T string="Pages:"}<br/>
                        <ul class="pages">{$pagination}</ul>
                    </td>
                </tr>
            </tfoot>
{/if}
            <tbody>
{foreach from=$list_trans item=transaction name=transactions_list}
    {assign var="mid" value=$transaction->member}
    {assign var="cclass" value=$transaction->getRowClass()}
                <tr>
                    <td class="{$cclass} center nowrap">{$transaction->id}</td>
                    <td class="{$cclass} nowrap">{$transaction->date}</td>
                    <td class="{$cclass} nowrap">{$transaction->description}</td>
{if $login->isAdmin() or $login->isStaff()}
                    <td class="{$cclass}">
    {if $transactions->filtre_cotis_adh eq ""}
                        <a href="gestion_transactions.php?id_adh={$mid}">
                            {if isset($member)}{$member->sname}{else}{memberName id="$mid"}{/if}
                        </a>
    {else}
                        <a href="voir_adherent.php?id_adh={$mid}">
                            {if isset($member)}{$member->sname}{else}{memberName id="$mid"}{/if}
                        </a>
    {/if}
                    </td>
{/if}
                    <td class="{$cclass} nowrap">{$transaction->amount}</td>
{if $login->isAdmin() or $login->isStaff()}
                    <td class="{$cclass} center nowrap">
                        <a href="ajouter_transaction.php?trans_id={$transaction->id}">
                            <img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16"/>
                        </a>
                        <a onclick="return confirm('{_T string="Do you really want to delete this transaction of the database ?"|escape:"javascript"}')" href="gestion_transactions.php?sup={$transaction->id}">
                            <img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/>
                        </a>
                    </td>
{/if}
                </tr>
{foreachelse}
                <tr><td colspan="{if $login->isAdmin() or $login->isStaff()}6{else}4{/if}" class="emptylist">{_T string="no transaction"}</td></tr>
{/foreach}
            </tbody>
        </table>
        <div id="legende" title="{_T string="Legend"}">
            <h1>{_T string="Legend"}</h1>
            <table>
                <tr>
                    <th class="transaction-normal color-sample"><img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/></th>
                    <td class="back">{_T string="Completely dispatched transaction"}</td>
                </tr>
                <tr>
                    <th class="transaction-uncomplete color-sample"><img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/></th>
                    <td class="back">{_T string="Uncomplete dispatched transaction"}</td>
                </tr>
            </table>
        </div>
        <script type="text/javascript">
            $(function(){
                $('#nbshow').change(function() {
                    this.form.submit();
                });

                $('#table_footer').parent().before('<td class="right" colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}9{elseif $login->isAdmin() or $login->isStaff()}8{else}7{/if}"><a href="#" id="show_legend">{_T string="Show legend"}</a></td>');
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
            });
        </script>
