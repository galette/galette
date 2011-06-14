		<h1 id="titre">{_T string="Member Profile"} {if $member->id != ""}#{$member->id} ({_T string="modification"}){else}({_T string="creation"}){/if}</h1>
		<form action="ajouter_adherent.php" method="post" enctype="multipart/form-data" id="form"> 
{if $error_detected|@count != 0}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
{if $warning_detected|@count != 0}
		<div id="warningbox">
			<h1>{_T string="- WARNING -"}</h1>
			<ul>
				{foreach from=$warning_detected item=warning}
					<li>{$warning}</li>
				{/foreach}
			</ul>
		</div>
{/if}
		<div class="bigtable">
			<fieldset class="cssform">
				<legend>{_T string="Identity:"}</legend>
				<div>
					<p>
						<span class="bline">{_T string="Picture:"}</span>
						<img src="{$galette_base_path}picture.php?id_adh={$member->id}&amp;rand={$time}" class="picture" width="{$member->picture->getOptimalWidth()}" height="{$member->picture->getOptimalHeight()}" alt="{_T string="Picture"}"/><br/>
	{if $member->hasPicture() eq 1 }
						<span class="labelalign"><label for="del_photo">{_T string="Delete image"}</label></span><input type="checkbox" name="del_photo" id="del_photo" value="1"/><br/>
	{/if}
						<input class="labelalign" type="file" name="photo"/>
					</p>
					<p>
						<span class="bline{if $required.titre_adh eq 1} required{/if}">{_T string="Title:"}</span>
						{if $disabled.titre_adh != ''}
							{html_radios name="titre_adh" options=$radio_titres checked=$member->politeness separator="&nbsp;" disabled="disabled"}
						{else}
							{html_radios name="titre_adh" options=$radio_titres checked=$member->politeness separator="&nbsp;"}
						{/if}
					</p>
					<p>
						<label for="nom_adh" class="bline{if $required.nom_adh eq 1} required{/if}">{_T string="Name:"}</label>
						<input type="text" name="nom_adh" id="nom_adh" value="{$member->name}" maxlength="20" {$disabled.nom_adh}/>
					</p>
					<p>
						<label for="prenom_adh" class="bline{if $required.prenom_adh eq 1} required{/if}">{_T string="First name:"}</label>
						<input type="text" name="prenom_adh" id="prenom_adh" value="{$member->surname}" maxlength="20" {$disabled.prenom_adh}/>
					</p>
					<p>
						<label for="pseudo_adh" class="bline {if $required.pseudo_adh eq 1} required{/if}">{_T string="Nickname:"}</label>
						<input type="text" name="pseudo_adh" id="pseudo_adh" value="{$member->nickname|htmlspecialchars}" maxlength="20" {$disabled.pseudo_adh}/>
					</p>
					<p>
						<label for="ddn_adh" class="bline{if $required.ddn_adh eq 1} required{/if}">{_T string="birth date:"}</label>
						<input type="text" name="ddn_adh" id="ddn_adh" value="{$member->birthdate}" maxlength="10" {$disabled.ddn_adh}/> <span class="exemple">{_T string="(dd/mm/yyyy format)"}</span>
					</p>
					<p>
						<label for="prof_adh" class="bline{if $required.prof_adh eq 1} required{/if}">{_T string="Profession:"}</label>
						<input type="text" name="prof_adh" id="prof_adh" value="{$member->job|htmlspecialchars}" maxlength="150" {$disabled.prof_adh}/>
					</p>
					<p>
						<label for="pref_lang" class="bline {if $required.pref_lang eq 1} required{/if}">{_T string="Language:"}</label>
						<select name="pref_lang" id="pref_lang" {$disabled.pref_lang}>
							{foreach item=langue from=$languages}
								<option value="{$langue->getID()}"{if $member->language eq $langue->getID()} selected="selected"{/if} style="background:url({$langue->getFlag()}) no-repeat;padding-left:30px;">{$langue->getName()|ucfirst}</option>
							{/foreach}
						</select>
					</p>
				</div>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T string="Galette-related data:"}</legend>
				<div>
					<p>
						<label for="bool_display_info" class="bline {if $required.bool_display_info eq 1} required{/if}">{_T string="Be visible in the<br /> members list :"}</label>
						<input type="checkbox" name="bool_display_info" id="bool_display_info" value="1" {if $member->appearsInMembersList() eq 1}checked="checked"{/if} {$disabled.bool_display_info}/>
					</p>
{if $login->isAdmin()}
					<p>
						<label for="activite_adh" class="bline {if $required.activite_adh eq 1} required{/if}">{_T string="Account:"}</label>
						<select name="activite_adh" id="activite_adh" {$disabled.activite_adh}>
							<option value="1" {if $member->isActive() eq 1}selected="selected"{/if}>{_T string="Active"}</option>
							<option value="0" {if $member->isActive() eq 0}selected="selected"{/if}>{_T string="Inactive"}</option>
						</select>
					</p>
					<p>
						<label for="id_statut" class="bline {if $required.id_statut eq 1} required{/if}">{_T string="Status:"}</label>
						<select name="id_statut" id="id_statut" {$disabled.id_statut}>
							{html_options options=$statuts selected=$member->status}
						</select>
					</p>
					<p>
						<label for="bool_admin_adh" class="bline {if $required.bool_admin_adh eq 1} required{/if}">{_T string="Galette Admin:"}</label>
						<input type="checkbox" name="bool_admin_adh" id="bool_admin_adh" value="1" {if $member->isAdmin() eq 1}checked="checked"{/if} {$disabled.bool_admin_adh}/>
					</p>
					<p>
						<label for="bool_exempt_adh" class="bline{if $required.bool_exempt_adh eq 1} required{/if}">{_T string="Freed of dues:"}</label>
						<input type="checkbox" name="bool_exempt_adh" id="bool_exempt_adh" value="1" {if $member->isDueFree() eq 1}checked="checked"{/if} {$disabled.bool_exempt_adh}/>
					</p>
{/if}
					<p>
						<label for="login_adh" class="bline{if $required.login_adh eq 1} required{/if}">{_T string="Username:"}</label>
						<input type="text" name="login_adh" id="login_adh" value="{$member->login}" maxlength="20" {$disabled.login_adh}/>
						<span class="exemple">{_T string="(at least 4 characters)"}</span>
					</p>
					<p>
						<label for="mdp_adh" class="bline{if $required.mdp_adh eq 1} required{/if}">{_T string="Password:"}</label>
						<input type="password" name="mdp_adh" id="mdp_adh" value="" maxlength="20" {$disabled.mdp_adh}/>
						<span class="exemple">{_T string="(at least 4 characters)"}</span>
					</p>
					<p>
						<input class="labelalign" type="password" name="mdp_adh2" value="" maxlength="20" {$disabled.mdp_adh}/>
						<span class="exemple">{_T string="(Confirmation)"}</span>
					</p>
{* FIXME: EN cas de modification, veut-on envoyer un mail ? *}
{if $login->isAdmin()}
					<p>
						<label for="mail_confirm" class="bline">{_T string="Send a mail:"}</label>
						<input type="checkbox" name="mail_confirm" id="mail_confirm" value="1" {if $smarty.post.mail_confirm != ""}checked="checked"{/if}/>
						<span class="exemple">
	{if $disabled.send_mail}
							{_T string="Mail has been disabled in the preferences. This functionnality is disabled."}
	{else}
							{_T string="(the member will receive his username and password by email, if he has an address.)"}
	{/if}
						</span>
					</p>
					<p>
						<label for="date_crea_adh" class="bline {if $required.date_crea_adh eq 1} required{/if}">{_T string="Creation date:"}</label>
						<input type="text" name="date_crea_adh" id="date_crea_adh" value="{$member->creation_date}" maxlength="10" {$disabled.date_crea_adh}/>
						<span class="exemple">{_T string="(dd/mm/yyyy format)"}</span>
					</p>
					<p>
						<label for="info_adh" class="bline{if $required.info_adh eq 1} required{/if}">{_T string="Other informations (admin):"}</label>
						<textarea name="info_adh" id="info_adh" cols="50" rows="6" {$disabled.info_adh}>{$member->others_infos_admin|htmlspecialchars}</textarea><br/>
						<span class="exemple labelalign">{_T string="This comment is only displayed for admins."}</span>
					</p>
{/if}
					<p>
						<label for="info_public_adh" class="bline{if $required.info_public_adh eq 1} required{/if}">{_T string="Other informations:"}</label> 
						<textarea name="info_public_adh" id="info_public_adh" cols="61" rows="6" {$disabled.info_public_adh}>{$member->other_infos|htmlspecialchars}</textarea>
{if $login->isAdmin()}
						<br/><span class="exemple labelalign">{_T string="This comment is reserved to the member."}</span>
{/if}
					</p>
				</div>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T string="Contact information:"}</legend>
				<div>
					<p>
						<label for="adresse_adh" class="bline{if $required.adresse_adh eq 1} required{/if}">{_T string="Address:"}</label>
						<input type="text" class="large" name="adresse_adh" id="adresse_adh" value="{$member->adress|htmlspecialchars}" maxlength="150" {$disabled.adresse_adh}/><br/>
{* FIXME: A-t-on réellement besoin de deux lignes pour une adresse ? *}
						<label for="adresse2_adh" class="bline libelle{if $required.adresse2_adh eq 1} required{/if}">{_T string="Address:"} {_T string=" (continuation)"}</label>
						<input type="text" class="large" name="adresse2_adh" id="adresse2_adh" value="{$member->adress_continuation|htmlspecialchars}" maxlength="150" {$disabled.adresse2_adh}/>
					</p>
					<p>
						<label for="cp_adh" class="bline{if $required.cp_adh eq 1} required{/if}">{_T string="Zip Code:"}</label>
						<input type="text" name="cp_adh" id="cp_adh" value="{$member->zipcode}" maxlength="10" {$disabled.cp_adh}/>
					</p>
					<p>
						<label for="ville_adh" class="bline{if $required.ville_adh eq 1} required{/if}">{_T string="City:"}</label>
						<input type="text" name="ville_adh" id="ville_adh" value="{$member->town|htmlspecialchars}" maxlength="50" {$disabled.ville_adh}/>
					</p>
					<p>
						<label for="pays_adh" class="bline{if $required.pays_adh eq 1} required{/if}">{_T string="Country:"}</label> 
						<input type="text" name="pays_adh" id="pays_adh" value="{$member->country|htmlspecialchars}" maxlength="50" {$disabled.pays_adh}/>
					</p>
					<p>
						<label for="tel_adh" class="bline{if $required.tel_adh eq 1} required{/if}">{_T string="Phone:"}</label>
						<input type="text" name="tel_adh" id="tel_adh" value="{$member->phone}" maxlength="20" {$disabled.tel_adh}/>
					</p>
					<p>
						<label for="gsm_adh" class="bline{if $required.gsm_adh eq 1} required{/if}">{_T string="Mobile phone:"}</label>
						<input type="text" name="gsm_adh" id="gsm_adh" value="{$member->gsm}" maxlength="20" {$disabled.gsm_adh}/>
					</p>
					<p>
						<label for="email_adh" class="bline{if $required.email_adh eq 1} required{/if}">{_T string="E-Mail:"}</label>
						<input type="text" name="email_adh" id="email_adh" value="{$member->email}" maxlength="150" size="30" {$disabled.email_adh}/>
					</p>
					<p>
						<label for="url_adh" class="bline{if $required.url_adh eq 1} required{/if}">{_T string="Website:"}</label>
						<input type="text" name="url_adh" id="url_adh" value="{$member->website}" maxlength="200" size="30" {$disabled.url_adh}/>
					</p>
					<p>
						<label for="icq_adh" class="bline{if $required.icq_adh eq 1}required{/if}">{_T string="ICQ:"}</label>
						<input type="text" name="icq_adh" id="icq_adh" value="{$member->icq}" maxlength="20" {$disabled.icq_adh}/>
					</p>
					<p>
						<label for="jabber_adh" class="bline{if $required.jabber_adh eq 1} required{/if}">{_T string="Jabber:"}</label>
						<input type="text" name="jabber_adh" id="jabber_adh" value="{$member->jabber}" maxlength="150" size="30" {$disabled.jabber_adh}/>
					</p>
					<p>
						<label for="msn_adh" class="bline{if $required.msn_adh eq 1} required{/if}">{_T string="MSN:"}</label>
						<input type="text" name="msn_adh" id="msn_adh" value="{$member->msn}" maxlength="150" size="30" {$disabled.msn_adh}/>
					</p>
					<p>
						<label for="gpgid" class="bline{if $required.gpgid eq 1} required{/if}">{_T string="Id GNUpg (GPG):"}</label>
						<input type="text" name="gpgid" id="gpgid" value="{$member->gpgid}" maxlength="8" size="8" {$disabled.gpgid}/>
					</p>
					<p>
						<label for="fingerprint" class="bline{if $required.fingerprint eq 1}required{/if}">{_T string="fingerprint:"}</label>
						<input type="text" name="fingerprint" id="fingerprint" value="{$member->fingerprint}" maxlength="40" size="40" {$disabled.fingerprint}/>
					</p>
				</div>
			</fieldset>
{include file="display_dynamic_fields.tpl" is_form=true}
		</div>
		<div class="button-container">
			<input type="submit" class="submit" name="valid" value="{_T string="Save"}"/>
			<input type="hidden" name="id_adh" value="{$member->id}"/>
		</div>
		<p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
		</form> 
		<script type="text/javascript">
			//<![CDATA[
				$(function() {ldelim}
					_collapsibleFieldsets();

					$('#ddn_adh').datepicker({ldelim}
						changeMonth: true,
						changeYear: true,
						showOn: 'button',
						buttonImage: '{$template_subdir}images/calendar.png',
						buttonImageOnly: true
					{rdelim});
					$('#date_crea_adh').datepicker({ldelim}
						showOn: 'button',
						buttonImage: '{$template_subdir}images/calendar.png',
						buttonImageOnly: true
					{rdelim});
				{rdelim});
	
			//]]>
		</script>
