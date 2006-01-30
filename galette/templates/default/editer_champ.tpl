		<h1 class="titre">{_T("Edit field")}</h1>
		<form action="editer_champ.php" method="post"> 						
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
					<th id="libelle">{_T("Name:")}</th> 
					<td colspan="3">
						<input type="text" name="field_name" value="{$data.name}">
					</td> 
				</tr>
				<tr>
					<th id="libelle">{_T("Visibility:")}</th> 
					<td>
						<select name="field_perm">
							<option value="{$perm_all}" {if $data.perm == $perm_all}selected="selected"{/if}>{$perm_names[$perm_all]}</option>
							<option value="{$perm_admin}" {if $data.perm == $perm_admin}selected="selected"{/if}>{$perm_names[$perm_admin]}</option>
						</select>
					</td>
					<th id="libelle">{_T("Position:")}</th> 
					<td>
						<select name="field_pos">
							{html_options options=$field_positions selected=$data.pos}
						</select>
					</td>
				</tr>
{if !$properties.no_data}
				<tr>
					<th id="libelle">{_T("Required:")}</th> 
					<td class="listing" colspan="3">
						<select name="field_required">
							<option value="0" {if $data.required == 0}selected="selected"{/if}>{_T("No")}</option>
							<option value="1" {if $data.required == 1}selected="selected"{/if}>{_T("Yes")}</option>
						</select>
					</td>
				</tr>
{/if}
{if $properties.with_width}
				<tr>
					<th id="libelle">{_T("Width:")}</th> 
					<td colspan="3">
						<input type="text" name="field_width" value="{$data.width}" size="3">
					</td>
				</tr>
{/if}
{if $properties.with_height}
				<tr>
					<th id="libelle">{_T("Height:")}</th> 
					<td colspan="3">
						<input type="text" name="field_height" value="{$data.height}" size="3">
					</td>
				</tr>
{/if}
{if $properties.with_size}
				<tr>
					<th id="libelle">{_T("Size:")}</th> 
					<td colspan="3">
						<input type="text" name="field_size" value="{$data.size}" size="3">
						<br/><div class="exemple">{_T("Maximum number of characters.")}</div>
					</td>
				</tr>
{/if}
{if $properties.multi_valued}
				<tr>
					<th id="libelle">{_T("Repeat:")}</th> 
					<td colspan="3">
						<input type="text" name="field_repeat" value="{$data.repeat}" size="3">
						<br/><div class="exemple">{_T("Number of values or zero if infinite.")}</div>
					</td>
				</tr>
{/if}
{if $properties.fixed_values}
				<tr>
					<th id="libelle">{_T("Values:")}</th> 
					<td colspan="3">
						<textarea name="fixed_values" cols="20" rows="6">{$data.fixed_values}</textarea>
						<br/><div class="exemple">{_T("Choice list (one entry per line).")}</div>
					</td>
				</tr>
{/if}
				<tr> 
					<th align="center" colspan="2"><br/><input type="submit" name="valid" value="{_T("Save")}"></th> 
					<th align="center" colspan="2"><br/><input type="submit" name="cancel" value="{_T("Cancel")}"></th> 
				</tr> 
			</table> 
		</div>
		<br/> 
		</blockquote> 
		<input type="hidden" name="form" value="{$form_name}">
		<input type="hidden" name="id" value="{$data.id}">
		</form>
