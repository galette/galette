		<H1 class="titre">{_T("Member Profile")}</H1>					
		<BLOCKQUOTE>
			<DIV align="center">
			<TABLE border="0"> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Name:")}</B></TD>
					<TD bgcolor="#EEEEEE">{$data.titres_adh} {$data.nom_adh} {$data.prenom_adh}</TD>
{if $smarty.session.admin_status eq 1}
					<TD colspan="2" rowspan="8" align="center">
{else}
					<TD colspan="2" rowspan="5" align="center">
{/if}
						<IMG src="picture.php?id_adh={$data.id_adh}&rand={$time}" border="1" alt="{_T("Picture")}">
                        		</TD>
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Nickname:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.pseudo_adh}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("birth date:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.ddn_adh}&nbsp;</TD>
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Status:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.libelle_statut}&nbsp;</TD> 
				</TR>
				<TR>
					<TD bgcolor="#DDDDFF"><B>{_T("Profession:")}</B></TD> 
					<TD bgcolor="#EEEEEE"><? echo $prof_adh; ?>&nbsp;</TD> 
				</TR> 
				<TR>
					<TD bgcolor="#DDDDFF"><B>{_T("Be visible in the<br /> members list :")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.bool_display_info}</TD>
				</TR>
{if $smarty.session.admin_status eq 1}
				<TR>
					<TD bgcolor="#DDDDFF"><B>{_T("Account:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.activite_adh}</TD>
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Galette Admin:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.bool_admin_adh}</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Freed of dues:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.bool_exempt_adh}</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("Language:")}<B></TD>
					<TD bgcolor="#EEEEEE"><IMG SRC="" align="left">{$data.pref_lang}</TD>
				</TR> 
{/if}
				<TR>
					<TD colspan="4">&nbsp;</TD> 
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF" valign="top"><B>{_T("Address:")}</B></TD> 
					<TD bgcolor="#EEEEEE" colspan="3">
						{$data.adresse_adh}&nbsp;<BR>
						{$data.adresse2_adh}&nbsp;
					</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Zip Code:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.cp_adh}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("City:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.ville_adh}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Country:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.pays_adh}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("Phone:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.tel_adh}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Mobile phone:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.gsm_adh}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("E-Mail:")}</B></TD> 
					<TD bgcolor="#EEEEEE">
{if $data.email_adh ne ''}					
						<A href="mailto:{$data.email_adh}">{$data.email_adh}</A>
{/if}
						&nbsp;
					</TD>
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Website:")}</B></TD> 
					<TD bgcolor="#EEEEEE">
{if $data.url_adh ne ''}
						<A href="{$data.url_adh}">{$data.url_adh}</A>
{/if}						
						&nbsp;
					</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("ICQ:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.icq_adh}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Jabber:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.jabber_adh}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("MSN:")}</B></TD> 
					<TD bgcolor="#EEEEEE">
{if $data.msn_adh ne ''}
						<A href="mailto:{$data.msn_adh}">{$data.msn_adh}</A>
{/if}
						&nbsp;
					</TD>
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Id GNUpg (GPG):")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.gpgid}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("fingerprint:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.fingerprint}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD colspan="4">&nbsp;</TD> 
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Username:")}&nbsp;</B></TD> 
					<TD bgcolor="#EEEEEE">{$data.login_adh}</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("Password:")}</B>&nbsp;</TD> 
					<TD bgcolor="#EEEEEE">{$data.mdp_adh}</TD> 
				</TR> 
{if $smarty.session.admin_status eq 1}
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Creation date:")}</B>&nbsp;</TD> 
					<TD bgcolor="#EEEEEE" colspan="3">{$data.date_crea_adh}</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF" valign="top"><B>{_T("Other informations (admin):")}</B></TD> 
					<TD bgcolor="#EEEEEE" colspan="3">{$data.info_adh}</TD> 
				</TR>
{/if}
				<TR> 
					<TD bgcolor="#DDDDFF" valign="top"><B>{_T("Other informations:")}</B></TD> 
					<TD bgcolor="#EEEEEE" colspan="3">{$data.info_public_adh}</TD> 
				</TR>

{include file="display_dynamic_fields.tpl"}

				<TR>
					<TD colspan="4" align="center">
						<BR>
						<A href="ajouter_adherent.php?id_adh={$data.id_adh}">{_T("[ Modification ]")}</A>
						&nbsp;&nbsp;&nbsp;
						<A href="gestion_contributions.php?id_adh={$data.id_adh}">{_T("[ Contributions ]")}</A>
{if $smarty.session.admin_status eq 1}
						&nbsp;&nbsp;&nbsp;
						<A href="ajouter_contribution.php?id_adh={$data.id_adh}">{_T("[ Add a contribution ]")}</A>
{/if}
					</TD>
				</TR>
			</TABLE> 
		</DIV>
		<BR> 
		</BLOCKQUOTE> 			
