{foreach from=$dynamic_fields item=field}
{if $field.field_perm ne 1 || $smarty.session.admin_status eq 1}
{if $field.field_type eq 0}
	{if $is_form eq false}
				<TR><TD colspan="4">&nbsp;</TD></TR>
	{else}
				<TR><TH colspan="4" id="header">&nbsp;</TH></TR>
	{/if}
{else}
{section name="fieldLoop" start=1 loop=$field.field_repeat+1}
{cycle name="col" values="left,right" assign="col" print=false}
{if $col == "left"}
				<TR>
{/if}
{if $smarty.section.fieldLoop.index eq 1}
{if $field.field_pos == 0 || $field.field_repeat != 1}
	{if $col == "right"}
		{if $is_form eq false}
			<TD bgcolor="#DDDDFF" valign="top">&nbsp;</TD><TD>&nbsp;</TD></TR><TR>
		{else}
			<TH id="libelle">&nbsp;</TH><TD>&nbsp;</TD></TR><TR>
		{/if}
	{else}
		{cycle name="col" advance="false" print=false}
	{/if}
{elseif $field.field_pos == 1 && $col == "right"}
		{if $is_form eq false}
			<TD bgcolor="#DDDDFF" valign="top">&nbsp;</TD><TD>&nbsp;</TD></TR><TR>
		{else}
			<TH id="libelle">&nbsp;</TH><TD>&nbsp;</TD></TR><TR>
		{/if}
	{cycle name="col" advance="false" print=false}
{elseif $field.field_pos == 2 && $col == "left"}
		{if $is_form eq false}
			<TD bgcolor="#DDDDFF" valign="top">&nbsp;</TD><TD>&nbsp;</TD>
		{else}
			<TH id="libelle">&nbsp;</TH><TD>&nbsp;</TD>
		{/if}
	{cycle name="col" advance="false" print=false}
{/if}
		{if $is_form eq false}
					<TD bgcolor="#DDDDFF" valign="top" rowspan="{$field.field_repeat}"><B>{$field.field_name}</B>&nbsp;</TD>
		{else}
					<TH {if $field.field_required eq 1}style="color: #FF0000;"{/if} id="libelle" rowspan="{$field.field_repeat}">{$field.field_name}&nbsp;</TH>
		{/if}
{/if}
{if $field.field_pos == 0 || $field.field_repeat != 1}
		{if $is_form eq false}
					<TD bgcolor="#EEEEEE" colspan="3">
		{else}
					<TD colspan="3">
		{/if}
{else}
		{if $is_form eq false}
					<TD bgcolor="#EEEEEE">
		{else}
					<TD>
		{/if}
{/if}
{if $is_form eq false}
						{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]|nl2br|default:"&nbsp;"}
{else}
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
{/if}
					</TD>
{if $field.field_pos != 1 || $field.field_repeat != 1}
				</TR>
{/if}
{/section}
{/if}
{/if}
{/foreach}
