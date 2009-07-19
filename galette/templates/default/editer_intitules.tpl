		<h1 class="titre">{$form_title}</h1>
		<form action="editer_intitules.php" method="post" enctype="multipart/form-data">
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
{if $table == ''}
		<select name="table" onchange="form.submit()">
			{html_options options=$all_forms}
		</select>&nbsp;
		<input type="hidden" name="list" value="1" />
		<input type="submit" class="submit" name="continue" value="{_T string="Continue"}"/>
{else} {* $table != '' *}
		<table width="100%" id="input-table"> 
			<tr>
				<th class="listing">#</th> 
				<th class="listing left">{_T string="Name"}</th>
{if $table == 'types_cotisation'}
				<th class="listing">{_T string="Extends membership?"}</th>
{elseif $table == 'statuts'}
				<th class="listing">{_T string="Priority"}</th>
{/if}
				<th class="listing">{_T string="Actions"}</th>
			</tr>
{foreach from=$entries item=entry}
			<tr>
				<td class="listing">{$entry.id}</td> 
				<td class="listing left">{$entry.name|escape}</td>
{if $table == 'types_cotisation'}
				<td class="listing">{$entry.extends}</td>
{elseif $table == 'statuts'}
       				<td class="listing">{$entry.priority}</td>
{/if}
				<td class="listing center">
					<a href="editer_intitules.php?table={$table}&amp;id={$entry.id}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" border="0" width="12" height="13"/></a>
					<a onclick="return confirm('{_T string="Do you really want to delete this category?"|escape:"javascript"}')" href="editer_intitules.php?table={$table}&amp;del={$entry.id}">
					<img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" border="0" width="11" height="13"/>
					</a>
				</td>
			</tr>
{/foreach}
			<tr>
				<td width="15" class="listing">&nbsp;</td> 
				<td class="listing left">
					<input size="40" type="text" name="{$namef}"/>
				</td>
				<td width="60" class="listing left">
{if $table == 'types_cotisation'}
					<select name="cotis_extension">
						<option value="0" selected="selected">{_T string="No"}</option>
						<option value="1">{_T string="Yes"}</option>
					</select>
{elseif $table == 'statuts'}
					<input size="4" type="text" name="priorite_statut" value="99" />
{/if}
				</td>
				<td class="listing center">
				    <input type="hidden" name="new" value="1" />
				    <input type="submit" class="submit"	name="valid" value="{_T string="Add"}"/>
				</td>
			</tr>
		</table> 
		<input type="hidden" name="table" value="{$table}"/>
{/if} {* $form_title == '' *}
		</form> 
