		<h1 id="titre">{_T string="Contribution card"} ({if $data.id_cotis != ""}{_T string="modification"}{else}{_T string="creation"}{/if})</h1>
		<form action="ajouter_contribution.php" method="post">
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
				<legend>{_T string="Select contributor and contribution type"}</legend>
				<p>
					<label for="id_adh" class="bline{if $required.id_adh eq 1} required{/if}">{_T string="Contributor:"}</label>
					<select name="id_adh" id="id_adh">
						{if $adh_selected eq 0}
						<option value="0">{_T string="-- select a name --"}</option>
						{/if}
						{html_options options=$adh_options selected=$data.id_adh}
					</select>
				</p>
				<p>
					<label for="id_type_cotis" class="bline{if $required.id_type_cotis eq 1} required{/if}">{_T string="Contribution type:"}</label>
					<select name="id_type_cotis" id="id_type_cotis"
						{if $type_selected eq 0}onchange="form.submit()"{/if}>
						{html_options options=$type_cotis_options selected=$data.id_type_cotis}
					</select>
				</p>
			</fieldset>

			{if $type_selected eq 1}
			<fieldset class="cssform">
				<legend>{_T string="Details of contribution"}</legend>
				<p>
					<label {if $required.montant_cotis eq 1}style="color: #FF0000;"{/if} class="bline">{_T string="Amount:"}</label>
					<input type="text" name="montant_cotis" value="{$data.montant_cotis}" maxlength="10"/>
				</p>
				{if $data.id_cotis != ""}
				<p>
					<label class="bline">{_T string="Transaction number:"}</label>
					{$data.trans_id}
				</p>
				{/if}
				<p>
					<label {if $required.date_debut_cotis eq 1}style="color: #FF0000;"{/if} class="bline">
						{if $cotis_extension eq 0}
							{_T string="Date of contribution:"}
						{else}
							{_T string="Start date of membership:"}
						{/if}
						<br/>&nbsp;</label>
						<input class="past-date-pick" type="text" name="date_debut_cotis" value="{$data.date_debut_cotis}" maxlength="10"/><br/>
						<span class="exemple">{_T string="(dd/mm/yyyy format)"}</span>
				</p>
				<p>
					{if $cotis_extension ne 0}
					<label {if $required.date_fin_cotis eq 1}style="color: #FF0000;"{/if} class="bline">
						{if $pref_membership_ext != ""}
							{_T string="Membership extension:"}
						{else}
							{_T string="End date of membership:"}
						{/if}
						<br/>&nbsp;
					</label>
						{if $pref_membership_ext != ""}
						<input type="text" name="duree_mois_cotis" value="{$data.duree_mois_cotis}" maxlength="3"/><br/>
						<span class="exemple">{_T string="months"}</span>
						{else}
						<input type="text" name="date_fin_cotis" value="{$data.date_fin_cotis}" maxlength="10"/><br/>
						<span class="exemple">{_T string="(dd/mm/yyyy format)"}</span>
						{/if}
					{/if}
				</p>
				<p>
					<label for="mail_confirm" class="bline">{_T string="Send a mail:"}</label>
					<input type="checkbox" name="mail_confirm" id="mail_confirm" value="1" {if $smarty.post.mail_confirm != ""}checked="checked"{/if}/>
					<span class="exemple">{_T string="(the member will receive a confirmation by email, if he has an address.)"}</span>
				</p>
				<p>
					<label {if $required.info_cotis eq 1}style="color: #FF0000;"{/if} class="bline">{_T string="Comments:"}</label>
					<textarea name="info_cotis" cols="61" rows="6">{$data.info_cotis}</textarea>
				</p>
			</fieldset>
{include file="display_dynamic_fields.tpl" is_form=true}
			{/if} {* $type_selected eq 1 *}
		</div>
		<div class="button-container">
{if $type_selected eq 1}
			<input type="submit" id="btnsave" value="{_T string="Save"}"/>
{else} {* $type_selected ne 1 *}
			<input type="submit" value="{_T string="Continue"}"/>
{/if} {* $type_selected eq 1 *}
			<input type="hidden" name="id_cotis" value="{$data.id_cotis}"/>
			<input type="hidden" name="trans_id" value="{$data.trans_id}"/>
			{if $type_selected eq 1}
			<input type="hidden" name="valid" value="1"/>
			{else}
			<input type="hidden" name="montant_cotis" value="{$data.montant_cotis}"/>
			{/if} {* $type_selected eq 1 *}
			<input type="hidden" name="type_selected" value="1"/>
			<input type="hidden" name="cotis_extension" value="{$cotis_extension}"/>
		</div>
		<p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
		</form>