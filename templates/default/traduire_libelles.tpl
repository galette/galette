		<H1 class="titre">{_T("Translate labels")}</H1>
		<FORM action="traduire_libelles.php" method="post" enctype="multipart/form-data">
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
{if $text_orig != ''}
			<TABLE width="100%" id="input-table"> 
				<TR>
					<TH class="listing left">{_T("Language")}</TH>
					<TH class="listing" width="100%">{_T("Text")}</TH>
				</TR>
				<TR>
					<TD class="listing left">{_T("Original")}</TD> 
					<TD class="listing left">
						<SELECT name="text_orig" onChange="form.submit()">
							{html_options values=$orig output=$orig selected=$text_orig}
						</SELECT>
					</TD>
				</TR>
{section name="lang" loop=$trans}
				<TR>
					<TD class="listing left">{$trans[lang].name}</TD> 
					<TD class="listing left">
						<INPUT type="text" name="text_trans_{$trans[lang].key}" value="{$trans[lang].text}">
					</TD>
				</TR>
{/section}
			</TABLE>
			<BR>
			<INPUT type="submit" name="trans" value="{_T("Save")}">&nbsp;
			<INPUT type="submit" name="update" value="{_T("Update")}">
		</FORM> 
{/if}
