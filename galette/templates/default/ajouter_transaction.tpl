{extends file="page.tpl"}

{block name="content"}
{if isset($members.list)}
        <form action="{if $transaction->id}{path_for name="editTransaction" data=["id" => $transaction->id]}{else}{path_for name="addTransaction"}{/if}" enctype="multipart/form-data" method="post">
        <div class="bigtable">
            <fieldset class="cssform">
                <legend class="ui-state-active ui-corner-top">{_T string="Transaction details"}</legend>
                <p>
                    <label for="trans_desc" class="bline">{_T string="Description:"}</label>
                    <input type="text" name="trans_desc" id="trans_desc" value="{$transaction->description}" maxlength="150" size="30"{if $required.trans_desc eq 1} required="required"{/if}/>
                </p>
                <p>
                    <label for="id_adh" class="bline" >{_T string="Originator:"}</label>
                    <select name="id_adh" id="id_adh" class="nochosen"{if $required.id_adh eq 1} required="required"{/if}>
    {if !$transaction->member}
                        <option value="">{_T string="-- select a name --"}</option>
    {/if}
    {foreach $members.list as $k=>$v}
                            <option value="{$k}"{if $transaction->member == $k} selected="selected"{/if}>{$v}</option>
    {/foreach}
                    </select>
                </p>
                <p>
                    <label for="trans_date" class="bline">{_T string="Date:"}</label>
                    <input type="text" class="date-pick" name="trans_date" id="trans_date" value="{$transaction->date}" maxlength="10"{if $required.trans_date eq 1} required="required"{/if}/> <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
                </p>
                <p>
                    <label for="trans_amount" class="bline">{_T string="Amount:"}</label>
                    <input type="text" name="trans_amount" id="trans_amount" value="{$transaction->amount}" maxlength="10"{if $required.trans_amount eq 1} required="required"{/if}/>
                </p>
    {if $transaction->id eq null or $transaction->getMissingAmount() > 0}
                <p>
                    <span class="bline tooltip" title="{_T string="Select a contribution type to create for dispatch transaction"}">{_T string="Dispatch type:"}</span>
                    <span class="tip">{_T string="Select a contribution type to create for dispatch transaction"}</span>
                    <input type="radio" name="contrib_type" id="contrib_type_fee" value="{constant('Galette\Entity\Contribution::TYPE_FEE')}" checked="checked"/> <label for="contrib_type_fee">{_T string="Membership fee"}</label>
                    <input type="radio" name="contrib_type" id="contrib_type_donation" value="{constant('Galette\Entity\Contribution::TYPE_DONATION')}"/> <label for="contrib_type_donation">{_T string="Donation"}</label>
                </p>
    {/if}
            </fieldset>
        </div>
    {include file="edit_dynamic_fields.tpl" object=$transaction}
        <div class="button-container">
            <button type="submit" name="valid" class="action">
                <i class="fas fa-save fa-fw"></i> {_T string="Save"}
            </button>
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
                    <a
                        href="{path_for name="addContribution" data=["type" => constant('Galette\Entity\Contribution::TYPE_FEE')]}?trans_id={$transaction->id}"
                        class="button fright tooltip"
                    >
                        <i class="fas fa-user-check"></i>
                        <span class="sr-only">{_T string="Create a new fee that will be attached to the current transaction"}</span>
                    </a>
                    <a
                        href="{path_for name="addContribution" data=["type" => constant('Galette\Entity\Contribution::TYPE_DONATION')]}?trans_id={$transaction->id}"
                        class="button fright tooltip"
                    >
                        <i class="fas fa-gift"></i>
                        <span class="sr-only">{_T string="Create a new donation that will be attached to the current transaction"}</span>
                    </a>
                    <a
                        href="#"
                        class="button fright tooltip"
                        id="contribslist"
                    >
                        <i class="fas fa-cookie"></i>
                        <span class="sr-only">{_T string="Select an existing contribution in the database, and attach it to the current transaction"}</span>
                    </a>
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
                    <th class="right bgfree" colspan="{if $login->isAdmin() or $login->isStaff()}7{else}5{/if}">{_T string="Dispatched amount:"}</th>
                    <th class="right bgfree">{$transaction->getDispatchedAmount()}</th>
                    <td></td>
                </tr>
                <tr>
                    <th class="right bgfree" colspan="{if $login->isAdmin() or $login->isStaff()}7{else}5{/if}">{_T string="Not dispatched amount:"}</th>
                    <th class="right bgfree">{$transaction->getMissingAmount()}</th>
                    <td></td>
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
                    <td class="{$cclass} actions_row">
                        <a
                            href="{path_for name="detach_contribution" data=["id" => $transaction->id, "cid" => $contrib->id]}"
                            class="delete tooltip"
                        >
                            <i class="fas fa-trash"></i>
                            <span class="sr-only">{_T string="Detach contribution from this transaction"}</span>
                        </a>
                    </td>
            {/if}
                </tr>
        {foreachelse}
                <tr><td colspan="{if $login->isAdmin() or $login->isStaff()}9{else}7{/if}" class="emptylist">{_T string="no contribution"}</td></tr>
        {/foreach}
            </tbody>
        </table>
    {/if}
{else} {* No members *}
    <div class="center" id="warningbox">
        <h3>{_T string="No member registered!"}</h3>
        <p>
            {_T string="Unfortunately, there is no member in your database yet,"}
            <br/>
            <a href="{path_for name="addMember"}">{_T string="please create a member"}</a>
        </p>
    </div>
{/if}
{/block}

{block name="javascripts"}
    <script type="text/javascript">
    {include file="js_chosen_adh.tpl"}
        $(function(){
{if $transaction->id}
            $('#contribslist').click(function(){
                $.ajax({
                    url: '{path_for name="contributions" data=["type" => "contributions"]}',
                    type: "GET",
                    data: {
                        ajax: true,
                        max_amount: '{$transaction->getMissingAmount()}'
                    },
                    {include file="js_loader.tpl"},
                    success: function(res){
                        _contribs_dialog(res);
                    },
                    error: function() {
                        alert("{_T string="An error occurred displaying members interface :("}");
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
                $("#contributions_list #legende").remove();
                $('#contributions_list').append( res );

                //Deactivate contributions list links
                $('#contributions_list tbody a').click(function(){
                    //for links in body (members links), we de nothing
                    return false;
                });
                _bindNbshow('#contributions_list #filtre');
                //Use JS to send form
                $('#contributions_list #filtre').submit(function(){
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
                            alert("{_T string="An error occurred displaying contributions :("}");
                        }
                    });
                    return false;
                });
                //Bind pagination links
                $('#contributions_list .pages a').bind({
                    click: function(){
                        $.ajax({
                            url: this.href.substring(this.href.indexOf('?')) + (this.href.indexOf('?') > -1 ? "&" : "?") + "ajax=true",
                            type: "GET",
                            {include file="js_loader.tpl"},
                            success: function(res){
                                $('#contributions_list').empty();
                                _contribs_ajax_mapper(res);
                            },
                            error: function() {
                                alert("{_T string="An error occurred displaying contributions :("}");
                            },
                        });
                        return false;
                    }
                });
                //Select a row
                $('#contributions_list .contribution_row').click(function(){
                    $('#contributions_list').dialog("close");
                    var _cid = $(this).find('input[name="contrib_id"]').val();
                    window.location.href = '{path_for name="attach_contribution" data=["id" => $transaction->id, "cid" => "%cid"]}'.replace(/%cid/, _cid);
                }).css('cursor', 'pointer').attr('title', '{_T string="Click on a contribution row to attach it to the current transaction" escape="js"}');
            }
{/if}
            $('#trans_date').datepicker({
                changeMonth: true,
                changeYear: true,
                showOn: 'button',
                buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
            });
        });
    </script>
{/block}
