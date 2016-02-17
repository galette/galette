{extends file="page.tpl"}
{block name="content"}
        <form action="{path_for name="payments_filter" data=["type" => "contributions"]}" method="post" id="filtre">
        <div id="listfilter">
            <label for="date_field_filter">{_T string="Show contributions by"}</label>&nbsp;
            <select name="date_field_filter" id="date_field_filter">
                <option value="{Galette\Repository\Contributions::DATE_BEGIN}"{if $contributions->date_field eq constant('Galette\Repository\Contributions::DATE_BEGIN')} selected="selected"{/if}>{_T string="Begin"}</option>
                <option value="{Galette\Repository\Contributions::DATE_END}"{if $contributions->date_field eq constant('Galette\Repository\Contributions::DATE_END')} selected="selected"{/if}>{_T string="End"}</option>
                <option value="{Galette\Repository\Contributions::DATE_RECORD}"{if $contributions->date_field eq constant('Galette\Repository\Contributions::DATE_RECORD')} selected="selected"{/if}>{_T string="Record"}</option>
            </select>
            <label for="start_date_filter">{_T string="since"}</label>&nbsp;
            <input type="text" name="start_date_filter" id="start_date_filter" maxlength="10" size="10" value="{$contributions->start_date_filter}"/>
            <label for="end_date_filter">{_T string="until"}</label>&nbsp;
            <input type="text" name="end_date_filter" id="end_date_filter" maxlength="10" size="10" value="{$contributions->end_date_filter}"/>
            <label for="payment_type_filter">{_T string="Payment type"}</label>
            <select name="payment_type_filter" id="payment_type_filter">
                <option value="-1">{_T string="Select"}</option>
                <option value="{Galette\Entity\Contribution::PAYMENT_CASH}"{if $contributions->payment_type_filter eq constant('Galette\Entity\Contribution::PAYMENT_CASH')} selected="selected"{/if}>{_T string="Cash"}</option>
                <option value="{Galette\Entity\Contribution::PAYMENT_CREDITCARD}"{if $contributions->payment_type_filter eq constant('Galette\Entity\Contribution::PAYMENT_CREDITCARD')} selected="selected"{/if}>{_T string="Credit card"}</option>
                <option value="{Galette\Entity\Contribution::PAYMENT_CHECK}"{if $contributions->payment_type_filter eq constant('Galette\Entity\Contribution::PAYMENT_CHECK')} selected="selected"{/if}>{_T string="Check"}</option>
                <option value="{Galette\Entity\Contribution::PAYMENT_TRANSFER}"{if $contributions->payment_type_filter eq constant('Galette\Entity\Contribution::PAYMENT_TRANSFER')} selected="selected"{/if}>{_T string="Transfer"}</option>
                <option value="{Galette\Entity\Contribution::PAYMENT_PAYPAL}"{if $contributions->payment_type_filter eq constant('Galette\Entity\Contribution::PAYMENT_PAYPAL')} selected="selected"{/if}>{_T string="Paypal"}</option>
                <option value="{Galette\Entity\Contribution::PAYMENT_OTHER}"{if $contributions->payment_type_filter === constant('Galette\Entity\Contribution::PAYMENT_OTHER')} selected="selected"{/if}>{_T string="Other"}</option>
            </select>
            <input type="submit" class="inline" value="{_T string="Filter"}"/>
            <input type="submit" name="clear_filter" class="inline" value="{_T string="Clear filter"}"/>
        </div>
{if isset($member)}
        <div id="member_stateofdue" class="{$member->getRowClass()}{if not $member->isActive()} inactive{/if}">{$member->getDues()}</div>
{/if}
        <table class="infoline">
            <tr>
                <td class="left nowrap">
{if isset($member) && $mode neq 'ajax'}
    {if $login->isAdmin() or $login->isStaff()}
                    <a id="clearfilter" href="?id_adh=all" title="{_T string="Show all members contributions"}">{_T string="Show all members contributions"}</a>
    {/if}
                    <strong>{$member->sname}</strong>
    {if not $member->isActive() } ({_T string="Inactive"}){/if}
    {if $login->isAdmin() or $login->isStaff()}
                    (<a href="{path_for name="member" data=["id" => $member->id]}">{_T string="See member profile"}</a> -
                    <a href="ajouter_contribution.php?id_adh={$member->id}">{_T string="Add a contribution"}</a>)
    {/if}
                    &nbsp;:
{/if}
                    {$nb_contributions} {if $nb_contributions != 1}{_T string="contributions"}{else}{_T string="contribution"}{/if}
                </td>
                <td class="right">
                    {if $mode eq 'ajax'}
                        <input type="hidden" name="ajax" value="true"/>
                        <input type="hidden" name="max_amount" value="{$max_amount}"/>
                    {/if}
                    <label for="nbshow">{_T string="Show:"}</label>
                    <select name="nbshow" id="nbshow">
                        {html_options options=$nbshow_options selected=$numrows}
                    </select>
                    <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                </td>
            </tr>
        </table>
        </form>
        <form action="{path_for name="contributions"}" method="post" id="listform">
        <table class="listing">
            <thead>
                <tr>
                    <th class="listing id_row">#</th>
                    <th class="listing left date_row">
                        <a href="gestion_contributions.php?tri={Galette\Repository\Contributions::ORDERBY_DATE}" class="listing">{_T string="Date"}
                        {if $contributions->orderby eq constant('Galette\Repository\Contributions::ORDERBY_DATE')}
                            {if $contributions->ordered eq constant('Galette\Repository\Contributions::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="listing left date_row">
                        <a href="gestion_contributions.php?tri={Galette\Repository\Contributions::ORDERBY_BEGIN_DATE}" class="listing">{_T string="Begin"}
                        {if $contributions->orderby eq constant('Galette\Repository\Contributions::ORDERBY_BEGIN_DATE')}
                            {if $contributions->ordered eq constant('Galette\Repository\Contributions::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="listing left date_row">
                        <a href="gestion_contributions.php?tri={Galette\Repository\Contributions::ORDERBY_END_DATE}" class="listing">{_T string="End"}
                        {if $contributions->orderby eq constant('Galette\Repository\Contributions::ORDERBY_END_DATE')}
                            {if $contributions->ordered eq constant('Galette\Repository\Contributions::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
{if ($login->isAdmin() or $login->isStaff()) and !isset($member)}
                    <th class="listing left">
                        <a href="gestion_contributions.php?tri={Galette\Repository\Contributions::ORDERBY_MEMBER}" class="listing">{_T string="Member"}
                        {if $contributions->orderby eq constant('Galette\Repository\Contributions::ORDERBY_MEMBER')}
                            {if $contributions->ordered eq constant('Galette\Repository\Contributions::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
{/if}
                    <th class="listing left">
                        <a href="gestion_contributions.php?tri={Galette\Repository\Contributions::ORDERBY_TYPE}" class="listing">{_T string="Type"}
                        {if $contributions->orderby eq constant('Galette\Repository\Contributions::ORDERBY_TYPE')}
                            {if $contributions->ordered eq constant('Galette\Repository\Contributions::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="listing left">
                        <a href="gestion_contributions.php?tri={Galette\Repository\Contributions::ORDERBY_AMOUNT}" class="listing">{_T string="Amount"}
                        {if $contributions->orderby eq constant('Galette\Repository\Contributions::ORDERBY_AMOUNT')}
                            {if $contributions->ordered eq constant('Galette\Repository\Contributions::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="listing left">
                        <a href="gestion_contributions.php?tri={Galette\Repository\Contributions::ORDERBY_PAYMENT_TYPE}" class="listing">{_T string="Payment type"}
                        {if $contributions->orderby eq constant('Galette\Repository\Contributions::ORDERBY_PAYMENT_TYPE')}
                            {if $contributions->ordered eq constant('Galette\Repository\Contributions::ORDER_ASC')}
                        <img src="{base_url}/{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
                        <img src="{base_url}/{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
                        {/if}
                        </a>
                    </th>
                    <th class="listing left">
                        {_T string="Duration"}
                    </th>
{if ($login->isAdmin() or $login->isStaff()) and $mode neq 'ajax'}
                    <th class="listing nowrap actions_row">{_T string="Actions"}</th>
{/if}
                </tr>
            </thead>
{if $nb_contributions != 0}
            <tfoot>
                <tr>
                    <td class="right" colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}10{elseif $login->isAdmin() or $login->isStaff()}9{else}8{/if}">
                        {_T string="Found contributions total %f" pattern="/%f/" replace=$contributions->sum}
                    </td>
                </tr>
    {if ($login->isAdmin() or $login->isStaff()) && $mode neq 'ajax'}
                <tr>
                    <td colspan="8" id="table_footer">
                        <ul class="selection_menu">
                            <li>{_T string="For the selection:"}</li>
                            <li><input type="submit" id="delete" onclick="return confirm('{_T string="Do you really want to delete all selected contributions?"|escape:"javascript"}');" name="delete" value="{_T string="Delete"}"/></li>
                        </ul>
                    </td>
                </tr>
    {/if}
                <tr>
                    <td colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}10{elseif $login->isAdmin() or $login->isStaff()}9{else}8{/if}" class="center" id="table_footer">
                        {_T string="Pages:"}<br/>
                        <ul class="pages">{$pagination}</ul>
                    </td>
                </tr>
            </tfoot>
{/if}
            <tbody>
{foreach from=$list_contribs item=contribution key=ordre}
    {assign var="mid" value=$contribution->member}
    {assign var="cclass" value=$contribution->getRowClass()}
                <tr{if $mode eq 'ajax'} class="contribution_row" id="row_{$contribution->id}"{/if}>
                    <td class="{$cclass} center nowrap">
                        {if $mode neq 'ajax'}
                            <input type="checkbox" name="contrib_sel[]" value="{$contribution->id}"/>
                        {else}
                            <input type="hidden" name="contrib_id" value="{$contribution->id}"/>
                        {/if}
                        {$ordre+1}
        {if $contribution->isTransactionPart() }
                        <a href="{base_url}/ajouter_transaction.php?trans_id={$contribution->transaction->id}" title="{_T string="Transaction: %s" pattern="/%s/" replace=$contribution->transaction->description}">
                            <img src="{base_url}/{$template_subdir}images/icon-money.png"
                                alt="{_T string="[view]"}"
                                width="16"
                                height="16"/>
                        </a>
        {else}
                        <img src="{base_url}/{$template_subdir}images/icon-empty.png"
                            alt=""
                            width="16"
                            height="16"/>
        {/if}
                    </td>
                    <td class="{$cclass} center nowrap">{$contribution->date}</td>
                    <td class="{$cclass} center nowrap">{$contribution->begin_date}</td>
                    <td class="{$cclass} center nowrap">{$contribution->end_date}</td>
    {if ($login->isAdmin() or $login->isStaff()) && !isset($member)}
                    <td class="{$cclass}">
        {if $contribution->filtre_cotis_adh eq ""}
                        <a href="gestion_contributions.php?id_adh={$mid}">{if isset($member)}{$member->sname}{else}{memberName id="$mid"}{/if}</a>
        {else}
                        <a href="{path_for name="member" data=["id" => $mid]}">{if isset($member)}{$member->sname}{else}{memberName id="$mid"}{/if}</a>
        {/if}
                    </td>
    {/if}
                    <td class="{$cclass}">{$contribution->type->libelle}</td>
                    <td class="{$cclass} nowrap">{$contribution->amount}</td>
                    <td class="{$cclass} nowrap">{$contribution->spayment_type}</td>
                    <td class="{$cclass} nowrap">{$contribution->duration}</td>
    {if ($login->isAdmin() or $login->isStaff()) and $mode neq 'ajax'}
                    <td class="{$cclass} center nowrap">
                        <a href="pdf_contribution.php?id_cotis={$contribution->id}">
                            <img src="{base_url}/{$template_subdir}images/icon-pdf.png" alt="{_T string="[pdf]"}" width="16" height="16" title="{_T string="Print an invoice or a receipt (depending on contribution type)"}"/>
                        </a>
                        <a href="ajouter_contribution.php?id_cotis={$contribution->id}">
                            <img src="{base_url}/{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16" title="{_T string="Edit the contribution"}"/>
                        </a>
                        <a onclick="return confirm('{_T string="Do you really want to delete this contribution of the database ?"|escape:"javascript"}')" href="gestion_contributions.php?sup={$contribution->id}">
                            <img src="{base_url}/{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16" title="{_T string="Delete the contribution"}"/>
                        </a>
                    </td>
    {/if}
                </tr>
{foreachelse}
                <tr><td colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}10{elseif $login->isAdmin() or $login->isStaff()}9{else}8{/if}" class="emptylist">{_T string="no contribution"}</td></tr>
{/foreach}
            </tbody>
        </table>
        </form>
        <div id="legende" title="{_T string="Legend"}">
            <h1>{_T string="Legend"}</h1>
            <table>
{if ($login->isAdmin() or $login->isStaff()) and $mode neq 'ajax'}
                <tr>
                    <th class="back"><img src="{base_url}/{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16"/></th>
                    <td class="back">{_T string="Modification"}</td>
                </tr>
                <tr>
                    <th class="back"><img src="{base_url}/{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/></th>
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
            $(function(){
                var _init_contribs_page = function(res){
                    $('#nbshow').change(function() {
                        this.form.submit();
                    });
                    $('#table_footer').parent().before('<td class="right" colspan="{if ($login->isAdmin() or $login->isStaff()) && !isset($member)}10{elseif $login->isAdmin() or $login->isStaff()}9{else}8{/if}"><a href="#" id="show_legend">{_T string="Show legend"}</a></td>');
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

                    $.datepicker.setDefaults($.datepicker.regional['{$galette_lang}']);
                    $('#start_date_filter, #end_date_filter').datepicker({
                        changeMonth: true,
                        changeYear: true,
                        showOn: 'button',
                        buttonImage: '{base_url}/{$template_subdir}images/calendar.png',
                        buttonImageOnly: true,
                        buttonText: '{_T string="Select a date" escape="js"}'
                    });
                }
                _init_contribs_page();
            });
        </script>
{/block}
