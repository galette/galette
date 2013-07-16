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
                    <th><tt>{ldelim}ASSO_NAME{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation name"}<br/><span>({_T string="globally available"})</span></td>
                    <th><tt>{ldelim}ASSO_SLOGAN{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation slogan"}<br/><span>({_T string="globally available"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}ASSO_ADDRESS{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation address"}<br/><span>({_T string="globally available"})</span></td>
                    <th><tt>{ldelim}ASSO_WEBSITE{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation website"}<br/><span>({_T string="globally available"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}NAME_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's name"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th><tt>{ldelim}ADDRESS_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's address"}<br/><span>({_T string="available with reservations"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}ZIP_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's zipcode"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th><tt>{ldelim}TOWN_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's town"}<br/><span>({_T string="available with reservations"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_LABEL{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution label"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th><tt>{ldelim}CONTRIBUTION_AMOUNT{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution amount"}<br/><span>({_T string="available with reservations"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_DATE{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution full date"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th><tt>{ldelim}CONTRIBUTION_YEAR{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution year"}<br/><span>({_T string="available with reservations"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_COMMENT{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution comment"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th><tt>{ldelim}CONTRIBUTION_BEGIN_DATE{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution begin date"}<br/><span>({_T string="available with reservations"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_END_DATE{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution end date"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th><tt>{ldelim}CONTRIBUTION_ID{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution id"}<br/><span>({_T string="available with reservations"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIBUTION_PAYMENT_TYPE{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution payment type"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th>&nbsp;</th>
                    <td class="back">&nbsp;</td>
                </tr>
            </table>
        </div>
        <script type="text/javascript">
            $('#tabs').append('<a class="button notext" id="btninfo_small" title="{_T string="Show existing variables"}">{_T string="Show existing variables"}</a>');
            $(function(){
                $('#legende h1').remove();
                $('#legende').dialog({
                    autoOpen: false,
                    modal: true,
                    hide: 'fold',
                    width: '60em'
                }).dialog('close');

                $('#btninfo_small').click(function(){
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
                            var _img = $('<figure id="loading"><p><img src="{$template_subdir}images/loading.png" alt="{_T string="Loading..."}"/><br/>{_T string="Currently loading..."}</p></figure>');
                            $('body').append(_img);
                        },
                        complete: function() {
                            $('#loading').remove();
                        },
                        error: function( xhr, status, index, anchor ) {
                            alert('{_T string="An error occured :("|escape:"js"}');
                        }
                    }
                });
            });
        </script>
