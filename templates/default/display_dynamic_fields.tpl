{foreach from=$dynamic_fields item=field}
{if $field.field_perm ne 1 || $smarty.session.admin_status eq 1}
{if $field.field_type eq 0}
				<TR><TD colspan="4">&nbsp;</TD></TR>
{else}
{section name="fieldLoop" start=1 loop=$field.field_repeat+1}
				<TR>
{if $smarty.section.fieldLoop.index eq 1}
					<TD bgcolor="#DDDDFF" valign="top" rowspan="{$field.field_repeat}"><B>{$field.field_name}</B>&nbsp;</TD>
{/if}
					<TD bgcolor="#EEEEEE" colspan="3">
						{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]|nl2br|default:"&nbsp;"}
                                        </TD>
                                </TR>
{/section}
{/if}
{/if}
{/foreach}
