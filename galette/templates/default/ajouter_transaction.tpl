{if isset($adh_options)}
        <form action="ajouter_transaction.php" method="post">
        <div class="bigtable">
            <fieldset class="cssform">
                <legend class="ui-state-active ui-corner-top">{_T string="Transaction details"}</legend>
                <p>
                    <label for="trans_desc" class="bline">{_T string="Description:"}</label>
                    <input type="text" name="trans_desc" id="trans_desc" value="{$transaction->description}" maxlength="150" size="30"{if $required.trans_desc eq 1} required{/if}/>
                </p>
                <p>
                    <label for="id_adh" class="bline" >{_T string="Originator:"}</label>
                    <select name="id_adh" id="id_adh"{if $required.id_adh eq 1} required{/if}>
{if !$transaction->member}
                        <option>{_T string="-- select a name --"}</option>
{/if}
{foreach $adh_options as $k=>$v}
                            <option value="{$k}"{if $transaction->member == $k} selected="selected"{/if}>{$v}</option>
{/foreach}
                    </select>
                </p>
                <p>
                    <label for="trans_date" class="bline">{_T string="Date:"}</label>
                    <input type="text" class="date-pick" name="trans_date" id="trans_date" value="{$transaction->date}" maxlength="10"{if $required.trans_date eq 1} required{/if}/> <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
                </p>
                <p>
                    <label for="trans_amount" class="bline">{_T string="Amount:"}</label>
                    <input type="text" name="trans_amount" id="trans_amount" value="{$transaction->amount}" maxlength="10"{if $required.trans_amount eq 1} required{/if}/>
                </p>
            </fieldset>
        </div>
{include file="edit_dynamic_fields.tpl"}
        <div class="button-container">
            <input id="btnsave" type="submit" value="{_T string="Save"}"/>
            <input type="hidden" name="trans_id" value="{$transaction->id}"/>
            <input type="hidden" name="valid" value="1"/>
        </div>
        <p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
{if $transaction->id}
        </form>
        <table class="listing">
            <caption>
                {_T string="Attached contributions"}
                {if $transaction->getMissingAmount() > 0}
                    <a href="ajouter_contribution.php?trans_id={$transaction->id}" class="button notext fright" id="btnadd" title="{_T string="Create a new contribution that will be attached to the current transaction"}">{_T string="New attached contribution"}</a>
                    <a href="#" class="button notext fright" id="memberslist" title="{_T string="Select an existing contribution in the database, and attach it to the current transaction"}">{_T string="Select existing contribution"}</a>
                {/if}
            </caption>
            <thead>
                <tr>
                    <th class="id_row">#</th>
                    <th class="left date_row">{_T string="Date"}</th>
                    <th class="left date_row">{_T string="Begin"}</th>
                    <th class="left date_row">{_T string="End"}</th>
                    <th class="left">{_T string="Duration"}</th>
{if $login->isAdmin() or $login->isStaff()}
                    <th class="left">{_T string="Member"}</th>
{/if}
                    <th class="left">{_T string="Type"}</th>
                    <th class="left">{_T string="Amount"}</th>
{if $login->isAdmin() or $login->isStaff()}
                    <th class="actions_row"></th>
{/if}
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th class="right bgfree" colspan="{if $login->isAdmin() or $login->isStaff()}8{else}6{/if}">{_T string="Dispatched amount:"}</th>
                    <th class="right bgfree">{$transaction->getDispatchedAmount()}</th>
                </tr>
                <tr>
                    <th class="right bgfree" colspan="{if $login->isAdmin() or $login->isStaff()}8{else}6{/if}">{_T string="Not dispatched amount:"}</th>
                    <th class="right bgfree">{$transaction->getMissingAmount()}</th>
                </tr>
            </tfoot>
            <tbody>
{foreach from=$contribs item=contrib key=ordre}
    {assign var="mid" value=$contrib->member}
    {assign var="cclass" value=$contrib->getRowClass()}
                <tr>
                    <td class="{$cclass} center nowrap">
                        {$ordre+1}
                    </td>
                    <td class="{$cclass} center nowrap">{$contrib->date}</td>
                    <td class="{$cclass} center nowrap">{$contrib->begin_date}</td>
                    <td class="{$cclass} center nowrap">{$contrib->end_date}</td>
                    <td class="{$cclass} nowrap">{$contrib->duration}</td>
    {if $login->isAdmin() or $login->isStaff()}
                    <td class="{$cclass}">{memberName id="$mid"}</td>
    {/if}
                    <td class="{$cclass}">{$contrib->type->libelle}</td>
                    <td class="{$cclass} nowrap right">{$contrib->amount}</td>
    {if $login->isAdmin() or $login->isStaff()}
                    <td class="{$cclass}">
                        <a href="?trans_id={$transaction->id}&detach={$contrib->id}">
                            <img src="{$template_subdir}images/delete.png" alt="{_T string="Detach"}" width="16" height="16" title="{_T string="Detach contribution from this transaction"}"/>
                        </a>
                    </td>
    {/if}
                </tr>
{foreachelse}
                <tr><td colspan="{if $login->isAdmin() or $login->isStaff()}8{else}7{/if}" class="emptylist">{_T string="no contribution"}</td></tr>
{/foreach}
            </tbody>
        </table>
{/if}
        <script type="text/javascript">
            $(function(){
{if $transaction->id}
                $('#memberslist').click(function(){
                    $.ajax({
                        url: 'gestion_contributions.php',
                        type: "POST",
                        data: {
                            ajax: true,
                            max_amount: '{$transaction->getMissingAmount()}'
                        },
                        {include file="js_loader.tpl"},
                        success: function(res){
                            _contribs_dialog(res);
                        },
                        error: function() {
                            alert("{_T string="An error occured displaying members interface :("}");
                        }
                    });
                    return false;
                });

                var _contribs_dialog = function(res){
                    var _el = $('<div id="contributions_list" title="{_T string="Contributions selection"}"> </div>');
                    _el.appendTo('body').dialog({
                        modal: true,
                        hide: 'fold',
                        width: '80%',
                        height: 500,
                        close: function(event, ui){
                            _el.remove();
                            $("#legende").remove();
                        }
                    });
                    _contribs_ajax_mapper(res);
                }

                var _contribs_ajax_mapper = function(res){
                    $("#legende").remove();
                    $('#contributions_list').append( res );

                    //Deactivate contributions list links
                    $('#contributions_list tbody a').click(function(){
                        //for links in body (members links), we de nothing
                        return false;
                    });
                    //Use JS to send form
                    $('#filtre').submit(function(){
                        $.ajax({
                            url: this.action,
                            type: "POST",
                            data: $("#filtre").serialize(),
                            {include file="js_loader.tpl"},
                            success: function(res){
                                $('#contributions_list').empty();
                                _contribs_ajax_mapper(res);
                            },
                            error: function() {
                                alert("{_T string="An error occured displaying contributions :("}");
                            }
                        });
                        return false;
                    });
                    //Re-bind submit event on the correct element here
                    $('#nbshow').unbind('change');
                    $('#nbshow').change(function() {
                        $('form#filtre').submit();
                    });
                    //Bind pagination links
                    $('.pages a').bind({
                        click: function(){
                            $.ajax({
                                url: 'gestion_contributions.php' + this.href.substring(this.href.indexOf('?')) + "&ajax=true",
                                type: "GET",
                                {include file="js_loader.tpl"},
                                success: function(res){
                                    $('#contributions_list').empty();
                                    _contribs_ajax_mapper(res);
                                },
                                error: function() {
                                    alert("{_T string="An error occured displaying contributions :("}");
                                },
                            });
                            return false;
                        }
                    });
                    //Select a row
                    $('.contribution_row').click(function(){
                        $('#contributions_list').dialog("close");
                        var _cid = $(this).find('input[name="contrib_id"]').val();
                        window.location.href = window.location.href + '&cid=' + _cid;
                    }).css('cursor', 'pointer').attr('title', '{_T string="Click on a contribution row to attach it to the current transaction" escape="js"}');
                }
{/if}
                $.datepicker.setDefaults($.datepicker.regional['{$galette_lang}']);
                $('#trans_date').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    buttonImage: '{$template_subdir}images/calendar.png',
                    buttonImageOnly: true
                });
            });
        </script>
{else} {* No members *}
    <div class="center" id="warningbox">
        <h3>{_T string="No member registered!"}</h3>
        <p>
            {_T string="Unfortunately, there is no member in your database yet,"}
            <br/>
            <a href="ajouter_adherent.php">{_T string="please create a member"}</a>
        </p>
    </div>
{/if}
