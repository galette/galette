	<form action="champs_requis.php" method="post">
		<fieldset class="cssform">
			<legend class="ui-state-active ui-corner-top">{_T string="Select mandatory fields for new adherents:"}</legend>
			{foreach key=col item=value from=$fields}
                {if $value != 'login_adh' && $value != 'mdp_adh'}
				<p>
					<span class="bline libelle">{if isset($adh_fields[$value])}{$adh_fields[$value]}{else}{$value}{/if}</span>
					<label for="{$value}_yes">{_T string="Yes"}</label>
					<input type="radio" name="{$value}" id="{$value}_yes" value="1"{if isset($required[$value])} checked="checked"{/if}/>
					<label for="{$value}_no">{_T string="No"}</label>
					<input type="radio" name="{$value}" id="{$value}_no" value="0"{if !isset($required[$value])} checked="checked"{/if}/>
				</p>
                {else}
                <input type="hidden" name="{$value}" value="1"/>
                {/if}
			{/foreach}
		</fieldset>
		<div class="button-container">
			<input type="submit" value="{_T string="Save"}"/>
		</div>
	</form>
