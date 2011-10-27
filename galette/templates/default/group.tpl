		<form action="ajouter_groupe.php" method="post" enctype="multipart/form-data" id="form">
{* FIXME: a bad hack... Title will go to page.tpl in the future as well as error/warnings (see public_page.tpl) *}
{if $error_detected|@count != 0 and $login->isLogged()}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
{if $warning_detected|@count != 0}
		<div id="warningbox">
			<h1>{_T string="- WARNING -"}</h1>
			<ul>
				{foreach from=$warning_detected item=warning}
					<li>{$warning}</li>
				{/foreach}
			</ul>
		</div>
{/if}
		<div class="bigtable">
			<fieldset class="cssform">
				<legend class="ui-state-active ui-corner-top">{_T string="Groups:"}</legend>
				<div>
					<p>
						<label for="group_name" class="bline">{_T string="Name:"}</label>
						<input type="text" name="group_name" id="group_name" value="{$group->getName()}" maxlength="20" required/>
					</p>
					<p>
                        {assign var="owner" value=$group->getOwner()}
						<label for="group_owner" class="bline">{_T string="Owner:"}</label>
						<input type="text" name="group_owner" id="group_owner" value="{$owner->id}" maxlength="20" required/> {if $owner->id != ''} ({$owner->sname}){/if}
					</p>
				</div>
			</fieldset>

		</div>
		<div class="button-container">
			<input type="submit" name="valid" id="btnsave" value="{_T string="Save"}"/>
			<input type="hidden" name="id_group" value="{$group->getId()}"/>
		</div>
		<p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
		</form>