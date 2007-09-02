	<h1 class="titre">{_T("Required fields for adherents")}</h1>
	<form action="champs_requis.php" method="post">
		<fieldset class="cssform">
			<legend>{_T("Select mandatory fields for new adherents:")}</legend>
			{foreach key=col item=value from=$fields}
				<p>
					<span class="bline libelle">{if isset($adh_fields[$value])}{$adh_fields[$value]}{else}{$value}{/if}</span>
					<label for="{$value}_yes">{_T("Yes")}</label>
					<input type="radio" name="{$value}" id="{$value}_yes" value="1"{if isset($required[$value])} checked="checked"{/if}/>
					<label for="{$value}_no">{_T("No")}</label>
					<input type="radio" name="{$value}" id="{$value}_no" value="0"{if !isset($required[$value])} checked="checked"{/if}/>
				</p>
			{/foreach}
		</fieldset>
		<div class="button-container">
			<input type="submit" class="submit" value="{_T("Save")}"/>
		</div>
	</form>
