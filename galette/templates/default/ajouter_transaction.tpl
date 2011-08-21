		<h1 id="titre">{_T string="New transaction"}</h1>
		<form action="ajouter_transaction.php" method="post">
{if $error_detected|@count != 0}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
		<div class="bigtable">
			<fieldset class="cssform">
				<legend class="ui-state-active ui-corner-top">{_T string="Transaction details"}</legend>
				<p>
					<label for="trans_desc" class="bline">{_T string="Description:"}</label>
					<input type="text" name="trans_desc" id="trans_desc" value="{$transaction->description}" maxlength="30" size="30"{if $required.trans_desc eq 1} required{/if}/>
				</p>
				<p>
					<label for="id_adh" class="bline" >{_T string="Originator:"}</label>
					<select name="id_adh" id="id_adh"{if $required.id_adh eq 1} required{/if}>
{if !$transaction->member}
						<option>{_T string="-- select a name --"}</option>
{/if}
{html_options options=$adh_options selected=$transaction->member}
					</select>
				</p>
				<p>
					<label for="trans_date" class="bline">{_T string="Date:"}</label>
					<input type="text" class="date-pick" name="trans_date" id="trans_date" value="{$transaction->date}" maxlength="10"{if $required.trans_date eq 1} required{/if}/> <span class="exemple">{_T string="(dd/mm/yyyy format)"}</span>
				</p>
				<p>
					<label for="trans_amount" class="bline">{_T string="Amount:"}</label>
					<input type="text" name="trans_amount" id="trans_amount" value="{$transaction->amount}" maxlength="10"{if $required.trans_amount eq 1} required{/if}/>
				</p>
			</fieldset>
		</div>
{include file="display_dynamic_fields.tpl" is_form=true}
		<div class="button-container">
			<input id="btnsave" type="submit" value="{_T string="Save"}"/>
			<input type="hidden" name="trans_id" value="{$transaction->id}"/>
			<input type="hidden" name="valid" value="1"/>
		</div>
		<p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
		</form>
{if $contribs}
		<table class="center_table">
            <caption>{_T string="Attached contributions"}</caption>
			<thead>
				<tr>
					<th class="listing id_row">#</th>
					<th class="listing left date_row">{_T string="Date"}</th>
					<th class="listing left date_row">{_T string="Begin"}</th>
					<th class="listing left date_row">{_T string="End"}</th>
					<th class="listing left">{_T string="Duration"}</th>
    {if $login->isAdmin()}
					<th class="listing left">{_T string="Member"}</th>
    {/if}
					<th class="listing left">{_T string="Type"}</th>
					<th class="listing left">{_T string="Amount"}</th>
				</tr>
			</thead>
            <tfoot>
                <tr>
                    <th class="right" colspan="{if $login->isAdmin()}7{else}6{/if}">{_T string="Dispatched amount:"}</th>
                    <th class="right">{$transaction->getDispatchedAmount()}</th>
                </tr>
                <tr>
                    <th class="right" colspan="{if $login->isAdmin()}7{else}6{/if}">{_T string="Not dispatched amount:"}</th>
                    <th class="right">{$transaction->getMissingAmount()}</th>
                </tr>
            </tfoot>
			<tbody>
    {foreach from=$contribs item=contrib key=ordre}
        {assign var="mid" value=$contrib->member}
        {assign var="cclass" value=$contrib->getRowClass()}
				<tr>
					<td class="{$cclass} center nowrap">
                        {php}$ordre = $this->get_template_vars('ordre');echo $ordre+1{/php}
                    </td>
					<td class="{$cclass} center nowrap">{$contrib->date}</td>
					<td class="{$cclass} center nowrap">{$contrib->begin_date}</td>
					<td class="{$cclass} center nowrap">{$contrib->end_date}</td>
					<td class="{$cclass} nowrap">{$contrib->duration}</td>
        {if $login->isAdmin()}
					<td class="{$cclass}">{memberName id="$mid"}</td>
        {/if}
					<td class="{$cclass}">{$contrib->type->libelle}</td>
					<td class="{$cclass} nowrap right">{$contrib->amount}</td>
				</tr>
    {foreachelse}
				<tr><td colspan="{if $login->isAdmin()}8{else}7{/if}" class="emptylist">{_T string="no contribution"}</td></tr>
    {/foreach}
			</tbody>
		</table>
{/if} {* $contribs *}
