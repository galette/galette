	<h1 id="titre">{_T("Member Profile")}</h1>
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
	<div class="bigtable">

	<ul id="details_menu">
{if ($data.pref_card_self eq 1) or ($smarty.session.admin_status eq 1)}
		<!--<li class="button-link button-card">-->
		<li>
			<a href="carte_adherent.php?id_adh={$data.id_adh}" id="btn_membercard">{_T("Generate Member Card")}</a>
		</li>
{/if}
		<!--<li class="button-link button-edit">-->
		<li>
			<a href="ajouter_adherent.php?id_adh={$data.id_adh}" id="btn_edit">{_T("Modification")}</a>
		</li>
		<!--<li class="button-link button-view-contributions">-->
		<li>
			<a href="gestion_contributions.php?id_adh={$data.id_adh}" id="btn_contrib">{_T("View contributions")}</a>
		</li>
{if $smarty.session.admin_status eq 1}
		<!--<li class="button-link button-add-contribution">-->
		<li>
			<a href="ajouter_contribution.php?id_adh={$data.id_adh}" id="btn_addcontrib">{_T("Add a contribution")}</a>
		</li>
{/if}
	</ul>

		<table class="details">
			<caption>{_T("Identity:")}</caption>
			<tr>
				<th>{_T("Name:")}</th>
				<td>{$data.titre_adh} {$data.nom_adh} {$data.prenom_adh}</td>
				<td rowspan="5" class="photo"><img src="picture.php?id_adh={$data.id_adh}&amp;rand={$time}" class="picture" width="{$data.picture_width}" height="{$data.picture_height}" alt="{_T("Picture")}"/></td>
			</tr>
			<tr>
				<th>{_T("Nickname:")}</th>
				<td>{$data.pseudo_adh}</td>
			</tr> 
			<tr> 
				<th>{_T("birth date:")}</th>
				<td>{$data.ddn_adh}</td>
			</tr>
			<tr>
				<th>{_T("Profession:")}</th>
				<td>{$data.prof_adh}</td>
			</tr>
			<tr>
				<th>{_T("Language:")}</th>
				<td><img src="{$data.pref_lang_img}" alt=""/> {$data.pref_lang}</td>
			</tr>
		</table>

		<table class="details">
			<caption>{_T("Galette-related data:")}</caption>
			<tr>
				<th>{_T("Status:")}</th>
				<td>{$data.libelle_statut}</td>
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
				<th>{_T("Username:")}</th>
				<td>{$data.login_adh}</td>
			</tr>
{if $smarty.session.admin_status eq 1}
			<tr>
				<th>{_T("Creation date:")}</th>
				<td>{$data.date_crea_adh}</td>
			</tr>
			<tr>
				<th>{_T("Other informations (admin):")}</th>
				<td>{$data.info_adh}</td>
			</tr>
{/if}
		</table>

		<table class="details">
			<caption>{_T("Contact information:")}</caption>
			<tr>
				<th>{_T("Address:")}</th> 
				<td>
					{$data.adresse_adh}
{if $data.adresse2_adh ne ''}
					<br/>{$data.adresse2_adh}
{/if}
				</td>
			</tr>
			<tr>
				<th>{_T("Zip Code:")}</th>
				<td>{$data.cp_adh}</td>
			</tr>
			<tr>
				<th>{_T("City:")}</th>
				<td>{$data.ville_adh}</td>
			</tr>
			<tr>
				<th>{_T("Country:")}</th>
				<td>{$data.pays_adh}</td>
			</tr>
			<tr>
				<th>{_T("Phone:")}</th>
				<td>{$data.tel_adh}</td>
			</tr>
			<tr>
				<th>{_T("Mobile phone:")}</th>
				<td>{$data.gsm_adh}</td>
			</tr>
			<tr>
				<th>{_T("E-Mail:")}</th>
				<td>
{if $data.email_adh ne ''}					
					<a href="mailto:{$data.email_adh}">{$data.email_adh}</a>
{/if}
				</td>
			</tr>
			<tr>
				<th>{_T("Website:")}</th>
				<td>
{if $data.url_adh ne ''}
					<a href="{$data.url_adh}">{$data.url_adh}</a>
{/if}						
				</td>
			</tr>
			<tr>
				<th>{_T("ICQ:")}</th>
				<td>{$data.icq_adh}</td>
			</tr>
			<tr>
				<th>{_T("Jabber:")}</th>
				<td>{$data.jabber_adh}</td>
			</tr>
			<tr>
				<th>{_T("MSN:")}</th>
				<td>
{if $data.msn_adh ne ''}
					<a href="mailto:{$data.msn_adh}">{$data.msn_adh}</a>
{/if}
				</td>
			</tr>
			<tr>
				<th>{_T("Id GNUpg (GPG):")}</th>
				<td>{$data.gpgid}</td>
			</tr>
			<tr>
				<th>{_T("fingerprint:")}</th>
				<td>{$data.fingerprint}</td>
			</tr>
		</table>

{include file="display_dynamic_fields.tpl" is_form=false}
	</div>
	<!--<div class="button-container">
{if ($data.pref_card_self eq 1) or ($smarty.session.admin_status eq 1)}
		<div class="button-link button-card">
			<a href="carte_adherent.php?id_adh={$data.id_adh}">{_T("Generate Member Card")}</a>
		</div>
{/if}
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
	</div>-->
