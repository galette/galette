		<h1 class="titre">{_T("Member Profile")}</h1>					
		<blockquote>
			<div align="center">
			<table border="0"> 
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Name:")}</b></td>
					<td bgcolor="#EEEEEE">{$data.titres_adh} {$data.nom_adh} {$data.prenom_adh}</td>
{if $smarty.session.admin_status eq 1}
					<td colspan="2" rowspan="8" align="center">
{else}
					<td colspan="2" rowspan="5" align="center">
{/if}
						<img src="picture.php?id_adh={$data.id_adh}&amp;rand={$time}" border="1" alt="{_T("Picture")}"/>
                        		</td>
				</tr>
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Nickname:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.pseudo_adh}&nbsp;</td> 
				</tr> 
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("birth date:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.ddn_adh}&nbsp;</td>
				</tr>
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Status:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.libelle_statut}&nbsp;</td> 
				</tr>
				<tr>
					<td bgcolor="#DDDDFF"><b>{_T("Profession:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.prof_adh}</td> 
				</tr> 
				<tr>
					<td bgcolor="#DDDDFF"><b>{_T("Be visible in the<br /> members list :")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.bool_display_info}</td>
				</tr>
{if $smarty.session.admin_status eq 1}
				<tr>
					<td bgcolor="#DDDDFF"><b>{_T("Account:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.activite_adh}</td>
				</tr>
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Galette Admin:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.bool_admin_adh}</td> 
				</tr> 
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Freed of dues:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.bool_exempt_adh}</td> 
					<td bgcolor="#DDDDFF"><b>{_T("Language:")}</b></td>
					<td bgcolor="#EEEEEE"><img SRC="{$data.pref_lang_img}" align="left"/>{$data.pref_lang}</td>
				</tr> 
{/if}
				<tr>
					<td colspan="4">&nbsp;</td> 
				</tr>
				<tr> 
					<td bgcolor="#DDDDFF" valign="top"><b>{_T("Address:")}</b></td> 
					<td bgcolor="#EEEEEE" colspan="3">
						{$data.adresse_adh}&nbsp;<br/>
						{$data.adresse2_adh}&nbsp;
					</td> 
				</tr> 
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Zip Code:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.cp_adh}&nbsp;</td> 
					<td bgcolor="#DDDDFF"><b>{_T("City:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.ville_adh}&nbsp;</td> 
				</tr> 
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Country:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.pays_adh}&nbsp;</td> 
					<td bgcolor="#DDDDFF"><b>{_T("Phone:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.tel_adh}&nbsp;</td> 
				</tr> 
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Mobile phone:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.gsm_adh}&nbsp;</td> 
					<td bgcolor="#DDDDFF"><b>{_T("E-Mail:")}</b></td> 
					<td bgcolor="#EEEEEE">
{if $data.email_adh ne ''}					
						<a href="mailto:{$data.email_adh}">{$data.email_adh}</a>
{/if}
						&nbsp;
					</td>
				</tr> 
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Website:")}</b></td> 
					<td bgcolor="#EEEEEE">
{if $data.url_adh ne ''}
						<a href="{$data.url_adh}">{$data.url_adh}</a>
{/if}						
						&nbsp;
					</td> 
					<td bgcolor="#DDDDFF"><b>{_T("ICQ:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.icq_adh}&nbsp;</td> 
				</tr> 
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Jabber:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.jabber_adh}&nbsp;</td> 
					<td bgcolor="#DDDDFF"><b>{_T("MSN:")}</b></td> 
					<td bgcolor="#EEEEEE">
{if $data.msn_adh ne ''}
						<a href="mailto:{$data.msn_adh}">{$data.msn_adh}</a>
{/if}
						&nbsp;
					</td>
				</tr> 
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Id GNUpg (GPG):")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.gpgid}&nbsp;</td> 
					<td bgcolor="#DDDDFF"><b>{_T("fingerprint:")}</b></td> 
					<td bgcolor="#EEEEEE">{$data.fingerprint}&nbsp;</td> 
				</tr> 
				<tr> 
					<td colspan="4">&nbsp;</td> 
				</tr>
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Username:")}&nbsp;</b></td> 
					<td bgcolor="#EEEEEE">{$data.login_adh}</td> 
					<td bgcolor="#DDDDFF"><b>{_T("Password:")}</b>&nbsp;</td> 
					<td bgcolor="#EEEEEE">{$data.mdp_adh}</td> 
				</tr> 
{if $smarty.session.admin_status eq 1}
				<tr> 
					<td bgcolor="#DDDDFF"><b>{_T("Creation date:")}</b>&nbsp;</td> 
					<td bgcolor="#EEEEEE" colspan="3">{$data.date_crea_adh}</td> 
				</tr> 
				<tr> 
					<td bgcolor="#DDDDFF" valign="top"><b>{_T("Other informations (admin):")}</b></td> 
					<td bgcolor="#EEEEEE" colspan="3">{$data.info_adh}</td> 
				</tr>
{/if}
				<tr> 
					<td bgcolor="#DDDDFF" valign="top"><b>{_T("Other informations:")}</b></td> 
					<td bgcolor="#EEEEEE" colspan="3">{$data.info_public_adh}</td> 
				</tr>

{include file="display_dynamic_fields.tpl" is_form=false}

				<tr>
					<td colspan="4" align="center">
						<br/>
						<a href="ajouter_adherent.php?id_adh={$data.id_adh}">{_T("[ Modification ]")}</a>
						&nbsp;&nbsp;&nbsp;
						<a href="gestion_contributions.php?id_adh={$data.id_adh}">{_T("[ Contributions ]")}</a>
{if $smarty.session.admin_status eq 1}
						&nbsp;&nbsp;&nbsp;
						<a href="ajouter_contribution.php?id_adh={$data.id_adh}">{_T("[ Add a contribution ]")}</a>
{/if}
					</td>
				</tr>
			</table> 
		</div>
		<br/> 
		</blockquote> 			
