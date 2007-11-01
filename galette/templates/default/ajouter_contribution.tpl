		<h1 id="titre">{_T("Contribution card")} ({if $data.id_cotis != ""}{_T("modification")}{else}{_T("creation")}{/if})</h1>
		<form action="ajouter_contribution.php" method="post">
{if $error_detected|@count != 0}
		<div id="errorbox">
			<h1>{_T("- ERROR -")}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
		<div class="bigtable tabbed">
			<fieldset class="cssform">
				<legend>{_T("Select contributor and contribution type")}</legend>
				<p>
					<label for="id_adh" class="bline{if $required.id_adh eq 1} required{/if}">{_T("Contributor:")}</label>
					<select name="id_adh" id="id_adh">
						{if $adh_selected eq 0}
						<option value="0">{_T("-- select a name --")}</option>
						{/if}
						{html_options options=$adh_options selected=$data.id_adh}
					</select>
				</p>
				<p>
					<label for="id_type_cotis" class="bline{if $required.id_type_cotis eq 1} required{/if}">{_T("Contribution type:")}</label>
					<select name="id_type_cotis" id="id_type_cotis"
						{if $type_selected eq 0}onchange="form.submit()"{/if}>
						{html_options options=$type_cotis_options selected=$data.id_type_cotis}
					</select>
				</p>
			</fieldset>

			<table border="0" id="input-table">
				{if $type_selected eq 1}
				<tr>
					<th {if $required.montant_cotis eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Amount:")}</th>
					<td {if !$data.trans_id}colspan="3"{/if}><input type="text" name="montant_cotis" value="{$data.montant_cotis}" maxlength="10"/></td>
				</tr>
				<tr>
					{if $data.trans_id}
					<th class="libelle">{_T("Transaction number:")}</th>
					<td >{$data.trans_id}</td>
					{/if}
				</tr>
				<tr>
					<th {if $required.date_debut_cotis eq 1}style="color: #FF0000;"{/if} class="libelle">
						{if $cotis_extension eq 0}
							{_T("Date of contribution:")}
						{else}
							{_T("Start date of membership:")}
						{/if}
						<br/>&nbsp;</th>
					<td {if $cotis_extension eq 0}colspan="2"{/if}>
						<input type="text" name="date_debut_cotis" value="{$data.date_debut_cotis}" maxlength="10"/><br/>
						<div class="exemple">{_T("(dd/mm/yyyy format)")}</div>
					</td>
				</tr>
				<tr>
					{if $cotis_extension ne 0}
					<th {if $required.date_fin_cotis eq 1}style="color: #FF0000;"{/if} class="libelle">
						{if $pref_membership_ext != ""}
							{_T("Membership extension:")}
						{else}
							{_T("End date of membership:")}
						{/if}
						<br/>&nbsp;
					</th>
					<td>
						{if $pref_membership_ext != ""}
						<input type="text" name="duree_mois_cotis" value="{$data.duree_mois_cotis}" maxlength="3"/><br/>
						<div class="exemple">{_T("months")}</div>
						{else}
						<input type="text" name="date_fin_cotis" value="{$data.date_fin_cotis}" maxlength="10"/><br/>
						<div class="exemple">{_T("(dd/mm/yyyy format)")}</div>
						{/if}
					</td>
					{/if}
				</tr>
				<tr>
					<th {if $required.info_cotis eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Comments:")}</th>
					<td ><textarea name="info_cotis" cols="61" rows="6">{$data.info_cotis}</textarea></td>
				</tr>
				<tr>
					<th colspan="2" class="libelle">{include file="display_dynamic_fields.tpl" is_form=true}</th>
				</tr>
				<tr>
					<th align="center" colspan="2"><br/><input type="submit" class="submit" value="{_T("Save")}"/></th>
				</tr>
				{else} {* $type_selected ne 1 *}
				<tr>
					<th align="center" colspan="2"><br/><input type="submit" class="submit" value="{_T("Continue")}"/></th>
				</tr>
				{/if} {* $type_selected eq 1 *}
			</table>
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
		<p>{_T("NB : The mandatory fields are in")} <span class="required">{_T("red")}</span></p>
		</form>