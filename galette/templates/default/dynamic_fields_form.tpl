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
						<TEXTAREA name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" cols="61" rows="6" {$disabled.dyn[$field.field_id]}>{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}</TEXTAREA>
{elseif $field.field_type eq 2}
						<INPUT type="text" name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" value="{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}" size="63" {$disabled.dyn[$field.field_id]}>
{/if}
					</TD>
				</TR>
{/section}
{/if}
{/if}
{/foreach}
