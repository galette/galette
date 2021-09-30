{if isset($mode) && $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}

{block name="content"}
{if isset($members.list) || $require_mass}
        <form action="{if $contribution->id}{path_for name="doEditContribution" data=["type" => $type, "id" => $contribution->id]}{else}{path_for name="doAddContribution" data=["type" => $type]}{/if}" enctype="multipart/form-data" method="post">
        <div class="bigtable">
    {if $contribution->isTransactionPart()}
        {assign var="mid" value=$contribution->transaction->member}
            <table id="transaction_detail">
                <caption>{_T string="Related transaction information"}</caption>
                <thead>
                    <tr>
                        <td colspan="5">
                            {$contribution->transaction->description}
                            <a href="{path_for name="editTransaction" data=["id" => $contribution->transaction->id]}" title="{_T string="View transaction"}">
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
                <legend class="ui-state-active ui-corner-top">
    {if $type eq constant('Galette\Entity\Contribution::TYPE_FEE')}
                    {_T string="Select contributor and membership fee type"}
    {else}
                    {_T string="Select contributor and donation type"}
    {/if}
    {if $contribution->isTransactionPart() && $contribution->transaction->getMissingAmount() > 0}
                    <a
                        href="{path_for name="addContribution" data=["type" => constant('Galette\Entity\Contribution::TYPE_FEE')]}?trans_id={$contribution->transaction->id}"
                        class="button fright tooltip"
                        title="{_T string="Create a new fee that will be attached to the current transaction"}">
                        <i class="fas fa-user-check"></i>
                        <span class="sr-only">{_T string="New attached fee"}</span>
                    </a>
                    <a
                        href="{path_for name="addContribution" data=["type" => "donation"]}?trans_id={$contribution->transaction->id}"
                        class="button fright tooltip"
                        title="{_T string="Create a new donation that will be attached to the current transaction"}">
                        <i class="fas fa-gift"></i>
                        <span class="sr-only">{_T string="New attached donation"}</span>
                    </a>
    {/if}
</legend>
    {if !$require_mass}
                <p>
                    <label for="id_adh" class="bline">{_T string="Contributor:"}</label>
                    <select name="id_adh" id="id_adh" class="nochosen">
                        {if $adh_selected eq 0}
                        <option value="">{_T string="Search for name or ID and pick member"}</option>
                        {/if}
                        {foreach $members.list as $k=>$v}
                            <option value="{$k}"{if $contribution->member == $k} selected="selected"{/if}>{$v}</option>
                        {/foreach}
                    </select>
                </p>
    {/if}
                <p>
                    <label for="id_type_cotis" class="bline">{_T string="Contribution type:"}</label>
                    <select name="id_type_cotis" id="id_type_cotis"{if isset($required.id_type_cotis) &&  ($required.id_type_cotis eq 1)} required="required"{/if}>
                        {if $contribution->type}
                            {assign var="selectedid" value=$contribution->type->id}
                        {else}
                            {assign var="selectedid" value=null}
                        {/if}
                        {html_options options=$type_cotis_options selected=$selectedid}
                    </select>
                </p>
    {if $type eq constant('Galette\Entity\Contribution::TYPE_FEE')}
                <noscript>
                    <div class="button-container" id="reloadcont">
                        <input type="submit" id="btnreload" name="btnreload" value="{_T string="Reload"}" title="{_T string="Reload date information according to selected member and contribution type"}"/>
                    </div>
                </noscript>
    {/if}
            </fieldset>

            <fieldset class="cssform">
                <legend class="ui-state-active ui-corner-top">{if $type eq constant('Galette\Entity\Contribution::TYPE_FEE')}{_T string="Details of membership fee"}{else}{_T string="Details of donation"}{/if}</legend>
                <p>
                    <label class="bline" for="montant_cotis">{_T string="Amount:"}</label>
                    <input type="text" name="montant_cotis" id="montant_cotis" value="{$contribution->amount}" maxlength="10"{if isset($required.montant_cotis) &&  ($required.montant_cotis eq 1)} required="required"{/if}/>
                </p>
                {* payment type *}
                {assign var="ptype" value=$contribution->payment_type}
                {if $ptype == null}
                    {assign var="ptype" value=constant('Galette\Entity\PaymentType::CHECK')}
                {/if}
                {include file="forms_types/payment_types.tpl" current=$ptype varname="type_paiement_cotis"}
                <p>
                    <label class="bline" for="date_enreg">
                        {_T string="Record date:"}
                    </label>
                    <input class="past-date-pick" type="text" name="date_enreg" id="date_enreg" value="{$contribution->date}" maxlength="10"{if isset($required.date_enreg) &&  ($required.date_enreg eq 1)} required="required"{/if}/>
                    <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
                </p>

                <p>
                    <label class="bline" for="date_debut_cotis">
                        {if $type eq constant('Galette\Entity\Contribution::TYPE_FEE')}
                            {_T string="Start date of membership:"}
                        {else}
                            {_T string="Date of contribution:"}
                        {/if}
                    </label>
                    <input class="past-date-pick" type="text" name="date_debut_cotis" id="date_debut_cotis" value="{$contribution->begin_date}" maxlength="10"{if isset($required.date_debut_cotis) &&  ($required.date_debut_cotis eq 1)} required="required"{/if}/>
                    <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
                </p>
        {if $type eq constant('Galette\Entity\Contribution::TYPE_FEE')}
                <p>
            {if $preferences->pref_membership_ext != ""}
                    <label class="bline" for="duree_mois_cotis">{_T string="Membership extension:"}</label>
                    <input type="text" name="duree_mois_cotis" id="duree_mois_cotis" value="{$contribution->duration}" maxlength="3"{if isset($required.date_fin_cotis) &&  ($required.date_fin_cotis eq 1)} required="required"{/if}/>
                    <span class="exemple">{_T string="months"}</span>
            {else}
                    <label class="bline" for="date_fin_cotis">{_T string="End date of membership:"}</label>
                    <input type="text" name="date_fin_cotis" id="date_fin_cotis" value="{$contribution->end_date}" maxlength="10"{if isset($required.date_fin_cotis) &&  ($required.date_fin_cotis eq 1)} required="required"{/if}/>
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
                    <input type="radio" name="contrib_type" id="contrib_type_fee" value="{constant('Galette\Entity\Contribution::TYPE_FEE')}" checked="checked"/> <label for="contrib_type_fee">{_T string="Membership fee"}</label>
                    <input type="radio" name="contrib_type" id="contrib_type_donation" value="donation"/> <label for="contrib_type_donation">{_T string="Donation"}</label>
                </p>
            </fieldset>
    {/if}
        {include file="edit_dynamic_fields.tpl" object=$contribution}
    {if not $contribution->id and $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
        {if !$require_mass}
            <p>
                <label for="mail_confirm">{_T string="Notify member"}</label>
                <input type="checkbox" name="mail_confirm" id="mail_confirm" value="1" {if $preferences->pref_bool_mailowner || isset($smarty.post.mail_confirm) and $smarty.post.mail_confirm != ""}checked="checked"{/if}/>
                <br/><span class="exemple">{_T string="Member will receive a notification by email, if he has an address."}</span>
            </p>
        {/if}
    {/if}
        </div>
    {if !$require_mass}
        <div class="button-container">
            <button type="submit" name="valid" class="action">
                <i class="fas fa-save fa-fw"></i> {_T string="Save"}
            </button>
            <input type="hidden" name="id_cotis" value="{$contribution->id}"/>
            <input type="hidden" name="valid" value="1"/>
            <input type="hidden" name="trans_id" value="{if $contribution->transaction neq NULL}{$contribution->transaction->id}{/if}"/>
        </div>
    {/if}
        </form>
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

    $(function() {
        $('#date_debut_cotis, #date_fin_cotis, #date_enreg').datepicker({
            changeMonth: true,
            changeYear: true,
            showOn: 'button',
            buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
        });

    {if $type eq constant('Galette\Entity\Contribution::TYPE_FEE') and !$contribution->id}
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
                    alert("{_T string="An error occurred retrieving dates :(" escape="js"}");
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
