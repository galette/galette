		<h1 class="titre">{_T("Automatic emails texts edition")}</h1>
		<form action="gestion_textes.php" method="post" enctype="multipart/form-data"> 
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
{if $warning_detected|@count != 0}
		<div id="warningbox">
			<h1>{_T("- WARNING -")}</h1>
			<ul>
{foreach from=$warning_detected item=warning}
				<li>{$warning}</li>
{/foreach}
			</ul>
		</div>
{/if}

		<div class="bigtable">
			<fieldset class="cssform" id="{$mtxt.tlang}">
				<legend>{$mtxt.tcomment}</legend>
				<p>
					<label class="bline">{_T("Language:")}</label>
					<select name="sel_lang" onchange="form.submit()">
						{foreach item=langue from=$langlist}
							<option value="{$langue->getID()}" {if $cur_lang eq $langue->getID()}selected="selected"{/if} style="padding-left: 30px; background-image: url({$langue->getFlag()}); background-repeat: no-repeat">{$langue->getName()}</option>
						{/foreach}
					</select>
				</p>
				<p>
					<label class="bline">{_T("Reference:")}</label>
					<select name="sel_ref" onchange="form.submit()">
						{foreach item=ref from=$reflist}
							<option value="{$ref.tref}" {if $cur_ref eq $ref.tref}selected="selected"{/if} >{$ref.tcomment}</option>
						{/foreach}
					</select>
				</p>
				<p>
					<label class="bline">{_T("Email Subject")}</label> 
					<input type="text" name="text_subject" id="tsubject" value="{$mtxt.tsubject}" maxlength="32" size="32"/><br/>
					<span class="exemple">{_T("(Max 32 characters)")}</span>
				</p>
				<p>
					<label class="bline">{_T("Email Body:")}</label>
					<textarea name="text_body" cols="64" rows="15">{$mtxt.tbody}</textarea><br/>
				</p>
			</fieldset>
		</div>
		<div class="button-container">
			<input type="hidden" name="valid" value="1"/>
			<input type="submit" class="submit" value="{_T("Save")}"/>
		</div>
		</form>
