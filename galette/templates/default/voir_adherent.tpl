	<h1 class="titre">{_T("Member Profile")}</h1>
    {if $error_detected|@count != 0}
    		<div id="errorbox">
    			<h1>{_T("- ERROR -")}</h1>
    			<ul>
    {foreach from=$error_detected item=error}
    				<li>{$error}</li>
    {/foreach}
    			</ul>
    		</div>
    {/if}
	{if $mail_warning}
		<div id="warningbox">
			<h1>{_T("- WARNING -")}</h1>
			<ul>
				<li>{$mail_warning}</li>
			</ul>
		</div>
	{/if}
	<div class="bigtable">
		<table>
			<tr>
				<th class="separator" colspan="2">{_T("Identity:")}</th>
			</tr>
			<tr>
				<th>{_T("Picture:")}</th>
				<td><img src="picture.php?id_adh={$data.id_adh}&amp;rand={$time}" class="picture" width="{$data.picture_width}" height="{$data.picture_height}" alt="{_T("Picture")}"/></td>
			</tr>
			<tr> 
				<th>{_T("Name:")}</th>
				<td>{$data.titre_adh} {$data.nom_adh} {$data.prenom_adh}</td>
			</tr>
			<tr> 
				<th>{_T("Nickname:")}</th> 
				<td>{$data.pseudo_adh}&nbsp;</td> 
			</tr> 
			<tr> 
				<th>{_T("birth date:")}</th> 
				<td>{$data.ddn_adh}&nbsp;</td>
			</tr>
			<tr>
				<th>{_T("Profession:")}</th> 
				<td>{$data.prof_adh}</td> 
			</tr> 
			<tr>
				<th>{_T("Language:")}</th>
				<td><img SRC="{$data.pref_lang_img}" align="left"/>{$data.pref_lang}</td>
			</tr> 
			<tr>
				<th class="separator">{_T("Galette-related data:")}</th>
			</tr>
			<tr> 
				<th>{_T("Status:")}</th> 
				<td>{$data.libelle_statut}&nbsp;</td> 
			</tr>
			<tr>
				<th>{_T("Be visible in the<br /> members list :")}</th> 
				<td>{$data.bool_display_info}</td>
			</tr>
{if $smarty.session.admin_status eq 1}
			<tr>
				<th>{_T("Account:")}</th> 
				<td>{$data.activite_adh}</td>
			</tr>
			<tr> 
				<th>{_T("Galette Admin:")}</th> 
				<td>{$data.bool_admin_adh}</td> 
			</tr> 
			<tr> 
				<th>{_T("Freed of dues:")}</th> 
				<td>{$data.bool_exempt_adh}</td>
			</tr>
{/if}
			<tr> 
				<th>{_T("Username:")}&nbsp;</th> 
				<td>{$data.login_adh}</td> 
			</tr> 
{if $smarty.session.admin_status eq 1}
			<tr> 
				<th>{_T("Creation date:")}&nbsp;</th> 
				<td>{$data.date_crea_adh}</td> 
			</tr> 
			<tr> 
				<th>{_T("Other informations (admin):")}</th> 
				<td>{$data.info_adh}</td> 
			</tr>
{/if}
			<tr>
				<th class="separator">{_T("Contact information:")}</th> 
			</tr>
			<tr> 
				<th>{_T("Address:")}</th> 
				<td>
					{$data.adresse_adh}&nbsp;
{if $data.adresse2_adh ne ''}
					<br/>{$data.adresse2_adh}&nbsp;
{/if}
				</td> 
			</tr> 
			<tr> 
				<th>{_T("Zip Code:")}</th> 
				<td>{$data.cp_adh}&nbsp;</td>
			</tr>
			<tr> 
				<th>{_T("City:")}</th> 
				<td>{$data.ville_adh}&nbsp;</td> 
			</tr> 
			<tr> 
				<th>{_T("Country:")}</th> 
				<td>{$data.pays_adh}&nbsp;</td> 
			</tr>
			<tr>
				<th>{_T("Phone:")}</th> 
				<td>{$data.tel_adh}&nbsp;</td> 
			</tr> 
			<tr> 
				<th>{_T("Mobile phone:")}</th> 
				<td>{$data.gsm_adh}&nbsp;</td> 
			</tr>
			<tr>
				<th>{_T("E-Mail:")}</th> 
				<td>
{if $data.email_adh ne ''}					
					<a href="mailto:{$data.email_adh}">{$data.email_adh}</a>
{/if}
					&nbsp;
				</td>
			</tr> 
			<tr> 
				<th>{_T("Website:")}</th> 
				<td>
{if $data.url_adh ne ''}
					<a href="{$data.url_adh}">{$data.url_adh}</a>
{/if}						
					&nbsp;
				</td>
			</tr>
			<tr>
				<th>{_T("ICQ:")}</th> 
				<td>{$data.icq_adh}&nbsp;</td> 
			</tr> 
			<tr> 
				<th>{_T("Jabber:")}</th> 
				<td>{$data.jabber_adh}&nbsp;</td> 
			</tr>
			<tr>
				<th>{_T("MSN:")}</th> 
				<td>
{if $data.msn_adh ne ''}
					<a href="mailto:{$data.msn_adh}">{$data.msn_adh}</a>
{/if}
					&nbsp;
				</td>
			</tr> 
			<tr> 
				<th>{_T("Id GNUpg (GPG):")}</th> 
				<td>{$data.gpgid}&nbsp;</td> 
			</tr>
			<tr>
				<th>{_T("fingerprint:")}</th> 
				<td>{$data.fingerprint}&nbsp;</td> 
			</tr> 
{include file="display_dynamic_fields.tpl" is_form=false}
		</table>
	</div>
	<div class="button-container">
		<div class="button-link button-edit">
			<a href="ajouter_adherent.php?id_adh={$data.id_adh}">{_T("Modification")}</a>
		</div>
		<div class="button-link button-view-contributions">
			<a href="gestion_contributions.php?id_adh={$data.id_adh}">{_T("View contributions")}</a>
		</div>
{if $smarty.session.admin_status eq 1}
		<div class="button-link button-add-contribution">
			<a href="ajouter_contribution.php?id_adh={$data.id_adh}">{_T("Add a contribution")}</a>
		</div>
{/if}
	</div>
