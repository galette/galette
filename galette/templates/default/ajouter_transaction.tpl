		<h1 id="titre">{_T("New transaction")}</h1>
		<form action="ajouter_transaction.php" method="post">
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
		<blockquote>
		<div align="center">
			<table border="0" id="input-table">
				<tr>
					<th {if $required.trans_desc eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Description:")}</th>
					<td colspan="3"><input type="text" name="trans_desc" value="{$data.trans_desc}" maxlength="30" size="30"/></td>
				</tr>
				<tr>
					<th {if $required.trans_date eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Date:")}</th>
					<td>
						<input type="text" name="trans_date" value="{$data.trans_date}" maxlength="10"/><br/>
						<div class="exemple">{_T("(dd/mm/yyyy format)")}</div>
					</td>
					<th {if $required.trans_amount eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Amount:")}</th>
					<td><input type="text" name="trans_amount" value="{$data.trans_amount}" maxlength="10"/></td>
				</tr>
				<tr>
					<th {if $required.id_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Originator:")}</th>
					<td colspan="3">
						<select name="id_adh">
							{if $data.id_adh == 0}
							<option>{_T("-- select a name --")}</option>
							{/if}
							{html_options options=$adh_options selected=$data.id_adh}
						</select>
					</td>
				</tr>
{include file="display_dynamic_fields.tpl" is_form=true}
				<tr>
					<th align="center" colspan="4"><br/><input type="submit" class="submit" value="{_T("Save")}"/></th>
				</tr>
			</table>
		</div>
		<br/>
		{_T("NB : The mandatory fields are in")} <font style="color: #FF0000">{_T("red")}</font>.
		</blockquote>
		<input type="hidden" name="trans_id" value="{$data.trans_id}"/>
		<input type="hidden" name="valid" value="1"/>
		</form>
