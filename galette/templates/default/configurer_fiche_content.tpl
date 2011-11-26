		<table id="input-table">
			<thead>
				<tr>
					<th class="listing" class="id_row">#</th>
					<th class="listing">{_T string="Name"}</th>
					<th class="listing date_row">{_T string="Visibility"}</th>
					<th class="listing date_row">{_T string="Type"}</th>
					<th class="listing date_row">{_T string="Required"}</th>
					<th class="listing date_row">{_T string="Position"}</th>
					<th class="listing">{_T string="Actions"}</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td class="listing">&nbsp;</td>
					<td class="listing left">
						<input size="40" type="text" name="field_name"/>
					</td>
					<td class="listing left">
						<select name="field_perm">
							{html_options options=$perm_names selected="0"}
						</select>
					</td>
					<td class="listing left">
						<select name="field_type">
							{html_options options=$field_type_names selected="0"}
						</select>
					</td>
					<td class="listing">
						<select name="field_required">
							<option value="0">{_T string="No"}</option>
							<option value="1">{_T string="Yes"}</option>
						</select>
					</td>
					<td class="listing left">
						<select name="field_pos">
							{html_options options=$field_positions selected="0"}
						</select>
					</td>
					<td class="listing center"><input type="submit" name="valid" id="btnadd" value="{_T string="Add"}"/></td>
				</tr>
			</tfoot>
			<tbody>
{foreach from=$dyn_fields item=field}
				<tr>
					<td class="listing">{$field.index}</td>
					<td class="listing left">{$field.name|escape}</td>
					<td class="listing left">{$field.perm}</td>
					<td class="listing left">{$field.type_name}</td>
					<td class="listing">
{if $field.type neq constant('DynamicFields::SEPARATOR')}
	{if $field.required}{_T string="Yes"}{else}{_T string="No"}{/if}
{/if}
					</td>
					<td class="listing left">{$field.pos}</td>
					<td class="listing center actions_row">
{if $field.no_data}
						<img src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="16" height="16"/>
						<img src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="16" height="16"/>
{else}
						<a href="editer_champ.php?form={$form_name}&amp;id={$field.id}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="Edit '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Edit '%s' field" pattern="/%s/" replace=$field.name}" width="16" height="16"/></a>
						<a href="traduire_libelles.php?text_orig={$field.name|escape}"><img src="{$template_subdir}images/icon-i18n.png" alt="{_T string="Edit '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Translate '%s' field" pattern="/%s/" replace=$field.name}" width="16" height="16"/></a>
{/if}
						<a onclick="return confirm('{_T string="Do you really want to delete this field ?\n All associated data will be deleted as well."|escape:"javascript"}')" href="configurer_fiches.php?form={$form_name}&amp;del={$field.id}">
							<img src="{$template_subdir}images/icon-trash.png" alt="{_T string="Delete '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Delete '%s' field" pattern="/%s/" replace=$field.name}" width="16" height="16"/>
						</a>
{if $field.index eq 1}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="9" height="8"/>
{else}
						<a href="configurer_fiches.php?form={$form_name}&amp;up={$field.id}">
							<img src="{$template_subdir}images/icon-up.png" alt="{_T string="Send up '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Send up '%s' field" pattern="/%s/" replace=$field.name}" width="9" height="8"/>
						</a>
{/if}
{if $field.index eq $dyn_fields|@count}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="9" height="8"/>
{else}
						<a href="configurer_fiches.php?form={$form_name}&amp;down={$field.id}">
							<img src="{$template_subdir}images/icon-down.png" alt="{_T string="Send down '%s' field" pattern="/%s/" replace=$field.name}" title="{_T string="Send down '%s' field" pattern="/%s/" replace=$field.name}" width="9" height="8"/>
						</a>
{/if}
					</td>
				</tr>
{/foreach}
			</tbody>
		</table>
		<input type="hidden" name="form" value="{$form_name}"/>
