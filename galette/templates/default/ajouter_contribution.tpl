{extends file="page.tpl"}

{block name="content"}
{if isset($adh_options)}
        <form action="{if $contribution->id}{path_for name="contribution" data=["type" => $type, "action" => {_T string="edit" domain="routes"}, "id" => $contribution->id]}{else}{path_for name="contribution" data=["type" => $type, "action" => {_T string="add" domain="routes"}]}{/if}" method="post">
        <div class="bigtable">
    {if $contribution->isTransactionPart()}
        {assign var="mid" value=$contribution->transaction->member}
            <table id="transaction_detail">
                <caption>{_T string="Related transaction informations"}</caption>
                <thead>
                    <tr>
                        <td colspan="5">
                            {$contribution->transaction->description}
                            <a href="{path_for name="transaction" data=["action" => {_T string="edit" domain="routes"}, "id" => $contribution->transaction->id]}" title="{_T string="View transaction"}">
                                <img src="{base_url}/{$template_subdir}images/icon-money.png"
                                    alt="{_T string="[view]"}"
                                    width="16"
                                    height="16"/>
                            </a>
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="listing">#</th>
                        <th class="listing">{_T string="Date"}</th>
                        <th class="listing">{_T string="Member"}</th>
                        <th class="listing">{_T string="Amount"}</th>
                        <th class="listing">{_T string="Not dispatched amount"}</th>
                    </tr>
                    <tr>
                        <td>{$contribution->transaction->id}</td>
                        <td>{$contribution->transaction->date}</td>
                        <td>{memberName id="$mid"}</td>
                        <td class="right">{$contribution->transaction->amount}</td>
                        <td class="right">{$contribution->transaction->getMissingAmount()}</td>
                    </tr>
                </tbody>
            </table>
    {/if}
            <p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
            <fieldset class="cssform">
                <legend class="ui-state-active ui-corner-top">{if $type eq {_T string="fee" domain="routes"}}{_T string="Select contributor and membership fee type"}{else}{_T string="Select contributor and donation type"}{/if}</legend>
                <p>
                    <label for="id_adh" class="bline">{_T string="Contributor:"}</label>
                    <select name="id_adh" id="id_adh"{if isset($disabled.id_adh)} {$disabled.id_adh}{/if}>
                        {if $adh_selected eq 0}
                        <option value="">{_T string="-- select a name --"}</option>
                        {/if}
                        {foreach $adh_options as $k=>$v}
                            <option value="{$k}"{if $contribution->member == $k} selected="selected"{/if}>{$v}</option>
                        {/foreach}
                    </select>
                </p>
                <p>
                    <label for="id_type_cotis" class="bline">{_T string="Contribution type:"}</label>
                    <select name="id_type_cotis" id="id_type_cotis"{if $required.id_type_cotis eq 1} required="required"{/if}>
                        {if $contribution->type}
                            {assign var="selectedid" value=$contribution->type->id}
                        {else}
                            {assign var="selectedid" value=null}
                        {/if}
                        {html_options options=$type_cotis_options selected=$selectedid}
                    </select>
                </p>
    {if $type eq {_T string="fee" domain="routes"}}
                <noscript>
                    <div class="button-container" id="reloadcont">
                        <input type="submit" id="btnreload" name="btnreload" value="{_T string="Reload"}" title="{_T string="Reload date informations according to selected member and contribution type"}"/>
                    </div>
                </noscript>
    {/if}
            </fieldset>

            <fieldset class="cssform">
                <legend class="ui-state-active ui-corner-top">{if $type eq {_T string="fee" domain="routes"}}{_T string="Details of membership fee"}{else}{_T string="Details of donation"}{/if}</legend>
                <p>
                    <label class="bline" for="montant_cotis">{_T string="Amount:"}</label>
                    <input type="text" name="montant_cotis" id="montant_cotis" value="{$contribution->amount}" maxlength="10"{if $required.montant_cotis eq 1} required="required"{/if}/>
                </p>
                <p>
                    <label class="bline" for="type_paiement_cotis">{_T string="Payment type:"}</label>
                    <select name="type_paiement_cotis" id="type_paiement_cotis">
                        <option value="{Galette\Entity\Contribution::PAYMENT_CASH}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_CASH')} selected="selected"{/if}>{_T string="Cash"}</option>
                        <option value="{Galette\Entity\Contribution::PAYMENT_CREDITCARD}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_CREDITCARD')} selected="selected"{/if}>{_T string="Credit card"}</option>
                        <option value="{Galette\Entity\Contribution::PAYMENT_CHECK}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_CHECK')} selected="selected"{/if}>{_T string="Check"}</option>
                        <option value="{Galette\Entity\Contribution::PAYMENT_TRANSFER}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_TRANSFER')} selected="selected"{/if}>{_T string="Transfer"}</option>
                        <option value="{Galette\Entity\Contribution::PAYMENT_PAYPAL}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_PAYPAL')} selected="selected"{/if}>{_T string="Paypal"}</option>
                        <option value="{Galette\Entity\Contribution::PAYMENT_OTHER}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_OTHER')} selected="selected"{/if}>{_T string="Other"}</option>
                    </select>
                </p>
                <p>
                    <label class="bline" for="date_enreg">
                        {_T string="Record date:"}
                    </label>
                    <input class="past-date-pick" type="text" name="date_enreg" id="date_enreg" value="{$contribution->date}" maxlength="10"{if $required.date_enreg eq 1} required="required"{/if}/>
                    <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
                </p>

                <p>
                    <label class="bline" for="date_debut_cotis">
                        {if $type eq {_T string="fee" domain="routes"}}
                            {_T string="Start date of membership:"}
                        {else}
                            {_T string="Date of contribution:"}
                        {/if}
                    </label>
                    <input class="past-date-pick" type="text" name="date_debut_cotis" id="date_debut_cotis" value="{$contribution->begin_date}" maxlength="10"{if $required.date_debut_cotis eq 1} required="required"{/if}/>
                    <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
                </p>
        {if $type eq {_T string="fee" domain="routes"}}
                <p>
            {if $pref_membership_ext != ""}
                    <label class="bline" for="duree_mois_cotis">{_T string="Membership extension:"}</label>
                    <input type="text" name="duree_mois_cotis" id="duree_mois_cotis" value="{$contribution->duration}" maxlength="3"{if $required.date_fin_cotis eq 1} required="required"{/if}/>
                    <span class="exemple">{_T string="months"}</span>
            {else}
                    <label class="bline" for="date_fin_cotis">{_T string="End date of membership:"}</label>
                    <input type="text" name="date_fin_cotis" id="date_fin_cotis" value="{$contribution->end_date}" maxlength="10"{if $required.date_fin_cotis eq 1} required="required"{/if}/>
                    <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
            {/if}
                </p>
        {/if}
                <p>
                    <label class="bline" for="info_cotis">{_T string="Comments:"}</label>
                    <textarea name="info_cotis" id="info_cotis" cols="61" rows="6"{if isset($required.info_cotis) and $required.info_cotis eq 1} required="required"{/if}>{$contribution->info}</textarea>
                </p>
            </fieldset>

    {if $contribution->isTransactionPart() && $contribution->transaction->getMissingAmount()}
            <fieldset class="cssform" id="transaction_related">
                <legend class="ui-state-active ui-corner-top">{_T string="Transaction related"}</legend>
                <p>
                    <span class="bline tooltip" title="{_T string="Select a contribution type to create for dispatch transaction"}">{_T string="Dispatch type:"}</span>
                    <span class="tip">{_T string="Select a contribution type to create for dispatch transaction"}</span>
                    <input type="radio" name="contrib_type" id="contrib_type_fee" value="{_T string="fee" domain="routes"}" checked="checked"/> <label for="contrib_type_fee">{_T string="Membership fee"}</label>
                    <input type="radio" name="contrib_type" id="contrib_type_donation" value="{_T string="donation" domain="routes"}"/> <label for="contrib_type_donation">{_T string="Donation"}</label>
                </p>
            </fieldset>
    {/if}
        {include file="edit_dynamic_fields.tpl" object=$contribution}
    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
            <p>
                <label for="mail_confirm">{_T string="Notify member"}</label>
                <input type="checkbox" name="mail_confirm" id="mail_confirm" value="1" {if isset($smarty.post.mail_confirm) and $smarty.post.mail_confirm != ""}checked="checked"{/if}/>
                <br/><span class="exemple">{_T string="Member will receive a notification by email, if he has an address."}</span>
            </p>
    {/if}
        </div>
        <div class="button-container">
            <input type="submit" id="btnsave" value="{_T string="Save"}"/>
            <input type="hidden" name="id_cotis" value="{$contribution->id}"/>
            <input type="hidden" name="valid" value="1"/>
            <input type="hidden" name="trans_id" value="{if $contribution->transaction neq NULL}{$contribution->transaction->id}{/if}"/>
        </div>
        </form>
{else} {* No members *}
    <div class="center" id="warningbox">
        <h3>{_T string="No member registered!"}</h3>
        <p>
            {_T string="Unfortunately, there is no member in your database yet,"}
            <br/>
            <a href="{path_for name="editmember" data=["action" => {_T string="add" domain="routes"}]}">{_T string="please create a member"}</a>
        </p>
    </div>
{/if}
{/block}

{block name="javascripts"}
<script type="text/javascript">
    $(function() {
        $.datepicker.setDefaults($.datepicker.regional['{$galette_lang}']);
        $('#date_debut_cotis, #date_fin_cotis, #date_enreg').datepicker({
            changeMonth: true,
            changeYear: true,
            showOn: 'button',
            buttonImage: '{base_url}/{$template_subdir}images/calendar.png',
            buttonImageOnly: true,
            buttonText: '{_T string="Select a date" escape="js"}'
        });

    {if $type eq {_T string="fee" domain="routes"} and !$contribution->id}
        $('#id_adh, #id_type_cotis').on('change', function() {
            var _this = $(this);
            var _member = $('#id_adh').val();
            var _fee    = $('#id_type_cotis').val();

            $.ajax({
                type: 'POST',
                dataType: 'json',
                url : '{path_for name="contributionDates"}',
                data: {
                    member_id: _member,
                    fee_id: _fee
                },
                {include file="js_loader.tpl"},
                success: function(res){
                    $('#date_debut_cotis').val(res.date_debut_cotis);
                    $('#date_fin_cotis').val(res.date_fin_cotis);
                },
                error: function() {
                    alert("{_T string="An error occured retrieving dates :(" escape="js"}");
                }
            });

        });
    {/if}

    {if $contribution->isTransactionPart() && $contribution->transaction->getMissingAmount()}
        $('#transaction_related').hide();
        $('#montant_cotis').on('keyup', function() {
            var _amount = {$contribution->transaction->getMissingAmount()};
            var _current = $(this).val();
            if (_current < _amount) {
                $('#transaction_related').show();
            } else if (_current > _amount) {
                alert('{_T string="Contribution amount should be greater than %max" pattern="/%max/" replace=$contribution->transaction->getMissingAmount() escape="js"}');
            } else {
                $('#transaction_related').hide();
            }
        });
    {/if}
    });
</script>
{/block}
