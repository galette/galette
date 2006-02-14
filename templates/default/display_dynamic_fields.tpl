{foreach from=$dynamic_fields item=field}
{if $field.field_perm ne 1 || $smarty.session.admin_status eq 1}
	{if $field.field_type eq 0}
			<tr>
				<th class="separator">{$field.field_name}&nbsp;</th>
			</tr>
	{else}
			<tr>
		{if $is_form eq false}
				<th>{$field.field_name}&nbsp;</th>
		{else}
				<th {if $field.field_required eq 1}style="color: #FF0000;"{/if} class="libelle">{$field.field_name}&nbsp;</th>
		{/if}
				<td>
		{section name="fieldLoop" start=1 loop=$field.field_repeat+1}
		{if $is_form eq false}
					{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]|nl2br|default:"&nbsp;"}
		{else}
			{if $field.field_type eq 1}
					<textarea name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}"
					cols="{if $field.field_width > 0}{$field.field_width}{else}61{/if}"
					rows="{if $field.field_height > 0}{$field.field_height}{else}6{/if}"
					{$disabled.dyn[$field.field_id]}>{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}</textarea>
			{elseif $field.field_type eq 2}
					<input type="text" name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}"
					{if $field.field_width > 0}size="{$field.field_width}"{/if}
					{if $field.field_size > 0}maxlength="{$field.field_size}"{/if}
					value="{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}" size="63" {$disabled.dyn[$field.field_id]}/>
			{elseif $field.field_type eq 3}
					<select name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}">
						{html_options options=$field.choices selected=$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}
					</select>
			{/if}
		{/if}
		<br/>
		{/section}
				</td>
		{if $field.field_pos != 1 || $field.field_repeat != 1}
			</tr>
		{/if}
	{/if}
{/if}
{/foreach}
