		<h1 class="titre">{_T("Member Profile")} ({if $data.id_adh != ""}{_T("modification")}{else}{_T("creation")}{/if})</h1>
		<form action="ajouter_adherent.php" method="post" enctype="multipart/form-data" name="form"> 
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
{if $warning_detected|@count != 0}
		<div id="warningbox">
			<h1>{_T("- WARNING -")}</h1>
			<ul>
				{foreach from=$warning_detected item=warning}
					<li>{$warning}</li>
				{/foreach}
			</ul>
		</div>
{/if}
		<blockquote>
		<div align="center">
			<table border="0" id="input-table"> 
				<tr> 
					<th {if $required.titre_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Title:")}</th> 
					<td colspan="3">
						{html_radios name="titre_adh" options=$radio_titres checked=$data.titre_adh separator="&nbsp;&nbsp;" disabled=$disabled.titre_adh}
					</td> 
				</tr> 
				<tr> 
					<th {if $required.nom_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Name:")}</th> 
					<td>
						<input type="text" name="nom_adh" value="{$data.nom_adh}" maxlength="20" {$disabled.nom_adh}/>
          </td> 
					<td colspan="2" rowspan="4" align="center" width="130">
						<img src="picture.php?id_adh={$data.id_adh}&amp;rand={$time}" border="1" width="{$data.picture_width}" height="{$data.picture_height}" alt="{_T("Picture")}"/>
					 </td>
				</tr>
				<tr>
					<th {if $required.prenom_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("First name:")}</th>
					<td><input type="text" name="prenom_adh" value="{$data.prenom_adh}" maxlength="20" {$disabled.prenom_adh}/></td>
				</tr>
				<tr>
					<th {if $required.pseudo_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Nickname:")}</th>
					<td><input type="text" name="pseudo_adh" value="{$data.pseudo_adh}" maxlength="20" {$disabled.pseudo_adh}/></td>
				</tr>
				<tr>
					<th {if $required.ddn_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("birth date:")}<br/>&nbsp;</th>
					<td>
						<input type="text" name="ddn_adh" value="{$data.ddn_adh}" maxlength="10" {$disabled.ddn_adh}/><br/>
						<div class="exemple">{_T("(dd/mm/yyyy format)")}</div>
					</td>
				</tr>
				<tr>
					<th {if $required.prof_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Profession:")}</th>
					<td><input type="text" name="prof_adh" value="{$data.prof_adh}" maxlength="150" {$disabled.prof_adh}/></td>
					<th class="libelle">{_T("Photo:")}</th>
					<td>
            <p>
{if $data.has_picture eq 1 }
						<span>{_T("Delete image")}</span><input type="checkbox" name="del_photo" value="1"/><br/>
{/if}
						<input type="file" name="photo"/>
            </p>
					</td>
				</tr>
				<tr>
					<th {if $required.bool_display_info eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Be visible in the<br /> members list :")}</th>
					<td><input type="checkbox" name="bool_display_info" value="1" {if $data.bool_display_info eq 1}checked="checked"{/if} {$disabled.bool_display_info}/></td>
					<th {if $required.pref_lang eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Language:")}</th>
					<td>
						<select name="pref_lang" {$disabled.pref_lang}>
						{foreach key=langue item=langue_t from=$languages}
							<option value="{$langue}" {if $data.pref_lang eq $langue}selected="selected"{/if} style="padding-left: 30px; background-image: url(lang/{$langue}.gif); background-repeat: no-repeat">{$langue_t|capitalize}</option>
						{/foreach}
						</select>
					</td>
				</tr>
{if $smarty.session.admin_status eq 1}
				<tr> 
					<th colspan="4" class="header">&nbsp;</th> 
				</tr>
				<tr>
					<th {if $required.activite_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Account:")}</th> 
					<td>
						<select name="activite_adh" {$disabled.activite_adh}>
							<option value="1" {if $data.activite_adh eq 1}selected="selected"{/if}>{_T("Active")}</option>
							<option value="0" {if $data.activite_adh eq 0}selected="selected"{/if}>{_T("Inactive")}</option>
						</select>
					</td>
					<th class="header" colspan="2">&nbsp;</th>
				</tr>
				<tr> 
					<th {if $required.id_statut eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Status:")}</th> 
					<td>
						<select name="id_statut" {$disabled.id_statut}>
							{html_options options=$statuts selected=$data.id_statut}
						</select>
					</td>
					<th class="header" colspan="2">&nbsp;</th>
				</tr>
				<tr>
					<th {if $required.bool_admin_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Galette Admin:")}</th> 
					<td><input type="checkbox" name="bool_admin_adh" value="1" {if $data.bool_admin_adh eq 1}checked="checked"{/if} {$disabled.bool_admin_adh}/></td> 
					<th class="header" colspan="2">&nbsp;</th>
				</tr> 
				<tr> 
					<th {if $required.bool_exempt_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Freed of dues:")}</th> 
					<td><input type="checkbox" name="bool_exempt_adh" value="1" {if $data.bool_exempt_adh eq 1}checked="checked"{/if} {$disabled.bool_exempt_adh}/></td> 
					<th class="header" colspan="2">&nbsp;</th>
				</tr>
{/if}
				<tr> 
					<th colspan="4" class="header">&nbsp;</th> 
				</tr>
				<tr> 
					<th {if $required.adresse_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Address:")}</th> 
					<td colspan="3">
						<input type="text" name="adresse_adh" value="{$data.adresse_adh}" maxlength="150" size="63" {$disabled.adresse_adh}/><br/>
						<input type="text" name="adresse2_adh" value="{$data.adresse2_adh}" maxlength="150" size="63" {$disabled.adresse2_adh}/>
					</td> 
				</tr> 
				<tr> 
					<th {if $required.cp_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Zip Code:")}</th> 
					<td><input type="text" name="cp_adh" value="{$data.cp_adh}" maxlength="10" {$disabled.cp_adh}/></td> 
					<th {if $required.ville_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("City:")}</th> 
					<td><input type="text" name="ville_adh" value="{$data.ville_adh}" maxlength="50" {$disabled.ville_adh}/></td> 
				</tr>
				<tr> 
					<th {if $required.pays_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Country:")}</th> 
					<td><input type="text" name="pays_adh" value="{$data.pays_adh}" maxlength="50" {$disabled.pays_adh}/></td> 
					<th {if $required.tel_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Phone:")}</th> 
					<td><input type="text" name="tel_adh" value="{$data.tel_adh}" maxlength="20" {$disabled.tel_adh}/></td> 
				</tr> 
				<tr> 
					<th {if $required.gsm_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Mobile phone:")}</th> 
					<td><input type="text" name="gsm_adh" value="{$data.gsm_adh}" maxlength="20" {$disabled.gsm_adh}/></td> 
					<th {if $required.email_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("E-Mail:")}</th> 
					<td><input type="text" name="email_adh" value="{$data.email_adh}" maxlength="150" size="30" {$disabled.email_adh}/></td> 
				</tr> 
				<tr> 
					<th {if $required.url_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Website:")}</th> 
					<td><input type="text" name="url_adh" value="{$data.url_adh}" maxlength="200" size="30" {$disabled.url_adh}/></td> 
					<th {if $required.icq_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("ICQ:")}</th> 
					<td><input type="text" name="icq_adh" value="{$data.icq_adh}" maxlength="20" {$disabled.icq_adh}/></td> 
				</tr> 
				<tr> 
					<th {if $required.jabber_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Jabber:")}</th> 
					<td><input type="text" name="jabber_adh" value="{$data.jabber_adh}" maxlength="150" size="30" {$disabled.jabber_adh}/></td> 
					<th {if $required.msn_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("MSN:")}</th> 
					<td><input type="text" name="msn_adh" value="{$data.msn_adh}" maxlength="150" size="30" {$disabled.msn_adh}/></td> 
				</tr> 
				<tr>
					<th {if $required.gpgid eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Id GNUpg (GPG):")}</th>
					<td><input type="text" name="gpgid" value="{$data.gpgid}" maxlength="8" size="8" {$disabled.gpgid}/></td>
					<th {if $required.fingerprint eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("fingerprint:")}</th>
					<td><input type="text" name="fingerprint" value="{$data.fingerprint}" maxlength="30" size="30" {$disabled.fingerprint}/></td>
				</tr>
				<tr> 
					<th colspan="4" class="header">&nbsp;</th> 
				</tr>
				<tr> 
					<th {if $required.login_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Username:")}<br/>&nbsp;</th> 
					<td colspan="3">
						<input type="text" name="login_adh" value="{$data.login_adh}" maxlength="20" {$disabled.login_adh}/><br/>
						<div class="exemple">{_T("(at least 4 characters)")}</div>
					</td> 
				</tr>
				<tr>
					<th {if $required.mdp_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Password:")}<br/>&nbsp;</th> 
					<td colspan="3">
						<input type="password" name="mdp_adh" value="" maxlength="20" {$disabled.mdp_adh}/>
						<div class="exemple">{_T("(at least 4 characters)")}</div><br/>
						<input type="password" name="mdp_adh2" value="" maxlength="20" {$disabled.mdp_adh}/>
						<div class="exemple">{_T("(Confirmation)")}</div><br/>
					</td> 
				</tr>
{if $smarty.session.admin_status eq 1}
				<tr> 
					<th class="libelle">{_T("Send a mail:")}<br/>&nbsp;</th> 
					<td colspan="3">
						<input type="checkbox" name="mail_confirm" value="1" {if $smarty.post.mail_confirm != ""}checked="checked"{/if}/><br/>
						<div class="exemple">{_T("(the member will receive his username and password by email, if he has an address.)")}</div>
					</td> 
				</tr> 
				<tr> 
					<th {if $required.date_crea_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Creation date:")}<br/>&nbsp;</th> 
					<td colspan="3">
						<input type="text" name="date_crea_adh" value="{$data.date_crea_adh}" maxlength="10" {$disabled.date_crea_adh}/><br/>
						<div class="exemple">{_T("(dd/mm/yyyy format)")}</div>
					</td> 
				</tr> 
				<tr> 
					<th {if $required.info_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Other informations (admin):")}</th> 
					<td colspan="3">
						<textarea name="info_adh" cols="61" rows="6" {$disabled.info_adh}>{$data.info_adh}</textarea><br/>
						<div class="exemple">{_T("This comment is only displayed for admins.")}</div>
					</td>
				</tr> 
{/if}
				<tr> 
					<th {if $required.info_public_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Other informations:")}</th> 
					<td colspan="3">
						<textarea name="info_public_adh" cols="61" rows="6" {$disabled.info_public_adh}>{$data.info_public_adh}</textarea>
{if $smarty.session.admin_status eq 1}
						<br/><div class="exemple">{_T("This comment is reserved to the member.")}</div>
{/if}
					</td>
				</tr>
{include file="display_dynamic_fields.tpl" is_form=true}
				<tr> 
					<th align="center" colspan="4"><br/><input type="submit" class="submit" name="valid" value="{_T("Save")}"/></th> 
				</tr>
			</table> 
		</div>
		<br/> 
		{_T("NB : The mandatory fields are in")} <font style="color: #FF0000">{_T("red")}</font>. 
		</blockquote> 
		<input type="hidden" name="id_adh" value="{$data.id_adh}"/> 
		</form> 
