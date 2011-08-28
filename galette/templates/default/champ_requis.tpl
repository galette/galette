	<form action="champs_requis.php" method="post">
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
{if $required_stored}
	<div id="infobox">{_T string="Required fields has been saved."}</div>
{/if}
		<fieldset class="cssform">
			<legend class="ui-state-active ui-corner-top">{_T string="Select mandatory fields for new adherents:"}</legend>
			{foreach key=col item=value from=$fields}
				<p>
					<span class="bline libelle">{if isset($adh_fields[$value])}{$adh_fields[$value]}{else}{$value}{/if}</span>
					<label for="{$value}_yes">{_T string="Yes"}</label>
					<input type="radio" name="{$value}" id="{$value}_yes" value="1"{if isset($required[$value])} checked="checked"{/if}/>
					<label for="{$value}_no">{_T string="No"}</label>
					<input type="radio" name="{$value}" id="{$value}_no" value="0"{if !isset($required[$value])} checked="checked"{/if}/>
				</p>
			{/foreach}
		</fieldset>
		<div class="button-container">
			<input type="submit" value="{_T string="Save"}"/>
		</div>
	</form>
