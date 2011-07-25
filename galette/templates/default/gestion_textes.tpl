		<h1 id="titre">{_T string="Automatic emails texts edition"}</h1>
		<form action="gestion_textes.php" method="post" enctype="multipart/form-data"> 
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
{if $warning_detected|@count != 0}
		<div id="infobox">
			<ul>
{foreach from=$warning_detected item=warning}
				<li>{$warning}</li>
{/foreach}
			</ul>
		</div>
{/if}

		<div class="bigtable">
			<fieldset class="cssform" id="{$mtxt->tlang}">
				<legend>{$mtxt->tcomment}</legend>
				<p>
					<label for="sel_lang" class="bline">{_T string="Language:"}</label>
					<select name="sel_lang" id="sel_lang">
						{foreach item=langue from=$langlist}
							<option value="{$langue->getID()}" {if $cur_lang eq $langue->getID()}selected="selected"{/if} style="padding-left: 30px; background-image: url({$langue->getFlag()}); background-repeat: no-repeat">{$langue->getName()}</option>
						{/foreach}
					</select>
					<noscript> <span><input type="submit" name="change_lang" value="{_T string="Change"}" /></span></noscript>
				</p>
				<p>
					<label for="sel_ref" class="bline">{_T string="Reference:"}</label>
					<select name="sel_ref" id="sel_ref">
						{foreach item=ref from=$reflist}
							<option value="{$ref.tref}" {if $cur_ref eq $ref.tref}selected="selected"{/if} >{$ref.tcomment}</option>
						{/foreach}
					</select>
					<noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
				</p>
				<p>
					<label for="tsubject" class="bline">{_T string="Email Subject"}</label> 
					<input type="text" name="text_subject" id="tsubject" value="{$mtxt->tsubject}" maxlength="32" size="32"/> <span class="exemple">{_T string="(Max 32 characters)"}</span>
				</p>
				<p>
					<label for="text_body" class="bline">{_T string="Email Body:"}</label>
					<textarea name="text_body" id="text_body" cols="64" rows="15">{$mtxt->tbody}</textarea>
				</p>
			</fieldset>
		</div>
		<div class="button-container">
			<input type="hidden" name="valid" id="valid" value="1"/>
			<input type="submit" value="{_T string="Save"}"/>
		</div>
		</form>
		{literal}
		<script type="text/javascript">
			$(function() {
				$('#sel_ref, #sel_lang').change(function() {
					$(':input[type="submit"]').attr('disabled', 'disabled');
					//Change the input[@id='value'] ; we do not want to validate, but to change lang/ref
					$('#valid').attr('value', (this.id === 'sel_lang') ? 'change_lang' : 'change_text');
					this.form.submit();
				});
			});
		</script>
		{/literal}
