{foreach from=$dynamic_fields item=field}
{if $field.field_perm ne 1 || $smarty.session.admin_status eq 1}
{if $field.field_type eq 0}
				<TR><TH colspan="4" id="header">&nbsp;</TH></TR>
{else}
{section name="fieldLoop" start=1 loop=$field.field_repeat+1}
				<TR>
{if $smarty.section.fieldLoop.index eq 1}
					<TH {if $field.field_required eq 1}style="color: #FF0000;"{/if} id="libelle" rowspan="{$field.field_repeat}">{$field.field_name}&nbsp;</TH>
{/if}
					<TD colspan="3">
{if $field.field_type eq 1}
						<TEXTAREA name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}"
						cols="{if $field.field_width > 0}{$field.field_width}{else}61{/if}"
						rows="{if $field.field_height > 0}{$field.field_height}{else}6{/if}"
						{$disabled.dyn[$field.field_id]}>{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}</TEXTAREA>
{elseif $field.field_type eq 2}
						<INPUT type="text" name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}"
						{if $field.field_width > 0}size="{$field.field_width}"{/if}
						{if $field.field_size > 0}maxlength="{$field.field_size}"{/if}
						value="{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}" size="63" {$disabled.dyn[$field.field_id]}>
{elseif $field.field_type eq 3}
						<SELECT name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}">
							{html_options options=$field.choices selected=$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}
						</SELECT>

{/if}
					</TD>
				</TR>
{/section}
{/if}
{/if}
{/foreach}
