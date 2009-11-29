		<h1 class="titre">{_T string="Translate labels"}</h1>
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
			<table width="100%" id="input-table">
				<tr>
					<th class="listing left">{_T string="Language"}</th>
					<th class="listing" width="100%">{_T string="Text"}</th>
				</tr>
				<tr>
					<td class="listing left">{_T string="Original"}</td>
					<td class="listing left">
						<select name="text_orig" onchange="form.submit()">
							{html_options values=$orig output=$orig selected=$text_orig}
						</select>
					</td>
				</tr>
{section name="lang" loop=$trans}
				<tr>
					<td class="listing left">{$trans[lang].name}</td>
					<td class="listing left">
						<input type="text" name="text_trans_{$trans[lang].key}" value="{$trans[lang].text|escape}"/>
					</td>
				</tr>
{/section}
			</table>
			<br/>
			<input type="submit" class="submit" name="trans" value="{_T string="Save"}"/>&nbsp;
			<input type="submit" class="submit" name="update" value="{_T string="Update"}"/>
		</form>
{/if}
