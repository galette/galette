{html_doctype xhtml=true type=strict omitxml=false encoding=iso-8859-1}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
	<head>
		<title>Galette {$GALETTE_VERSION}</title>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
		<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css"/>
		<script type="text/javascript" src="{$jquery_dir}jquery-1.2.1.pack.js"></script>
		<script type="text/javascript" src="{$jquery_dir}jquery.bgFade.js"></script>
		<script type="text/javascript" src="{$jquery_dir}niftycube.js"></script>
		<script type="text/javascript" src="{$scripts_dir}common.js"></script>
{literal}
		<script type="text/javascript">
		<!--
			function updatelanguage(){
				//document.cookie = "pref_lang="+document.forms[0].pref_lang.value;
				document.forms[0].update_lang.value=1;
				document.forms[0].submit();
			}
		-->
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
		<h1 id="titre">{_T("Member profile")}</h1>
		<ul class="menu m_subscribe">
			<li id="backhome"><a href="index.php">{_T("Back to login page")}</a></li>
			<li id="lostpassword"><a href="lostpasswd.php">{_T("Lost your password?")}</a></li>
		</ul>
		<div>
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
{if !$head_redirect}
		<form action="self_adherent.php" method="post" enctype="multipart/form-data">
{if !$head_redirect}
			<p>{_T("NB : The mandatory fields are in")} <span class="required">{_T("red")}</span></p>
{/if}
			<fieldset class="cssform">
				<legend>{_T("Identity:")}</legend>
				<p>
					<span class="bline libelle{if $required.titre_adh eq 1} required{/if}">{_T("Title:")}</span>
					{html_radios name="titre_adh" options=$radio_titres checked=$data.titre_adh separator="&nbsp;&nbsp;" disabled=$disabled.titre_adh}
				</p>
				<p>
					<label for="nom_adh" class="bline libelle{if $required.nom_adh eq 1} required{/if}">{_T("Name:")}</label>
					<input type="text" name="nom_adh" id="nom_adh" value="{$data.nom_adh}" maxlength="20" {$disabled.nom_adh}/>
				</p>
				<p>
					<label for="prenom_adh" class="bline libelle{if $required.prenom_adh eq 1} required{/if}">{_T("First name:")}</label>
              				<input type="text" name="prenom_adh" id="prenom_adh" value="{$data.prenom_adh}" maxlength="20" {$disabled.prenom_adh}/>
				</p>
				<p>
					{_T("You can prepare a picture to upload after sending")}
					{_T("your fee.")}
					<!-- l'adherent qui s'auto-inscrit ne peut pas tout de suite expedier une image -->
				</p>
				<p>
					<label for="pseudo_adh" class="bline libelle{if $required.pseudo_adh eq 1} required{/if}">{_T("Nickname:")}</label>
					<input type="text" name="pseudo_adh" id="pseudo_adh" value="{$data.pseudo_adh}" maxlength="20" {$disabled.pseudo_adh}/>
				</p>
				<p>
					<label for="ddn_adh" class="bline libelle{if $required.ddn_adh eq 1} required{/if}">{_T("birth date:")}</label>
					<input type="text" name="ddn_adh" id="ddn_adh" value="{$data.ddn_adh}" maxlength="10" {$disabled.ddn_adh}/> <span class="exemple">{_T("(dd/mm/yyyy format)")}</span>
				</p>
				<p>
					<label for="prof_adh" class="bline libelle{if $required.prof_adh eq 1} required{/if}">{_T("Profession:")}</label>
					<input type="text" name="prof_adh" id="prof_adh" value="{$data.prof_adh}" maxlength="150" {$disabled.prof_adh}/>
				</p>
				<p>
					<label for="bool_display_info" class="bline libelle{if $required.bool_display_info eq 1} required{/if}">{_T("Be visible in the<br /> members list :")}</label>
					<input type="checkbox" name="bool_display_info" id="bool_display_info" value="1" {if $data.bool_display_info eq 1}checked="checked"{/if} {$disabled.bool_display_info}/>
				</p>
				<p>
					<label for="pref_lang" class="bline libelle{if $required.pref_lang eq 1} required{/if}">{_T("Language:")}</label>
					<select name="pref_lang" id="pref_lang" onchange="javascript:updatelanguage();" {$disabled.pref_lang}>
						{foreach key=langue item=langue_t from=$languages}
							<option value="{$langue}" {if $data.pref_lang eq $langue}selected="selected"{/if} style="padding-left: 30px; background-image: url(lang/{$langue}.gif); background-repeat: no-repeat">{$langue_t|capitalize}</option>
						{/foreach}
					</select>
					<input type="hidden" name="update_lang" value="0" />
				</p>
			</fieldset>
			<fieldset class="cssform">
				<legend>{_T("Contact information:")}</legend>
				<p>
					<label for="adresse_adh" class="bline libelle{if $required.adresse_adh eq 1} required{/if}">{_T("Address:")}</label>
					<input type="text" name="adresse_adh" id="adresse_adh" value="{$data.adresse_adh}" maxlength="150" size="63" {$disabled.adresse_adh}/><!--<br/>
					<label for="adresse2_adh">{_T("Address:")} {_T(" (continuation)")}</label>-->
					<input type="text" name="adresse2_adh" id="adresse2_adh" value="{$data.adresse2_adh}" maxlength="150" size="63" {$disabled.adresse2_adh}/>
				</p>
				<p>
					<label for="cp_adh" class="bline libelle{if $required.cp_adh eq 1} required{/if}">{_T("Zip Code:")}</label>
              				<input type="text" name="cp_adh" id="cp_adh" value="{$data.cp_adh}" maxlength="10" {$disabled.cp_adh}/>
				</p>
				<p>
					<label for="ville_adh" class="bline libelle{if $required.ville_adh eq 1} required{/if}">{_T("City:")}</label>
					<input type="text" name="ville_adh" id="ville_adh" value="{$data.ville_adh}" maxlength="50" {$disabled.ville_adh}/>
				</p>
				<p>
					<label for="pays_adh" class="bline libelle{if $required.pays_adh eq 1} required{/if}">{_T("Country:")}</label>
					<input type="text" name="pays_adh" id="pays_adh" value="{$data.pays_adh}" maxlength="50" {$disabled.pays_adh}/>
				</p>
				<p>
					<label for="tel_adh" class="bline libelle{if $required.tel_adh eq 1} required{/if}">{_T("Phone:")}</label>
					<input type="text" name="tel_adh" id="tel_adh" value="{$data.tel_adh}" maxlength="20" {$disabled.tel_adh}/>
				</p>
				<p>
					<label for="gsm_adh" class="bline libelle{if $required.gsm_adh eq 1} required{/if}">{_T("Mobile phone:")}</label>
					<input type="text" name="gsm_adh" id="gsm_adh" value="{$data.gsm_adh}" maxlength="20" {$disabled.gsm_adh}/>
				</p>
				<p>
					<label for="email_adh" class="bline libelle{if $required.email_adh eq 1} required{/if}">{_T("E-Mail:")}</label>
					<input type="text" name="email_adh" id="email_adh" value="{$data.email_adh}" maxlength="150" size="30" {$disabled.email_adh}/>
				</p>
				<p>
					<label for="url_adh" class="bline libelle{if $required.url_adh eq 1} required{/if}">{_T("Website:")}</label>
              				<input type="text" name="url_adh" id="url_adh" value="{$data.url_adh}" maxlength="200" size="30" {$disabled.url_adh}/>
				</p>
				<p>
					<label for="icq_adh" class="bline libelle{if $required.icq_adh eq 1} required{/if}">{_T("ICQ:")}</label>
					<input type="text" name="icq_adh" id="icq_adh" value="{$data.icq_adh}" maxlength="20" {$disabled.icq_adh}/>
				</p>
				<p>
					<label for="jabber_adh" class="bline libelle{if $required.jabber_adh eq 1} required{/if}">{_T("Jabber:")}</label>
					<input type="text" name="jabber_adh" id="jabber_adh" value="{$data.jabber_adh}" maxlength="150" size="30" {$disabled.jabber_adh}/>
				</p>
				<p>
					<label for="msn_adh" class="bline libelle{if $required.msn_adh eq 1} required{/if}">{_T("MSN:")}</label>
					<input type="text" name="msn_adh" id="msn_adh" value="{$data.msn_adh}" maxlength="150" size="30" {$disabled.msn_adh}/>
				</p>
				<p>
					<label for="gpgid" class="bline libelle{if $required.gpgid eq 1} required{/if}">{_T("Id GNUpg (GPG):")}</label>
					<input type="text" name="gpgid" id="gpgid" value="{$data.gpgid}" maxlength="8" size="8" {$disabled.gpgid}/>
				</p>
				<p>
					<label for="fingerprint" class="bline libelle{if $required.fingerprint eq 1} required{/if}">{_T("fingerprint:")}</label>
					<input type="text" name="fingerprint" id="fingerprint" value="{$data.fingerprint}" maxlength="30" size="30" {$disabled.fingerprint}/>
				</p>
			</fieldset>
			<fieldset class="cssform">
				<legend>{_T("Galette-related data:")}</legend>
				<p>
					<label for="login_adh" class="bline libelle{if $required.login_adh eq 1} required{/if}">{_T("Username:")}</label>
					<input type="text" name="login_adh" id="login_adh" value="{$data.login_adh}" maxlength="20" {$disabled.login_adh}/>
					<span class="exemple">{_T("(at least 4 characters)")}</span>
				</p>
				<p>
					<label for="mdp_adh" class="bline libelle{if $required.mdp_adh eq 1} required{/if}">{_T("Password:")}</label>
					<input type="hidden" name="mdp_crypt" value="{$spam_pass}" />
					<img src="{$spam_img}" alt="{_T("Passworg image")}" />
					<input type="text" name="mdp_adh" id="mdp_adh" value="" maxlength="20" {$disabled.mdp_adh}/>
					<span class="exemple">{_T("Please repeat in the field the password shown in the image.")}</span>
				</p>
				<p>
					<label for="info_public_adh" class="bline libelle{if $required.info_public_adh eq 1} required{/if}">{_T("Other informations:")}</label>
					<textarea name="info_public_adh" id="info_public_adh" cols="61" rows="6" {$disabled.info_public_adh}>{$data.info_public_adh}</textarea>
				</p>
			</fieldset>
			{include file="display_dynamic_fields.tpl" is_form=true}
		<div>
			<input type="submit" class="submit" value="{_T("Save")}"/>
			<input type="hidden" name="valid" value="1"/>
		</div>
	</form>
{/if}

		</blockquote>
		<div id="copyright">
			<a href="http://galette.tuxfamily.org/">Galette {$GALETTE_VERSION}</a>
		</div>
	</div>
</body>
</html>
