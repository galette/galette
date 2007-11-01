		<h1 id="titre">{_T("Profile configuration")}{if $form_title != ''} ({$form_title}){/if}</h1>
		<form action="configurer_fiches.php" method="post" enctype="multipart/form-data">
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
		<ul id="tabs">
{foreach from=$all_forms key=key item=form}
			<li><a href="?form={$key}"{if $form_name eq $key} class="current_tab"{/if}>{$form}</a></li>
{/foreach}
		</ul>
		<div class="tabbed">
		<table id="input-table">
			<tr>
				<th class="listing">#</th> 
				<th class="listing left">{_T("Name")}</th>
				<th class="listing">{_T("Visibility")}</th>
				<th class="listing">{_T("Type")}</th>
				<th class="listing">{_T("Required")}</th>
				<th class="listing">{_T("Position")}</th>
				<th class="listing">{_T("Actions")}</th>
			</tr>
{foreach from=$dyn_fields item=field}
			<tr>
				<td class="listing">{$field.index}</td> 
				<td class="listing left">{$field.name|escape}</td>
				<td class="listing left">{$field.perm}</td>
				<td class="listing left">{$field.type}</td>
				<td class="listing">
					{if $field.type != $field_type_separator}
						{if $field.required}{_T("Yes")}{else}{_T("No")}{/if}
					{/if}
				</td>
				<td class="listing left">{$field.pos}</td>
				<td class="listing center">
{if $field.no_data}
					<img src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="16" height="16"/>
{else}
					<a href="editer_champ.php?form={$form_name}&amp;id={$field.id}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" width="16" height="16"/></a>
{/if}
					<a onclick="return confirm('{_T("Do you really want to delete this field ?\\n All associated data will be deleted as well.")|escape:"javascript"}')" href="configurer_fiches.php?form={$form_name}&amp;del={$field.id}">
					<img src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" width="16" height="16"/>
					</a>
{if $field.index eq 1}
					<img src="{$template_subdir}images/icon-empty.png" alt="" width="9" height="8"/>
{else}
					<a href="configurer_fiches.php?form={$form_name}&amp;up={$field.id}">
					<img src="{$template_subdir}images/icon-up.png" alt="{_T("[up]")}" width="9" height="8"/>
					</a>
{/if}
{if $field.index eq $dyn_fields|@count}
					<img src="{$template_subdir}images/icon-empty.png" alt="" width="9" height="8"/>
{else}
					<a href="configurer_fiches.php?form={$form_name}&amp;down={$field.id}">
					<img src="{$template_subdir}images/icon-down.png" alt="{_T("[down]")}" width="9" height="8"/>
					</a>
{/if}
				</td>
			</tr>
{/foreach}
			<tr>
				<td width="15" class="listing">&nbsp;</td> 
				<td class="listing left">
					<input size="40" type="text" name="field_name"/>
				</td>
				<td width="60" class="listing left">
					<select name="field_perm">
						{html_options options=$perm_names selected="0"}
					</select>
				</td>
				<td width="60" class="listing left">
					<select name="field_type">
						{html_options options=$field_type_names selected="0"}
					</select>
				</td>
				<td class="listing">
					<select name="field_required">
						<option value="0">{_T("No")}</option>
						<option value="1">{_T("Yes")}</option>
					</select>
				</td>
				<td width="60" class="listing left">
					<select name="field_pos">
						{html_options options=$field_positions selected="0"}
					</select>
				</td>
				<td class="listing center"><input type="submit" class="submit" name="valid" value="{_T("Add")}"/></td>
			</tr>
		</table>
		</div>
		<input type="hidden" name="form" value="{$form_name}"/>
		</form>
		{literal}
		<script type="text/javascript">
			<![CDATA[
			$('#tabs li').corner('top');
			$('.tabbed').corner('bottom');
			]]>
		</script>
		{/literal}