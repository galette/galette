{extends file="page.tpl"}

{block name="content"}
        <div class="tabbed">
        <div id="tabs">
            <ul>
{foreach from=$models item=m name=formodels}
    {if $m->id eq $model->id}
        {assign var='activetab' value=$smarty.foreach.formodels.iteration}
    {/if}
                <li{if $m->id eq $model->id} class="ui-tabs-selected"{/if}><a href="?id={$m->id}">{$m->name}</a></li>
{/foreach}
            </ul>
            <div id="ui-tabs-{$activetab}">
{include file="gestion_pdf_content.tpl"}
            </div>
        </div>
        </div>
        <div id="legende" class="texts_legend" title="{_T string="Existing variables"}">
            <h1>{_T string="Existing variables"}</h1>
            <table>
                <tr>
                    <th colspan="4">
                        {_T string="Globally available"}
                    </th>
                </tr>
                <tr>
                    <th><tt>{ldelim}ASSO_NAME{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation name"}</td>
                    <th><tt>{ldelim}ASSO_SLOGAN{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation slogan"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}ASSO_ADDRESS{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation address"}</td>
                    <th><tt>{ldelim}ASSO_WEBSITE{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation website"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}ASSO_LOGO{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation logo"}</td>
                    <th><tt>{ldelim}DATE_NOW{rdelim}</tt></th>
                    <td class=back">{_T string="Current date (Y-m-d)"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}NAME_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's name"}</td>
                    <th><tt>{ldelim}ADDRESS_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's address"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}ZIP_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's zipcode"}</td>
                    <th><tt>{ldelim}TOWN_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's town"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}GROUP_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's main group"}</td>
                    <th><tt>{ldelim}GROUPS_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's groups (as list)"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}COMPANY_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Company name"}</td>
                    <th><tt>{ldelim}ID_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's ID"}</td>
                </tr>
                <tr>
                    <th colspan="4">
                        {_T string="Available for invoices and receipts only"}
                    </th>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_LABEL{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution label"}</td>
                    <th><tt>{ldelim}CONTRIBUTION_AMOUNT{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution amount"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_DATE{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution full date"}</td>
                    <th><tt>{ldelim}CONTRIBUTION_YEAR{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution year"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_COMMENT{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution comment"}</td>
                    <th><tt>{ldelim}CONTRIBUTION_BEGIN_DATE{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution begin date"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_END_DATE{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution end date"}</td>
                    <th><tt>{ldelim}CONTRIBUTION_ID{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution id"}</td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_PAYMENT_TYPE{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution payment type"}</td>
                    <th>&nbsp;</th>
                    <td class="back">&nbsp;</td>
                </tr>
            </table>
        </div>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $('#tabs').append('<a id="btnlegend" class="tab-button tooltip action" title="{_T string="Show existing variables"}"><i class="fas fa-info-circle fa-2x"></i> <span class="sr-only">{_T string="Show existing variables" escape="js"}</span></a>');
            $(function(){
                $('#legende h1').remove();
                $('#legende').dialog({
                    autoOpen: false,
                    modal: true,
                    hide: 'fold',
                    width: '60em'
                }).dialog('close');

                $('#btnlegend').click(function(){
                    $('#legende').dialog('open');
                        return false;
                });

                $('#tabs > ul > li > a').each(function(){
                    $(this).attr('href', $(this).attr('href')  + '&ajax=true');
                });

                $('#tabs').tabs({
                    load: function(event, ui) {
                        $('#tabs input:submit, #tabs .button, #tabs input:reset' ).button();
                    },
                    ajaxOptions: {
                        {* Cannot include js_loader.tpl here because we need to use beforeSend specificaly... *}
                        beforeSend: function(xhr, settings) {
                            if ( settings.url.match(/\?id={$model->id}.*/) ) {
                                return false; //avoid reloading first tab onload
                            }
                            var _img = $('<figure id="loading"><p><img src="{base_url}/{$template_subdir}images/loading.png" alt="{_T string="Loading..."}"/><br/>{_T string="Currently loading..."}</p></figure>');
                            $('body').append(_img);
                        },
                        complete: function() {
                            $('#loading').remove();
                        },
                        error: function( xhr, status, index, anchor ) {
                            alert('{_T string="An error occurred :(" escape="js"}');
                        }
                    }
                });
            });
        </script>
{/block}
