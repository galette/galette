		<h1 id="titre">{_T string="Translate labels"}</h1>
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
{if $text_orig != ''}
		<form action="traduire_libelles.php" method="post" enctype="multipart/form-data">
			<div class="bigtable">
				<p class="right">
					<label for="text_orig">{_T string="Choose label to translate"}</label>
					<select name="text_orig" id="text_orig">
						{html_options values=$orig output=$orig selected=$text_orig}
					</select>
					<noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
				</p>
				<fieldset class="cssform">
					<legend>{_T string="Translation of '%s' label"|regex_replace:"/%s/":$text_orig}</legend>
{section name="lang" loop=$trans}
					<p>
						<label for="text_trans_{$trans[lang].key}" class="bline">{$trans[lang].name}</label>
						<input type="text" name="text_trans_{$trans[lang].key}" id="text_trans_{$trans[lang].key}" value="{$trans[lang].text|escape}"/>
					</p>
{/section}
				</fieldset>
			</div>
			<div class="button-container">
				<input type="submit" class="submit" name="trans" value="{_T string="Save"}"/>
			</div>
		</form>
{literal}
		<script type="text/javascript">
			<![CDATA[
				$('#text_orig').change(function() {
					this.form.submit();
				});
			]]>
		</script>
{/literal}
{else}
		<p>{_T string="No fields to translate."}</p>
{/if}
