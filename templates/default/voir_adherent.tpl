		<H1 class="titre">{_T("Member Profile")}</H1>					
		<BLOCKQUOTE>
			<DIV align="center">
			<TABLE border="0"> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Name:")}</B></TD>
					<TD bgcolor="#EEEEEE">{$adherent.titres_adh} {$adherent.nom_adh} {$adherent.prenom_adh}</TD>
{if $smarty.session.admin_status eq 1}
					<TD colspan="2" rowspan="8" align="center">
{else}
					<TD colspan="2" rowspan="5" align="center">
{/if}
						photo
                        		</TD>
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Nickname:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.pseudo_adh}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("birth date:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.ddn_adh}&nbsp;</TD>
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Status:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.libelle_statut}&nbsp;</TD> 
				</TR>
				<TR>
					<TD bgcolor="#DDDDFF"><B>{_T("Profession:")}</B></TD> 
					<TD bgcolor="#EEEEEE"><? echo $prof_adh; ?>&nbsp;</TD> 
				</TR> 
				<TR>
					<TD bgcolor="#DDDDFF"><B>{_T("Be visible in the<br /> members list :")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.bool_display_info}</TD>
				</TR>
{if $smarty.session.admin_status eq 1}
				<TR>
					<TD bgcolor="#DDDDFF"><B>{_T("Account:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.activite_adh}</TD>
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Galette Admin:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.bool_admin_adh}</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Freed of dues:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.bool_exempt_adh}</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("Language:")}<B></TD>
					<TD bgcolor="#EEEEEE"><IMG SRC="" align="left">{$adherent.pref_lang}</TD>
				</TR> 
{/if}
				<TR>
					<TD colspan="4">&nbsp;</TD> 
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF" valign="top"><B>{_T("Address:")}</B></TD> 
					<TD bgcolor="#EEEEEE" colspan="3">
						{$adherent.adresse_adh}&nbsp;<BR>
						{$adherent.adresse2_adh}&nbsp;
					</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Zip Code:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.cp_adh}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("City:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.ville_adh}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Country:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.pays_adh}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("Phone:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.tel_adh}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Mobile phone:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.gsm_adh}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("E-Mail:")}</B></TD> 
					<TD bgcolor="#EEEEEE">
{if $adherent.email_adh ne ''}					
						<A href="mailto:{$adherent.email_adh}">{$adherent.email_adh}</A>
{/if}
						&nbsp;
					</TD>
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Website:")}</B></TD> 
					<TD bgcolor="#EEEEEE">
{if $adherent.url_adh ne ''}
						<A href="{$adherent.url_adh}">{$adherent.url_adh}</A>
{/if}						
						&nbsp;
					</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("ICQ:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.icq_adh}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Jabber:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.jabber_adh}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("MSN:")}</B></TD> 
					<TD bgcolor="#EEEEEE">
{if $adherent.msn_adh ne ''}
						<A href="mailto:{$adherent.msn_adh}">{$adherent.msn_adh}</A>
{/if}
						&nbsp;
					</TD>
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Id GNUpg (GPG):")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.gpgid}&nbsp;</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("fingerprint:")}</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.fingerprint}&nbsp;</TD> 
				</TR> 
				<TR> 
					<TD colspan="4">&nbsp;</TD> 
				</TR>
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Username:")}&nbsp;</B></TD> 
					<TD bgcolor="#EEEEEE">{$adherent.login_adh}</TD> 
					<TD bgcolor="#DDDDFF"><B>{_T("Password:")}</B>&nbsp;</TD> 
					<TD bgcolor="#EEEEEE">{$adherent.mdp_adh}</TD> 
				</TR> 
{if $smarty.session.admin_status eq 1}
				<TR> 
					<TD bgcolor="#DDDDFF"><B>{_T("Creation date:")}</B>&nbsp;</TD> 
					<TD bgcolor="#EEEEEE" colspan="3">{$adherent.date_crea_adh}</TD> 
				</TR> 
				<TR> 
					<TD bgcolor="#DDDDFF" valign="top"><B>{_T("Other informations (admin):")}</B></TD> 
					<TD bgcolor="#EEEEEE" colspan="3">{$adherent.info_adh}</TD> 
				</TR>
{/if}
				<TR> 
					<TD bgcolor="#DDDDFF" valign="top"><B>{_T("Other informations:")}</B></TD> 
					<TD bgcolor="#EEEEEE" colspan="3">{$adherent.info_public_adh}</TD> 
				</TR>

{foreach from=$dynamic_fields item=field}
{if $field.perm_cat ne 1 || $smarty.session.admin_status eq 1}
{if $field.type_cat eq 0}
				<TR><TD colspan="4">&nbsp;</TD></TR>
{else}
{section name="fieldLoop" start=1 loop=$field.size_cat+1}
				<TR>
{if $smarty.section.fieldLoop.index eq 1}
					<TD bgcolor="#DDDDFF" valign="top" rowspan="{$field.size_cat}"><B>{$field.name_cat}</B>&nbsp;</TD>
{/if}
					<TD bgcolor="#EEEEEE" colspan="3">
						{$adherent.dyn[$field.id_cat][$smarty.section.fieldLoop.index]}
                                        </TD>
                                </TR>
{/section}
{/if}
{/if}
{/foreach}


				<TR>
					<TD colspan="4" align="center">
						<BR>
						<A href="ajouter_adherent.php?id_adh={$adherent.id_adh}">{_T("[ Modification ]")}</A>
						&nbsp;&nbsp;&nbsp;
						<A href="gestion_contributions.php?id_adh={$adherent.id_adh}">{_T("[ Contributions ]")}</A>
{if $smarty.session.admin_status eq 1}
						&nbsp;&nbsp;&nbsp;
						<A href="ajouter_contribution.php?id_adh={$adherent.id_adh}">{_T("[ Add a contribution ]")}</A>
{/if}
					</TD>
				</TR>
			</TABLE> 
		</DIV>
		<BR> 
		</BLOCKQUOTE> 			
