{html_doctype xhtml=true type=strict omitxml=false encoding=UTF-8}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
	<head>
		{include file='common_header.tpl'}
		<link rel="stylesheet" type="text/css" href="{$template_subdir}datePicker.css"/>
		<script type="text/javascript" src="{$jquery_dir}jquery.datePicker.js"></script>
		<script type="text/javascript" src="{$jquery_dir}date.js"></script>
		{if $lang ne 'en'}
		<script type="text/javascript" src="{$jquery_dir}date_{$galette_lang}.js"></script>
		{/if}
		<script type="text/javascript" src="{$scripts_dir}date_common.js"></script>
{literal}
		<script type="text/javascript">
			$(function(){
				$('#pref_lang').change(function(){
					$('#update_lang').attr('value', 1);
					$('#valid').attr('value', 0);
					$('#subscribtion_form').submit();
				});
			});
		</script>
{/literal}
{if $head_redirect}{$head_redirect}{/if}
	</head>
	<body>
		<div id="main_logo">
{if $smarty.session.customLogo}
			<img src="photos/0.{$smarty.session.customLogoFormat}" alt="[ Galette ]"/>
{else}
			<img src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60"/>  
{/if}
		</div>
		<h1 id="titre" class="self_subscribe">{_T string="Member profile"}</h1>
		<ul class="menu m_subscribe">
			<li id="backhome"><a href="index.php">{_T string="Back to login page"}</a></li>
			<li id="lostpassword"><a href="lostpasswd.php">{_T string="Lost your password?"}</a></li>
		</ul>
		<div>
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
{if $has_register}
		<p id="infobox">{_T string="Your account has been successfully created."}<br/>{_T string="Your browser should redirect you to the login page in a few seconds, if not, please go to: "} <a href="../index.php">{_T string="Homepage"}</a></p>
{/if}
{if !$head_redirect}
		<form action="self_adherent.php" method="post" enctype="multipart/form-data" id="subscribtion_form">
			<p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
			<fieldset class="cssform">
				<legend>{_T string="Identity:"}</legend>
				<p>
					<span class="bline libelle{if $required.titre_adh eq 1} required{/if}">{_T string="Title:"}</span>
					{if $disabled.titre_adh != ''}
						{html_radios name="titre_adh" options=$radio_titres checked=$data.titre_adh separator="&nbsp;" disabled="disabled"}
					{else}
						{html_radios name="titre_adh" options=$radio_titres checked=$data.titre_adh separator="&nbsp;"}
					{/if}
				</p>
				<p>
					<label for="nom_adh" class="bline libelle{if $required.nom_adh eq 1} required{/if}">{_T string="Name:"}</label>
					<input type="text" name="nom_adh" id="nom_adh" value="{$data.nom_adh}" maxlength="20" {$disabled.nom_adh}/>
				</p>
				<p>
					<label for="prenom_adh" class="bline libelle{if $required.prenom_adh eq 1} required{/if}">{_T string="First name:"}</label>
              				<input type="text" name="prenom_adh" id="prenom_adh" value="{$data.prenom_adh}" maxlength="20" {$disabled.prenom_adh}/>
				</p>
				<p>
					{_T string="You can prepare a picture to upload after sending"}
					{_T string="your fee."}
					<!-- l'adherent qui s'auto-inscrit ne peut pas tout de suite expedier une image -->
				</p>
				<p>
					<label for="pseudo_adh" class="bline libelle{if $required.pseudo_adh eq 1} required{/if}">{_T string="Nickname:"}</label>
					<input type="text" name="pseudo_adh" id="pseudo_adh" value="{$data.pseudo_adh}" maxlength="20" {$disabled.pseudo_adh}/>
				</p>
				<p>
					<label for="ddn_adh" class="bline libelle{if $required.ddn_adh eq 1} required{/if}">{_T string="birth date:"}</label>
					<input class="past-date-pick" type="text" name="ddn_adh" id="ddn_adh" value="{$data.ddn_adh}" maxlength="10" {$disabled.ddn_adh}/> <span class="exemple">{_T string="(dd/mm/yyyy format)"}</span>
				</p>
				<p>
					<label for="prof_adh" class="bline libelle{if $required.prof_adh eq 1} required{/if}">{_T string="Profession:"}</label>
					<input type="text" name="prof_adh" id="prof_adh" value="{$data.prof_adh}" maxlength="150" {$disabled.prof_adh}/>
				</p>
				<p>
					<label for="bool_display_info" class="bline libelle{if $required.bool_display_info eq 1} required{/if}">{_T string="Be visible in the<br /> members list :"}</label>
					<input type="checkbox" name="bool_display_info" id="bool_display_info" value="1" {if $data.bool_display_info eq 1}checked="checked"{/if} {$disabled.bool_display_info}/>
				</p>
				<p>
					<label for="pref_lang" class="bline libelle{if $required.pref_lang eq 1} required{/if}">{_T string="Language:"}</label>
					<select name="pref_lang" id="pref_lang" {$disabled.pref_lang}>
						{foreach item=langue from=$languages}
							<option value="{$langue->getID()}"{if $data.pref_lang eq $langue->getID()} selected="selected"{/if} style="background:url({$langue->getFlag()}) no-repeat;padding-left:30px;">{$langue->getName()|ucfirst}</option>
						{/foreach}
					</select>
					<input type="hidden" name="update_lang" id="update_lang" value="0" />
				</p>
			</fieldset>
			<fieldset class="cssform">
				<legend>{_T string="Contact information:"}</legend>
				<p>
					<label for="adresse_adh" class="bline libelle{if $required.adresse_adh eq 1} required{/if}">{_T string="Address:"}</label>
					<input type="text" name="adresse_adh" id="adresse_adh" value="{$data.adresse_adh}" maxlength="150" class="large" {$disabled.adresse_adh}/><br/>
					<label for="adresse2_adh" class="bline libelle{if $required.adresse_adh eq 1} required{/if}">{_T string="Address:"} {_T string=" (continuation)"}</label>
					<input type="text" name="adresse2_adh" id="adresse2_adh" value="{$data.adresse2_adh}" maxlength="150" class="large" {$disabled.adresse2_adh}/>
				</p>
				<p>
					<label for="cp_adh" class="bline libelle{if $required.cp_adh eq 1} required{/if}">{_T string="Zip Code:"}</label>
              				<input type="text" name="cp_adh" id="cp_adh" value="{$data.cp_adh}" maxlength="10" {$disabled.cp_adh}/>
				</p>
				<p>
					<label for="ville_adh" class="bline libelle{if $required.ville_adh eq 1} required{/if}">{_T string="City:"}</label>
					<input type="text" name="ville_adh" id="ville_adh" value="{$data.ville_adh}" maxlength="50" {$disabled.ville_adh}/>
				</p>
				<p>
					<label for="pays_adh" class="bline libelle{if $required.pays_adh eq 1} required{/if}">{_T string="Country:"}</label>
					<input type="text" name="pays_adh" id="pays_adh" value="{$data.pays_adh}" maxlength="50" {$disabled.pays_adh}/>
				</p>
				<p>
					<label for="tel_adh" class="bline libelle{if $required.tel_adh eq 1} required{/if}">{_T string="Phone:"}</label>
					<input type="text" name="tel_adh" id="tel_adh" value="{$data.tel_adh}" maxlength="20" {$disabled.tel_adh}/>
				</p>
				<p>
					<label for="gsm_adh" class="bline libelle{if $required.gsm_adh eq 1} required{/if}">{_T string="Mobile phone:"}</label>
					<input type="text" name="gsm_adh" id="gsm_adh" value="{$data.gsm_adh}" maxlength="20" {$disabled.gsm_adh}/>
				</p>
				<p>
					<label for="email_adh" class="bline libelle{if $required.email_adh eq 1} required{/if}">{_T string="E-Mail:"}</label>
					<input type="text" name="email_adh" id="email_adh" value="{$data.email_adh}" maxlength="150" class="large" {$disabled.email_adh}/>
				</p>
				<p>
					<label for="url_adh" class="bline libelle{if $required.url_adh eq 1} required{/if}">{_T string="Website:"}</label>
              				<input type="text" name="url_adh" id="url_adh" value="{$data.url_adh}" maxlength="200" class="large" {$disabled.url_adh}/>
				</p>
				<p>
					<label for="icq_adh" class="bline libelle{if $required.icq_adh eq 1} required{/if}">{_T string="ICQ:"}</label>
					<input type="text" name="icq_adh" id="icq_adh" value="{$data.icq_adh}" maxlength="20" {$disabled.icq_adh}/>
				</p>
				<p>
					<label for="jabber_adh" class="bline libelle{if $required.jabber_adh eq 1} required{/if}">{_T string="Jabber:"}</label>
					<input type="text" name="jabber_adh" id="jabber_adh" value="{$data.jabber_adh}" maxlength="150" class="large" {$disabled.jabber_adh}/>
				</p>
				<p>
					<label for="msn_adh" class="bline libelle{if $required.msn_adh eq 1} required{/if}">{_T string="MSN:"}</label>
					<input type="text" name="msn_adh" id="msn_adh" value="{$data.msn_adh}" maxlength="150" class="large" {$disabled.msn_adh}/>
				</p>
				<p>
					<label for="gpgid" class="bline libelle{if $required.gpgid eq 1} required{/if}">{_T string="Id GNUpg (GPG):"}</label>
					<input type="text" name="gpgid" id="gpgid" value="{$data.gpgid}" maxlength="8" size="8" {$disabled.gpgid}/>
				</p>
				<p>
					<label for="fingerprint" class="bline libelle{if $required.fingerprint eq 1} required{/if}">{_T string="fingerprint:"}</label>
					<input type="text" name="fingerprint" id="fingerprint" value="{$data.fingerprint}" maxlength="40" class="large" {$disabled.fingerprint}/>
				</p>
			</fieldset>
			<fieldset class="cssform">
				<legend>{_T string="Galette-related data:"}</legend>
				<p>
					<label for="login_adh" class="bline libelle{if $required.login_adh eq 1} required{/if}">{_T string="Username:"}</label>
					<input type="text" name="login_adh" id="login_adh" value="{$data.login_adh}" maxlength="20" {$disabled.login_adh}/>
					<span class="exemple">{_T string="(at least 4 characters)"}</span>
				</p>
				<p>
					<label for="mdp_adh" class="bline libelle{if $required.mdp_adh eq 1} required{/if}">{_T string="Password:"}</label>
					<input type="hidden" name="mdp_crypt" value="{$spam_pass}" />
					<img src="{$spam_img}" alt="{_T string="Passworg image"}" />
					<input type="text" name="mdp_adh" id="mdp_adh" value="" maxlength="20" {$disabled.mdp_adh}/>
					<span class="exemple">{_T string="Please repeat in the field the password shown in the image."}</span>
				</p>
				<p>
					<label for="info_public_adh" class="bline libelle{if $required.info_public_adh eq 1} required{/if}">{_T string="Other informations:"}</label>
					<textarea name="info_public_adh" id="info_public_adh" cols="61" rows="6" {$disabled.info_public_adh}>{$data.info_public_adh}</textarea>
				</p>
			</fieldset>
			{include file="display_dynamic_fields.tpl" is_form=true}
		<div>
			<input type="submit" class="submit" value="{_T string="Save"}"/>
			<input type="hidden" name="valid" id="valid" value="1"/>
		</div>
	</form>
{/if}

		<div id="copyright">
			<a href="http://galette.tuxfamily.org/">Galette {$GALETTE_VERSION}</a>
		</div>
	</div>
	<script type="text/javascript">
		//<![CDATA[
			$.dpText = {ldelim}
				TEXT_PREV_YEAR		:	'{_T string="Previous year"}',
				TEXT_PREV_MONTH		:	'{_T string="Previous month"}',
				TEXT_NEXT_YEAR		:	'{_T string="Next year"}',
				TEXT_NEXT_MONTH		:	'{_T string="Next month"}',
				TEXT_CLOSE		:	'{_T string="Close"}',
				TEXT_CHOOSE_DATE	:	'{_T string="Choose date"}'
			{rdelim}
			$('.past-date-pick').datePicker({ldelim}startDate:'01/01/1900'{rdelim});
		//]]>
	</script>
</body>
</html>
