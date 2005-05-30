		<H1 class="titre">{_T("Settings")}</H1>
		<FORM action="preferences.php" method="post" enctype="multipart/form-data"> 
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
{if $warning_detected|@count != 0}
		<DIV id="warningbox">
			<H1>{_T("- WARNING -")}</H1>
			<UL>
{foreach from=$warning_detected item=warning}
				<LI>{$warning}<LI>
{/foreach}
			</UL>
		</DIV>
{/if}
		<BLOCKQUOTE>
		<DIV align="center">
			<TABLE border="0" id="input-table">
				<TR>
					<TH colspan="2" id="header">{_T("General information:")}</TH>
				</TR> 
				<TR> 
					<TH {if $required.pref_nom eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Name (corporate name) of the association:")}</TH>
					<TD><INPUT type="text" name="pref_nom" value="{$pref.pref_nom}" maxlength="190"></TD>
				</TR>
				<TR>
					<TH id="libelle">{_T("Logo:")}</TH> 
					<TD>
{if $pref.has_logo eq 1}      
						(TODO: display the logo)<BR>
						<INPUT type="submit" name="del_photo" value="{_T("Delete the logo")}">
{else}
						<INPUT type="file" name="logo">
{/if}
					</TD>
				</TR>
				<TR>
					<TH {if $required.pref_adresse eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Address:")}</TH> 
					<TD><INPUT type="text" name="pref_adresse" value="{$pref.pref_adresse}" maxlength="190" size="42"></TD> 
				</TR>         
				<TR>
					<TH id="libelle">&nbsp;</TH> 
					<TD><INPUT type="text" name="pref_adresse2" value="{$pref.pref_adresse2}" maxlength="190" size="42"></TD> 
				</TR>         
				<TR>
					<TH {if $required.pref_cp eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Zip Code:")}</TH>
					<TD><input type="text" name="pref_cp" value="{$pref.pref_cp}" maxlength="10"></TD>
				</TR>         
				<TR>
					<TH {if $required.pref_ville eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("City:")}</TH> 
					<TD><INPUT type="text" name="pref_ville" value="{$pref.pref_ville}" maxlength="100"></TD>
				</TR>         
				<TR>
					<TH {if $required.pref_pays eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Country:")}</TH> 
					<TD><input type="text" name="pref_pays" value="{$pref.pref_pays}" maxlength="50"></TD>
				</TR>         
				<TR>
					<TH colspan="2" id="header"><BR>{_T("Galette's parameters:")}</TH>
				</TR> 
				<TR>
					<TH {if $required.pref_lang eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Default language:")}</TH>
					<TD>
						<SELECT name="pref_lang">
{foreach key=langue item=langue_t from=$languages}
							<OPTION value="{$langue}" {if $pref.pref_lang eq $langue}selected{/if} style="padding-left: 30px; background-image: url(lang/{$langue}.gif); background-repeat: no-repeat">{$langue_t|capitalize}</OPTION>
{/foreach}
						</SELECT>
					</TD> 
				</TR>         
				<TR>
					<TH {if $required.pref_numrows eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Lines / Page:")}</TH>
					<TD>
						<SELECT name="pref_numrows">
							{html_options options=$pref_numrows_options selected=$pref.pref_numrows}
						</SELECT>
					</TD>
				</TR>         
				<TR>
					<TH {if $required.pref_log eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Logging level:")}</TH>
					<TD>
						<SELECT name="pref_log">
							<OPTION value="0" {if $pref.pref_log eq 0}SELECTED{/if}>{_T("Disabled")}</OPTION>
							<OPTION value="1" {if $pref.pref_log eq 1}SELECTED{/if}>{_T("Normal")}</OPTION>
							<OPTION value="2" {if $pref.pref_log eq 2}SELECTED{/if}>{_T("Detailed")}</OPTION>
						</SELECT>
					</TD>
				</TR>         
				<TR>
					<TH {if $required.pref_membership_ext eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Default membership extension:")}</TH>
					<TD>
						<INPUT type="text" name="pref_membership_ext" value="{$pref.pref_membership_ext}" maxlength="2">
						<SPAN class="exemple">{_T("(Months)")}</SPAN>
					</TD>
				</TR>         
				<TR>
					<TH {if $required.pref_beg_membership eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Beginning of membership:")}</TH>
					<TD>
						<INPUT type="text" name="pref_beg_membership" value="{$pref.pref_beg_membership}" maxlength="5">
						<SPAN class="exemple">{_T("(dd/mm)")}</SPAN>
					</TD>
				</TR>         
				<TR>
					<TH colspan="2" id="header"><BR>{_T("Mail settings:")}</TH>
				</TR> 
				<TR>
					<TH {if $required.pref_email_nom eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Sender name:")}</TH> 
					<TD><INPUT type="text" name="pref_email_nom" value="{$pref.pref_email_nom}" maxlength="50"></TD>
				</TR>         
				<TR>
					<TH {if $required.pref_email eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Sender Email:")}</TH> 
					<TD><INPUT type="text" name="pref_email" value="{$pref.pref_email}" maxlength="100" size="30"></TD>
				</TR>
				<TR>
					<TH {if $required.pref_mail_method eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Emailing method:")}</TH>
					<TD>
						<INPUT type="radio" name="pref_mail_method" value="0" {if $pref.pref_mail_method eq 0}CHECKED{/if}> {_T("Emailing disabled")}<BR>
						<INPUT type="radio" name="pref_mail_method" value="1" {if $pref.pref_mail_method eq 1}CHECKED{/if}> {_T("PHP mail() function")}<BR>
						<INPUT type="radio" name="pref_mail_method" value="2" {if $pref.pref_mail_method eq 2}CHECKED{/if}> {_T("Using a SMTP server (slower)")}
					</TD
				</TR>
				<TR>
					<TH {if $required.pref_mail_smtp eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("SMTP server:")}</TH>
					<TD><input type="text" name="pref_mail_smtp" value="{$pref.pref_mail_smtp}" maxlength="100" size="30"></TD>
				</TR> 
				<TR>
					<TH colspan="2" id="header"><BR>{_T("Label generation parameters:")}</TH>
				</TR> 
				<TR>
					<TH {if $required.pref_etiq_marges eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Margins:")}</TH> 
					<TD>
						<INPUT type="text" name="pref_etiq_marges" value="{$pref.pref_etiq_marges}" maxlength="4"> mm 
						<SPAN class="exemple">{_T("(Integer)")}</SPAN>
					</TD> 
				</TR>         
				<TR>
					<TH {if $required.pref_etiq_hspace eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Horizontal spacing:")}</TH>
					<TD>
						<INPUT type="text" name="pref_etiq_hspace" value="{$pref.pref_etiq_hspace}" maxlength="4"> mm
						<SPAN class="exemple">{_T("(Integer)")}</SPAN>
					</TD> 
				</TR>
				<TR>
					<TH {if $required.pref_etiq_vspace eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Vertical spacing:")}</TH>
					<TD>
						<INPUT type="text" name="pref_etiq_vspace" value="{$pref.pref_etiq_vspace}" maxlength="4"> mm
						<SPAN class="exemple">{_T("(Integer)")}</SPAN>
					</TD> 
				</TR>
				<TR>
					<TH {if $required.pref_etiq_hsize eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Label width:")}</TH>
					<TD>
						<INPUT type="text" name="pref_etiq_hsize" value="{$pref.pref_etiq_hsize}" maxlength="4"> mm
						<SPAN class="exemple">{_T("(Integer)")}</SPAN>
					</TD> 
				</TR>
				<TR>
					<TH {if $required.pref_etiq_vsize eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Label height:")}</TH>
					<TD>
						<INPUT type="text" name="pref_etiq_vsize" value="{$pref.pref_etiq_vsize}" maxlength="4"> mm
						<SPAN class="exemple">{_T("(Integer)")}</SPAN>
					</TD> 
				</TR>
    				<TR>
   					<TH {if $required.pref_etiq_cols eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Number of label columns:")}</TH> 
				<TD>
						<INPUT type="text" name="pref_etiq_cols" value="{$pref.pref_etiq_cols}" maxlength="4">
						<SPAN class="exemple">{_T("(Integer)")}</SPAN>
					</TD> 
   				</TR>         
				<TR>
   					<TH {if $required.pref_etiq_rows eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Number of label lines:")}</TH> 
					<TD>
						<INPUT type="text" name="pref_etiq_rows" value="{$pref.pref_etiq_rows}" maxlength="4">
						<SPAN class="exemple">{_T("(Integer)")}</SPAN>
					</TD> 
   				</TR>         
    				<TR>
   					<TH {if $required.pref_etiq_corps eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Font size:")}</TH> 
					<TD>
						<INPUT type="text" name="pref_etiq_corps" value="{$pref.pref_etiq_corps}" maxlength="4">
						<SPAN class="exemple">{_T("(Integer)")}</SPAN>
					</TD> 
   				</TR>         
   				<TR>
   					<TH colspan="2" id="header"><BR>{_T("Admin account (independant of members):")}</TH>
   				</TR> 
   				<TR>
   					<TH {if $required.pref_admin_login eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Username:")}</TH> 
					<TD>
						<INPUT type="text" name="pref_admin_login" value="{$pref.pref_admin_login}" maxlength="20">
					</TD>
   				</TR>
				<TR> 
					<TH {if $required.pref_admin_pass eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Password:")}</TH> 
					<TD>
						<INPUT type="text" name="pref_admin_pass" value="{$pref.pref_admin_pass}" maxlength="20">
					</TD>
				</TR>
				<TR> 
					<TH align="center" colspan="2"><BR><INPUT type="submit" value="{_T("Save")}"></TH> 
				</TR> 
			</TABLE> 
		</DIV>
		<BR> 
		{_T("NB : The mandatory fields are in")} <font style="color: #FF0000">{_T("red")}</font>. 
		</BLOCKQUOTE>
		<INPUT TYPE="hidden" name="valid" value="1">
		</FORM> 
