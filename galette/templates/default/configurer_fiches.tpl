		<H1 class="titre">{_T("Profile configuration")}{if $form_title != ''} ({$form_title}){/if}</H1>
		<FORM action="configurer_fiches.php" method="post" enctype="multipart/form-data">
{if $error_detected|@count != 0}
		<DIV id="errorbox">
			<H1>{_T("- ERROR -")}</H1>
			<UL>
{foreach from=$error_detected item=error}
				<LI>{$error}<LI>
{/foreach}
			</UL>
		</DIV>
{/if}
{if $form_title == ''}
		<SELECT name="form" onChange="form.submit()">
			{html_options options=$all_forms selected=$form_name}
		</SELECT>&nbsp;
		<INPUT type="submit" name="continue" value="{_T("Continue")}"/>
{else} {* $form_title != '' *}
		<TABLE width="100%" id="input-table"> 
			<TR>
				<TH class="listing">#</TH> 
				<TH class="listing left">{_T("Name")}</TH>
				<TH class="listing">{_T("Visibility")}</TH>
				<TH class="listing">{_T("Type")}</TH>
				<TH class="listing">{_T("Required")}</TH>
				<TH class="listing">{_T("Position")}</TH>
				<TH class="listing">{_T("Actions")}</TH>
			</TR>
{foreach from=$dyn_fields item=field}
			<TR>
				<TD class="listing">{$field.index}</TD> 
				<TD class="listing left">{$field.name}</TD>
				<TD class="listing left">{$field.perm}</TD>
				<TD class="listing left">{$field.type}</TD>
				<TD class="listing">
					{if $field.type != $field_type_separator}
						{if $field.required}{_T("Yes")}{else}{_T("No")}{/if}
					{/if}
				</TD>
				<TD class="listing left">{$field.pos}</TD>
				<TD class="listing center">
{if $field.no_data}
					<IMG src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="12" height="13"/>
{else}
					<A href="editer_champ.php?form={$form_name}&amp;id={$field.id}"><IMG src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" border="0" width="12" height="13"/></A>
{/if}
					<A onClick="return confirm('{_T("Do you really want to delete this category ?\n All associated data will be deleted as well.")|escape:"javascript"}')" href="configurer_fiches.php?form={$form_name}&amp;del={$field.id}">
					<IMG src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" border="0" width="11" height="13"/>
					</A>
{if $field.index eq 1}
					<IMG src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="9" height="13"/>
{else}
					<A href="configurer_fiches.php?form={$form_name}&up={$field.id}">
					<IMG src="{$template_subdir}images/icon-up.png" alt="{_T("[up]")}" border="0" width="9" height="8"/>
					</A>
{/if}
{if $field.index eq $dyn_fields|@count}
					<IMG src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="9" height="13"/>
{else}
					<A href="configurer_fiches.php?form={$form_name}&down={$field.id}">
					<IMG src="{$template_subdir}images/icon-down.png" alt="{_T("[down]")}" border="0" width="9" height="8"/>
					</A>
{/if}
				</TD>
			</TR>
{/foreach}
			<TR>
				<TD width="15" class="listing">&nbsp;</TD> 
				<TD class="listing left">
					<INPUT size="40" type="text" name="field_name"/>
				</TD>
				<TD width="60" class="listing left">
					<SELECT name="field_perm">
						{html_options options=$perm_names selected="0"}
					</SELECT>
				</TD>
				<TD width="60" class="listing left">
					<SELECT name="field_type">
						{html_options options=$field_type_names selected="0"}
					</SELECT>
				</TD>
				<TD class="listing">
					<SELECT name="field_required">
						<OPTION value="0">{_T("No")}</OPTION>
						<OPTION value="1">{_T("Yes")}</OPTION>
					</SELECT>
				</TD>
				<TD width="60" class="listing left">
					<SELECT name="field_pos">
						{html_options options=$field_positions selected="0"}
					</SELECT>
				</TD>
				<TD class="listing center"><INPUT type="submit" name="valid" value="{_T("Add")}"/></TD>
			</TR>
		</TABLE> 
		<INPUT type="hidden" name="form" value="{$form_name}"/>
{/if} {* $form_title == '' *}
		</FORM> 
