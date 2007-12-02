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
		<div class="bigtable">
			<fieldset class="cssform">
				<legend>{_T("Transaction details")}</legend>
				<p>
					<label for="trans_desc" class="bline{if $required.trans_desc eq 1} required{/if}">{_T("Description:")}</label>
					<input type="text" name="trans_desc" id="trans_desc" value="{$data.trans_desc}" maxlength="30" size="30"/>
				</p>
				<p>
					<label for="id_adh" class="bline{if $required.id_adh eq 1} required{/if}" >{_T("Originator:")}</label>
					<select name="id_adh" id="id_adh">
{if $data.id_adh == 0}
						<option>{_T("-- select a name --")}</option>
{/if}
{html_options options=$adh_options selected=$data.id_adh}
					</select>
				</p>
				<p>
					<label for="trans_date" class="bline{if $required.trans_date eq 1} required{/if}">{_T("Date:")}</label>
					<input type="text" name="trans_date" id="trans_date" value="{$data.trans_date}" maxlength="10"/> <span class="exemple">{_T("(dd/mm/yyyy format)")}</span>
				</p>
				<p>
					<label for="trans_amount" class="bline{if $required.trans_amount eq 1} required{/if}">{_T("Amount:")}</label>
					<input type="text" name="trans_amount" id="trans_amount" value="{$data.trans_amount}" maxlength="10"/>
				</p>
			</fieldset>
		</div>
{include file="display_dynamic_fields.tpl" is_form=true}
		<div class="button-container">
			<input type="submit" class="submit" value="{_T("Save")}"/>
			<input type="hidden" name="trans_id" value="{$data.trans_id}"/>
			<input type="hidden" name="valid" value="1"/>
		</div>
		<p>{_T("NB : The mandatory fields are in")} <span class="required">{_T("red")}</span></p>
		</form>
