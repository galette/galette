		<H1 class="titre">{_T("Member Profile")} ({if $adherent.id_adh != ""}{_T("modification")}{else}{_T("creation")}{/if})</H1>
		<FORM action="ajouter_adherent.php" method="post" enctype="multipart/form-data" name="form"> 
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
				<LI>{$warning_detected}<LI>
			</UL>
		</DIV>
{/if}
		<BLOCKQUOTE>
		<DIV align="center">
			<TABLE border="0" id="input-table"> 
				<TR> 
					<TH {if $required.titre_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Title:")}</TH> 
					<TD colspan="3">
						{html_radios name="titre_adh" options=$radio_titres checked=$adherent.titre_adh separator="&nbsp;&nbsp;" disabled=$disabled.titre_adh}
					</TD> 
				</TR> 
				<TR> 
					<TH {if $required.nom_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Name:")}</TH> 
					<TD>
						<INPUT type="text" name="nom_adh" value="{$adherent.nom_adh}" maxlength="20" {$disabled.nom_adh}></TD> 
					<TD colspan="2" rowspan="4" align="center" width="130">
						<IMG src="picture.php?id_adh={$adherent.id_adh}&rand={$time}" border="1" alt="{_T("Picture")}">
					 </TD>
				</TR>
				<TR>
					<TH {if $required.prenom_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("First name:")}</TH>
					<TD><INPUT type="text" name="prenom_adh" value="{$adherent.prenom_adh}" maxlength="20" {$disabled.prenom_adh}></TD>
				</TR>
				<TR>
					<TH {if $required.pseudo_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Nickname:")}</TH>
					<TD><INPUT type="text" name="pseudo_adh" value="{$adherent.pseudo_adh}" maxlength="20" {$disabled.pseudo_adh}></TD>
				</TR>
				<TR>
					<TH {if $required.ddn_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("birth date:")}<br>&nbsp;</TH>
					<TD>
						<INPUT type="text" name="ddn_adh" value="{$adherent.ddn_adh}" maxlength="10" {$disabled.ddn_adh}><BR>
						<DIV class="exemple">{_T("(dd/mm/yyyy format)")}</DIV>
					</TD>
				</TR>
				<TR>
					<TH {if $required.prof_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Profession:")}</TH>
					<TD><input type="text" name="prof_adh" value="{$adherent.prof_adh}" maxlength="150" {$disabled.prof_adh}></TD>
					<TH id="libelle">{_T("Photo:")}</TH>
					<TD>
{if $adherent.has_picture eq 1 }					
						<INPUT type="submit" name="del_photo" value="{_T("Delete the picture")}">
{else}
						<INPUT type="file" name="photo">
{/if}
					</TD>
				</TR>
				<TR>
					<TH {if $required.bool_display_info eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Be visible in the<br /> members list :")}</TH>
					<TD><input type="checkbox" name="bool_display_info" value="1" {if $adherent.bool_display_info eq 1}checked{/if} {$disabled.bool_display_info}></TD>
					<TH {if $required.pref_lang eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Language:")}</TH>
					<TD>
						{literal}
						<script language="javascript" type="text/javascript">
						<!--
							function updatelanguage(){
								document.cookie = "pref_lang="+document.form.pref_lang.value;
								window.location.reload()
							}
						-->
						</script>
						{/literal}
						<SELECT NAME="pref_lang" onChange="updatelanguage()" {$disabled.pref_lang}>
						{foreach key=langue item=langue_t from=$languages}
							<OPTION value="{$langue}" {if $adherent.pref_lang eq $langue}selected{/if} style="padding-left: 30px; background-image: url(lang/{$langue}.gif); background-repeat: no-repeat">{$langue_t|capitalize}</OPTION>
						{/foreach}
						</SELECT>
					</TD>
				</TR>
{if $smarty.session.admin_status eq 1}
				<TR> 
					<TH colspan="4" id="header">&nbsp;</TH> 
				</TR>
				<TR>
					<TH {if $required.activite_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Account:")}</TH> 
					<TD>
						<SELECT name="activite_adh" {$disabled.activite_adh}>
							<OPTION value="1" {if $adherent.activite_adh eq 1}selected{/if}>{_T("Active")}</OPTION>
							<OPTION value="0" {if $adherent.activite_adh eq 0}selected{/if}>{_T("Inactive")}</OPTION>
						</SELECT>
					</TD>
					<TH id="header" colspan="2">&nbsp;</TH>
				</TR>
				<TR> 
					<TH {if $required.id_statut eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Status:")}</TH> 
					<TD>
						<SELECT name="id_statut" {$disabled.id_statut}>
							{html_options options=$statuts selected=$adherent.id_statut}
						</SELECT>
					</TD>
					<TH id="header" colspan="2">&nbsp;</TH>
				</TR>
				<TR>
					<TH {if $required.bool_admin_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Galette Admin:")}</TH> 
					<TD><input type="checkbox" name="bool_admin_adh" value="1" {if $adherent.bool_admin_adh eq 1}checked{/if} {$disabled.bool_admin_adh}></TD> 
					<TH id="header" colspan="2">&nbsp;</TH>
				</TR> 
				<TR> 
					<TH {if $required.bool_exempt_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Freed of dues:")}</TH> 
					<TD><INPUT type="checkbox" name="bool_exempt_adh" value="1" {if $adherent.bool_exempt_adh eq 1}checked{/if} {$disabled.bool_exempt_adh}></TD> 
					<TH id="header" colspan="2">&nbsp;</TH>
				</TR>
{/if}
				<TR> 
					<TH colspan="4" id="header">&nbsp;</TH> 
				</TR>
				<TR> 
					<TH {if $required.adresse_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Address:")}</TH> 
					<TD colspan="3">
						<INPUT type="text" name="adresse_adh" value="{$adherent.adresse_adh}" maxlength="150" size="63" {$disabled.adresse_adh}><BR>
						<INPUT type="text" name="adresse2_adh" value="{$adherent.adresse2_adh}" maxlength="150" size="63" {$disabled.adresse2_adh}>
					</TD> 
				</TR> 
				<TR> 
					<TH {if $required.cp_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Zip Code:")}</TH> 
					<TD><INPUT type="text" name="cp_adh" value="{$adherent.cp_adh}" maxlength="10" {$disabled.cp_adh}></TD> 
					<TH {if $required.ville_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("City:")}</TH> 
					<TD><INPUT type="text" name="ville_adh" value="{$adherent.ville_adh}" maxlength="50" {$disabled.ville_adh}></TD> 
				</TR>
				<TR> 
					<TH {if $required.pays_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Country:")}</TH> 
					<TD><INPUT type="text" name="pays_adh" value="{$adherent.pays_adh}" maxlength="50" {$disabled.pays_adh}></TD> 
					<TH {if $required.tel_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Phone:")}</TH> 
					<TD><INPUT type="text" name="tel_adh" value="{$adherent.tel_adh}" maxlength="20" {$disabled.tel_adh}></TD> 
				</TR> 
				<TR> 
					<TH {if $required.gsm_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Mobile phone:")}</TH> 
					<TD><INPUT type="text" name="gsm_adh" value="{$adherent.gsm_adh}" maxlength="20" {$disabled.gsm_adh}></TD> 
					<TH {if $required.email_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("E-Mail:")}</TH> 
					<TD><INPUT type="text" name="email_adh" value="{$adherent.email_adh}" maxlength="150" size="30" {$disabled.email_adh}></TD> 
				</TR> 
				<TR> 
					<TH {if $required.url_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Website:")}</TH> 
					<TD><INPUT type="text" name="url_adh" value="{$adherent.url_adh}" maxlength="200" size="30" {$disabled.url_adh}></TD> 
					<TH {if $required.icq_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("ICQ:")}</TH> 
					<TD><INPUT type="text" name="icq_adh" value="{$adherent.icq_adh}" maxlength="20" {$disabled.icq_adh}></TD> 
				</TR> 
				<TR> 
					<TH {if $required.jabber_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Jabber:")}</TH> 
					<TD><INPUT type="text" name="jabber_adh" value="{$adherent.jabber_adh}" maxlength="150" size="30" {$disabled.jabber_adh}></TD> 
					<TH {if $required.msn_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("MSN:")}</TH> 
					<TD><INPUT type="text" name="msn_adh" value="{$adherent.msn_adh}" maxlength="150" size="30" {$disabled.msn_adh}></TD> 
				</TR> 
				<TR>
					<TH {if $required.gpgid eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Id GNUpg (GPG):")}</TH>
					<TD><INPUT type="text" name="gpgid" value="{$adherent.gpgid}" maxlength="8" size="8" {$disabled.gpgid}></TD>
					<TH {if $required.fingerprint eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("fingerprint:")}</TH>
					<TD><INPUT type="text" name="fingerprint" value="{$adherent.fingerprint}" maxlength="30" size="30" {$disabled.fingerprint}></TD>
				</TR>
				<TR> 
					<TH colspan="4" id="header">&nbsp;</TH> 
				</TR>
				<TR> 
					<TH {if $required.login_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Username:")}<BR>&nbsp;</TH> 
					<TD>
						<INPUT type="text" name="login_adh" value="{$adherent.login_adh}" maxlength="20" {$disabled.login_adh}><BR>
						<DIV class="exemple">{_T("(at least 4 characters)")}</DIV>
					</TD> 
					<TH {if $required.mdp_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Password:")}<BR>&nbsp;</TH> 
					<TD>
						<INPUT type="text" name="mdp_adh" value="{$adherent.mdp_adh}" maxlength="20" {$disabled.mdp_adh}><BR>
						<DIV class="exemple">{_T("(at least 4 characters)")}</DIV>
					</TD> 
				</TR>
{if $smarty.session.admin_status eq 1}
				<TR> 
					<TH id="libelle">{_T("Send a mail:")}<BR>&nbsp;</TH> 
					<TD colspan="3">
						<INPUT type="checkbox" name="mail_confirm" value="1" {if $smarty.post.mail_confirm != ""}CHECKED{/if}><BR>
						<DIV class="exemple">{_T("(the member will receive his username and password by email, if he has an address.)")}</DIV>
					</TD> 
				</TR> 
				<TR> 
					<TH {if $required.date_crea_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Creation date:")}<BR>&nbsp;</TH> 
					<TD colspan="3">
						<INPUT type="text" name="date_crea_adh" value="{$adherent.date_crea_adh}" maxlength="10" {$disabled.date_crea_adh}><BR>
						<DIV class="exemple">{_T("(dd/mm/yyyy format)")}</DIV>
					</TD> 
				</TR> 
				<TR> 
					<TH {if $required.info_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Other informations (admin):")}</TH> 
					<TD colspan="3">
						<TEXTAREA name="info_adh" cols="61" rows="6" {$disabled.info_adh}>{$adherent.info_adh}</TEXTAREA><BR>
						<DIV class="exemple">{_T("This comment is only displayed for admins.")}</DIV>
					</TD>
				</TR> 
{/if}
				<TR> 
					<TH {if $required.info_public_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Other informations:")}</TH> 
					<TD colspan="3">
						<TEXTAREA name="info_public_adh" cols="61" rows="6" {$disabled.info_public_adh}>{$adherent.info_public_adh}</TEXTAREA>
{if $smarty.session.admin_status eq 1}
						<BR><DIV class="exemple">{_T("This comment is reserved to the member.")}</DIV>
{/if}
					</TD>
				</TR>
{foreach from=$dynamic_fields item=field}
{if $field.perm_cat ne 1 || $smarty.session.admin_status eq 1}
{if $field.type_cat eq 0}
				<TR><TH colspan="4" id="header">&nbsp;</TH></TR>
{else}
{section name="fieldLoop" start=1 loop=$field.size_cat+1}
				<TR>
{if $smarty.section.fieldLoop.index eq 1}
					<TH {if $field.req_cat eq 1}style="color: #FF0000;"{/if} id="libelle" rowspan="{$field.size_cat}">{$field.name_cat}&nbsp;</TH>
{/if}
					<TD colspan="3">
{if $field.type_cat eq 1}
						<TEXTAREA name="info_field_{$field.id_cat}_{$smarty.section.fieldLoop.index}" cols="61" rows="6" {$disabled.dyn[$field.id_cat]}>{$adherent.dyn[$field.id_cat][$smarty.section.fieldLoop.index]}</TEXTAREA>
{elseif $field.type_cat eq 2}
						<INPUT type="text" name="info_field_{$field.id_cat}_{$smarty.section.fieldLoop.index}" value="{$adherent.dyn[$field.id_cat][$smarty.section.fieldLoop.index]}" size="63" {$disabled.dyn[$field.id_cat]}>
{/if}
					</TD>
				</TR>
{/section}
{/if}
{/if}
{/foreach}
				<TR> 
					<TH align="center" colspan="4"><BR><INPUT type="submit" name="valid" value="{_T("Save")}"></TH> 
				</TR>
			</TABLE> 
		</DIV>
		<BR> 
		{_T("NB : The mandatory fields are in")} <FONT style="color: #FF0000">{_T("red")}</FONT>. 
		</BLOCKQUOTE> 
		<INPUT type="hidden" name="id_adh" value="{$adherent.id_adh}"> 
		</FORM> 
