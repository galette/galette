		<H1 class="titre">{_T("Edit field")}</H1>
		<FORM action="editer_champ.php" method="post"> 						
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
		<BLOCKQUOTE>
		<DIV align="center">
			<TABLE border="0" id="input-table"> 
				<TR> 
					<TH id="libelle">{_T("Name:")}</TH> 
					<TD colspan="3">
						<INPUT type="text" name="field_name" value="{$data.name}">
					</TD> 
				</TR>
				<TR>
					<TH id="libelle">{_T("Visibility:")}</TH> 
					<TD>
						<SELECT name="field_perm">
							<OPTION value="{$perm_all}" {if $data.perm == $perm_all}selected="selected"{/if}>{$perm_names[$perm_all]}</OPTION>
							<OPTION value="{$perm_admin}" {if $data.perm == $perm_admin}selected="selected"{/if}>{$perm_names[$perm_admin]}</OPTION>
						</SELECT>
					</TD>
{if !$properties.no_data}
					<TH id="libelle">{_T("Required:")}</TH> 
					<TD class="listing">
						<SELECT name="field_required">
							<OPTION value="0" {if $data.required == 0}selected="selected"{/if}>{_T("No")}</OPTION>
							<OPTION value="1" {if $data.required == 1}selected="selected"{/if}>{_T("Yes")}</OPTION>
						</SELECT>
					</TD>
{/if}
				</TR>
{if $properties.with_width}
				<TR>
					<TH id="libelle">{_T("Width:")}</TH> 
					<TD colspan="3">
						<INPUT type="text" name="field_width" value="{$data.width}" size="3">
					</TD>
				</TR>
{/if}
{if $properties.with_height}
				<TR>
					<TH id="libelle">{_T("Height:")}</TH> 
					<TD colspan="3">
						<INPUT type="text" name="field_height" value="{$data.height}" size="3">
					</TD>
				</TR>
{/if}
{if $properties.with_size}
				<TR>
					<TH id="libelle">{_T("Size:")}</TH> 
					<TD colspan="3">
						<INPUT type="text" name="field_size" value="{$data.size}" size="3">
						<BR><DIV class="exemple">{_T("Maximum number of characters.")}</DIV>
					</TD>
				</TR>
{/if}
{if $properties.multi_valued}
				<TR>
					<TH id="libelle">{_T("Repeat:")}</TH> 
					<TD colspan="3">
						<INPUT type="text" name="field_repeat" value="{$data.repeat}" size="3">
						<BR><DIV class="exemple">{_T("Number of values or zero if infinite.")}</DIV>
					</TD>
				</TR>
{/if}
{if $properties.fixed_values}
				<TR>
					<TH id="libelle">{_T("Values:")}</TH> 
					<TD colspan="3">
						<TEXTAREA name="fixed_values" cols="20" rows="6">{$data.fixed_values}</TEXTAREA>
						<BR><DIV class="exemple">{_T("Choice list (one entry per line).")}</DIV>
					</TD>
				</TR>
{/if}
				<TR> 
					<TH align="center" colspan="2"><BR><input type="submit" name="valid" value="{_T("Save")}"></TH> 
					<TH align="center" colspan="2"><BR><input type="submit" name="cancel" value="{_T("Cancel")}"></TH> 
				</TR> 
			</TABLE> 
		</DIV>
		<BR> 
		</BLOCKQUOTE> 
		<INPUT type="hidden" name="form" value="{$form_name}">
		<INPUT type="hidden" name="id" value="{$data.id}">
		</FORM>
