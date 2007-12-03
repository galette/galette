		<h1 id="titre">{_T("Member Profile")} #{$data.id_adh} ({if $data.id_adh != ""}{_T("modification")}{else}{_T("creation")}{/if})</h1>
		<form action="ajouter_adherent.php" method="post" enctype="multipart/form-data" id="form"> 
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
		<div class="bigtable">
			<fieldset class="cssform">
				<legend>{_T("Identity:")}</legend>
				<p>
					<span class="bline">{_T("Picture:")}</span>
					<img src="picture.php?id_adh={$data.id_adh}&amp;rand={$time}" class="picture" width="{$data.picture_width}" height="{$data.picture_height}" alt="{_T("Picture")}"/><br/>
	{if $data.has_picture eq 1 }
					<span class="labelalign"><label for="del_photo">{_T("Delete image")}</label></span><input type="checkbox" name="del_photo" id="del_photo" value="1"/><br/>
	{/if}
					<input class="labelalign" type="file" name="photo"/>
				</p>
				<p>
					<span class="bline{if $required.titre_adh eq 1} required{/if}">{_T("Title:")}</span>
					{html_radios name="titre_adh" options=$radio_titres checked=$data.titre_adh separator="&nbsp;&nbsp;" disabled=$disabled.titre_adh}
				</p>
				<p>
					<label for="nom_adh" class="bline{if $required.nom_adh eq 1} required{/if}">{_T("Name:")}</label>
					<input type="text" name="nom_adh" id="nom_adh" value="{$data.nom_adh}" maxlength="20" {$disabled.nom_adh}/>
				</p>
				<p>
					<label for="prenom_adh" class="bline{if $required.prenom_adh eq 1} required{/if}">{_T("First name:")}</label>
					<input type="text" name="prenom_adh" id="prenom_adh" value="{$data.prenom_adh}" maxlength="20" {$disabled.prenom_adh}/>
				</p>
				<p>
					<label for="pseudo_adh" class="bline {if $required.pseudo_adh eq 1} required{/if}">{_T("Nickname:")}</label>
					<input type="text" name="pseudo_adh" id="pseudo_adh" value="{$data.pseudo_adh}" maxlength="20" {$disabled.pseudo_adh}/>
				</p>
				<p>
					<label for="ddn_adh" class="bline{if $required.ddn_adh eq 1} required{/if}">{_T("birth date:")}</label>
					<input class="past-date-pick" type="text" name="ddn_adh" id="ddn_adh" value="{$data.ddn_adh}" maxlength="10" {$disabled.ddn_adh}/> <span class="exemple">{_T("(dd/mm/yyyy format)")}</span>
				</p>
				<p>
					<label for="prof_adh" class="bline{if $required.prof_adh eq 1} required{/if}">{_T("Profession:")}</label>
					<input type="text" name="prof_adh" id="prof_adh" value="{$data.prof_adh}" maxlength="150" {$disabled.prof_adh}/>
				</p>
				<p>
					<label for="pref_lang" class="bline {if $required.pref_lang eq 1} required{/if}">{_T("Language:")}</label>
					<select name="pref_lang" id="pref_lang" {$disabled.pref_lang}>
						{foreach item=langue from=$languages}
							<option value="{$langue->getID()}"{if $data.pref_lang eq $langue->getID()} selected="selected"{/if} style="background:url({$langue->getFlag()}) no-repeat;padding-left:30px;">{$langue->getName()|capitalize}</option>
						{/foreach}
					</select>
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T("Galette-related data:")}</legend>
				<p>
					<label for="bool_display_info" class="bline {if $required.bool_display_info eq 1} required{/if}">{_T("Be visible in the<br /> members list :")}</label>
					<input type="checkbox" name="bool_display_info" id="bool_display_info" value="1" {if $data.bool_display_info eq 1}checked="checked"{/if} {$disabled.bool_display_info}/>
				</p>
{if $smarty.session.admin_status eq 1}
				<p>
					<label for="activite_adh" class="bline {if $required.activite_adh eq 1} required{/if}">{_T("Account:")}</label>
					<select name="activite_adh" id="activite_adh" {$disabled.activite_adh}>
						<option value="1" {if $data.activite_adh eq 1}selected="selected"{/if}>{_T("Active")}</option>
						<option value="0" {if $data.activite_adh eq 0}selected="selected"{/if}>{_T("Inactive")}</option>
					</select>
				</p>
				<p>
					<label for="id_statut" class="bline {if $required.id_statut eq 1} required{/if}">{_T("Status:")}</label>
					<select name="id_statut" id="id_statut" {$disabled.id_statut}>
						{html_options options=$statuts selected=$data.id_statut}
					</select>
				</p>
				<p>
					<label for="bool_admin_adh" class="bline {if $required.bool_admin_adh eq 1} required{/if}">{_T("Galette Admin:")}</label>
					<input type="checkbox" name="bool_admin_adh" id="bool_admin_adh" value="1" {if $data.bool_admin_adh eq 1}checked="checked"{/if} {$disabled.bool_admin_adh}/>
				</p>
				<p>
					<label for="bool_exempt_adh" class="bline{if $required.bool_exempt_adh eq 1} required{/if}">{_T("Freed of dues:")}</label>
					<input type="checkbox" name="bool_exempt_adh" id="bool_exempt_adh" value="1" {if $data.bool_exempt_adh eq 1}checked="checked"{/if} {$disabled.bool_exempt_adh}/>
				</p>
{/if}
				<p>
					<label for="login_adh" class="bline{if $required.login_adh eq 1} required{/if}">{_T("Username:")}</label>
					<input type="text" name="login_adh" id="login_adh" value="{$data.login_adh}" maxlength="20" {$disabled.login_adh}/>
					<span class="exemple">{_T("(at least 4 characters)")}</span>
				</p>
				<p>
					<label for="mdp_adh" class="bline{if $required.mdp_adh eq 1} required{/if}">{_T("Password:")}</label>
					<input type="password" name="mdp_adh" id="mdp_adh" value="" maxlength="20" {$disabled.mdp_adh}/>
					<span class="exemple">{_T("(at least 4 characters)")}</span>
				</p>
				<p>
					<input class="labelalign" type="password" name="mdp_adh2" value="" maxlength="20" {$disabled.mdp_adh}/>
					<span class="exemple">{_T("(Confirmation)")}</span>
				</p>
{if $smarty.session.admin_status eq 1}
				<p>
					<label for="mail_confirm" class="bline">{_T("Send a mail:")}</label>
					<input type="checkbox" name="mail_confirm" id="mail_confirm" value="1" {if $smarty.post.mail_confirm != ""}checked="checked"{/if}/>
					<span class="exemple">{_T("(the member will receive his username and password by email, if he has an address.)")}</span>
				</p>
				<p>
					<label for="date_crea_adh" class="bline {if $required.date_crea_adh eq 1} required{/if}">{_T("Creation date:")}</label>
					<input class="past-date-pick" type="text" name="date_crea_adh" id="date_crea_adh" value="{$data.date_crea_adh}" maxlength="10" {$disabled.date_crea_adh}/>
					<span class="exemple">{_T("(dd/mm/yyyy format)")}</span>
				</p>
				<p>
					<label for="info_adh" class="bline{if $required.info_adh eq 1} required{/if}">{_T("Other informations (admin):")}</label>
					<textarea name="info_adh" id="info_adh" cols="61" rows="6" {$disabled.info_adh}>{$data.info_adh}</textarea><br/>
					<span class="exemple labelalign">{_T("This comment is only displayed for admins.")}</span>
				</p>
{/if}
				<p>
					<label for="info_public_adh" class="bline{if $required.info_public_adh eq 1} required{/if}">{_T("Other informations:")}</label> 
					<textarea name="info_public_adh" id="info_public_adh" cols="61" rows="6" {$disabled.info_public_adh}>{$data.info_public_adh}</textarea>
{if $smarty.session.admin_status eq 1}
					<br/><span class="exemple labelalign">{_T("This comment is reserved to the member.")}</span>
{/if}
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T("Contact information:")}</legend>
				<p>
					<label for="adresse_adh" class="bline{if $required.adresse_adh eq 1} required{/if}">{_T("Address:")}</label>
					<input type="text" name="adresse_adh" id="adresse_adh" value="{$data.adresse_adh}" maxlength="150" size="50" {$disabled.adresse_adh}/><br/>
					<label for="adresse2_adh" class="bline libelle{if $required.adresse_adh eq 1} required{/if}">{_T("Address:")} {_T(" (continuation)")}</label>
					<input type="text" name="adresse2_adh" id="adresse2_adh" value="{$data.adresse2_adh}" maxlength="150" size="50" {$disabled.adresse2_adh}/>
				</p>
				<p>
					<label for="cp_adh" class="bline{if $required.cp_adh eq 1} required{/if}">{_T("Zip Code:")}</label>
					<input type="text" name="cp_adh" id="cp_adh" value="{$data.cp_adh}" maxlength="10" {$disabled.cp_adh}/>
				</p>
				<p>
					<label for="ville_adh" class="bline{if $required.ville_adh eq 1} required{/if}">{_T("City:")}</label>
					<input type="text" name="ville_adh" id="ville_adh" value="{$data.ville_adh}" maxlength="50" {$disabled.ville_adh}/>
				</p>
				<p>
					<label for="pays_adh" class="bline{if $required.pays_adh eq 1} required{/if}">{_T("Country:")}</label> 
					<input type="text" name="pays_adh" id="pays_adh" value="{$data.pays_adh}" maxlength="50" {$disabled.pays_adh}/>
				</p>
				<p>
					<label for="tel_adh" class="bline{if $required.tel_adh eq 1} required{/if}">{_T("Phone:")}</label>
					<input type="text" name="tel_adh" id="tel_adh" value="{$data.tel_adh}" maxlength="20" {$disabled.tel_adh}/>
				</p>
				<p>
					<label for="gsm_adh" class="bline{if $required.gsm_adh eq 1} required{/if}">{_T("Mobile phone:")}</label>
					<input type="text" name="gsm_adh" id="gsm_adh" value="{$data.gsm_adh}" maxlength="20" {$disabled.gsm_adh}/>
				</p>
				<p>
					<label for="email_adh" class="bline{if $required.email_adh eq 1} required{/if}">{_T("E-Mail:")}</label>
					<input type="text" name="email_adh" id="email_adh" value="{$data.email_adh}" maxlength="150" size="30" {$disabled.email_adh}/>
				</p>
				<p>
					<label for="url_adh" class="bline{if $required.url_adh eq 1} required{/if}">{_T("Website:")}</label>
					<input type="text" name="url_adh" id="url_adh" value="{$data.url_adh}" maxlength="200" size="30" {$disabled.url_adh}/>
				</p>
				<p>
					<label for="icq_adh" class="bline{if $required.icq_adh eq 1}required{/if}">{_T("ICQ:")}</label>
					<input type="text" name="icq_adh" id="icq_adh" value="{$data.icq_adh}" maxlength="20" {$disabled.icq_adh}/>
				</p>
				<p>
					<label for="jabber_adh" class="bline{if $required.jabber_adh eq 1} required{/if}">{_T("Jabber:")}</label>
					<input type="text" name="jabber_adh" id="jabber_adh" value="{$data.jabber_adh}" maxlength="150" size="30" {$disabled.jabber_adh}/>
				</p>
				<p>
					<label for="msn_adh" class="bline{if $required.msn_adh eq 1} required{/if}">{_T("MSN:")}</label>
					<input type="text" name="msn_adh" id="msn_adh" value="{$data.msn_adh}" maxlength="150" size="30" {$disabled.msn_adh}/>
				</p>
				<p>
					<label for="gpgid" class="bline{if $required.gpgid eq 1} required{/if}">{_T("Id GNUpg (GPG):")}</label>
					<input type="text" name="gpgid" id="gpgid" value="{$data.gpgid}" maxlength="8" size="8" {$disabled.gpgid}/>
				</p>
				<p>
					<label for="fingerprint" class="bline{if $required.fingerprint eq 1}required{/if}">{_T("fingerprint:")}</label>
					<input type="text" name="fingerprint" id="fingerprint" value="{$data.fingerprint}" maxlength="40" size="40" {$disabled.fingerprint}/>
				</p>
			</fieldset>
{include file="display_dynamic_fields.tpl" is_form=true}
		</div>
		<div class="button-container">
			<input type="submit" class="submit" name="valid" value="{_T("Save")}"/>
			<input type="hidden" name="id_adh" value="{$data.id_adh}"/> 
		</div>
		<p>{_T("NB : The mandatory fields are in")} <span class="required">{_T("red")}</span></p>
		</form> 
