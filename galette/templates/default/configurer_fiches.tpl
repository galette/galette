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
		<INPUT type="submit" value="{_T("Continue")}">
{else} {* $form_title != '' *}
		<TABLE width="100%" id="input-table"> 
			<TR>
				<TH class="listing">#</TH> 
				<TH class="listing left">{_T("Name")}</TH>
				<TH class="listing">{_T("Visibility")}</TH>
				<TH class="listing">{_T("Type")}</TH>
				<TH class="listing">{_T("Repeat")}</TH>
				<TH class="listing">{_T("Actions")}</TH>
			</TR>
{foreach from=$dyn_fields item=field}
			<TR>
				<TD class="listing">{$field.index}</TD> 
				<TD class="listing left">{$field.name}</TD>
				<TD class="listing left">{$field.perm}</TD>
				<TD class="listing left">{$field.type}</TD>
				<TD class="listing">{$field.repeat}</TD>
				<TD class="listing center">
					<A onClick="return confirm('{_T("Do you really want to delete this category ?\n All associated data will be deleted as well.")|escape:"javascript"}')" href="configurer_fiches.php?sup={$field.id}">
					<IMG src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" border="0" width="11" height="13">
					</A>
{if $field.index eq 1}
					<IMG src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="11" height="13">
{else}
					<A href="configurer_fiches.php?up={$field.id}">
					<IMG src="{$template_subdir}images/icon-up.png" alt="{_T("[top]")}" border="0" width="9" height="8">
					</A>
{/if}
{if $field.index eq $dyn_fields|@count}
					<IMG src="{$template_subdir}images/icon-empty.png" alt="" border="0" width="11" height="13">
{else}
					<A href="configurer_fiches.php?down={$field.id}">
					<IMG src="{$template_subdir}images/icon-down.png" alt="{_T("[bottom]")}" border="0" width="9" height="8">
					</A>
{/if}
				</TD>
			</TR>
{/foreach}
			<TR>
				<TD width="15" class="listing">&nbsp;</TD> 
				<TD class="listing left">
					<INPUT size="40" type="text" name="field_name">
				</TD>
				<TD width="60" class="listing left">
					<SELECT name="field_perm">
						<OPTION value="{$perm_all}">{_T("all")}</OPTION>
						<OPTION value="{$perm_admin}">{_T("admin")}</OPTION>
					</SELECT>
				</TD>
				<TD width="60" class="listing left">
					<SELECT name="field_type">
						<OPTION value="{$field_type_separator}">{_T("separator")}</OPTION>
						<OPTION value="{$field_type_text}">{_T("free text")}</OPTION>
						<OPTION value="{$field_type_line}">{_T("single line")}</OPTION>
					</SELECT>
				</TD>
				<TD class="listing">
					<INPUT size="2" maxlength="2" type="text" value="1" name="field_repeat">
				</TD>
				<TD class="listing center"><INPUT type="submit" name="valid" value="{_T("Add")}"></TD>
			</TR>
		</TABLE> 
		<INPUT type="hidden" name="form" value="{$form_name}">
{/if} {* $form_title == '' *}
		</FORM> 
