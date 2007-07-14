		<h1 class="titre">{_T("Settings")}</h1>
		<ul id="tabs">
			<li id="main"{if $current_tab == "main"} class="selected"{/if}><a href="?tab=main">{php}echo rtrim(_T("General information:"),":"){/php}</a></li>
			<li id="mail"{if $current_tab == "mail"} class="selected"{/if}><a href="?tab=mail">{php}echo rtrim(_T("Mail settings:"),":"){/php}</a></li>
			<li id="tags"{if $current_tab == "tags"} class="selected"{/if}><a href="?tab=tags">{php}echo rtrim(_T("Label generation parameters:"),":"){/php}</a></li>
			<li id="cards"{if $current_tab == "cards"} class="selected"{/if}><a href="?tab=cards">{php}echo rtrim(_T("Cards generation parameters:"),":"){/php}</a></li>
			<li id="required"{if $current_tab == "required"} class="selected"{/if}><a href="?tab=required">{_T("Required fields")}</a></li>
			<li id="admin"{if $current_tab == "admin"} class="selected"{/if}><a href="?tab=admin">{_T("Admin account")}</a></li>
		</ul>
		<form action="preferences.php" method="post" enctype="multipart/form-data"> 
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
				<legend>{_T("General information:")}</legend>
				<p>
					<label for="pref_nom" class="bline{if $required.pref_nom eq 1} required{/if}">{_T("Name (corporate name) of the association:")}</label>
					<input type="text" name="pref_nom" id="pref_nom" value="{$pref.pref_nom}" maxlength="190"/>
				</p>
				<p>
					<label for="logo_picture" class="bline">{_T("Logo:")}</label>
{if $pref.has_logo eq 1}
					<img src="picture.php?id_adh=0&amp;rand={$time}" class="picture" width="{$pref.picture_width}" height="{$pref.picture_height}" alt="{_T("Picture")}"/><br/>
					<span>{_T("Delete image")}</span><input type="checkbox" name="del_logo" value="1" /><br />
{/if}
					<input type="file" name="logo" id="logo_picture"/>
				</p>
				<p>
					<label for="pref_adresse" class="bline{if $required.pref_adresse eq 1} required{/if}">{_T("Address:")}</label> 
					<input type="text" name="pref_adresse" id="pref_adresse" value="{$pref.pref_adresse}" maxlength="190" size="42"/><br/>
					<input type="text" name="pref_adresse2" id="pref_adresse2" value="{$pref.pref_adresse2}" maxlength="190" size="42"/>
				</p>
				<p>
					<label for="pref_cp" class="bline{if $required.pref_cp eq 1} required{/if}">{_T("Zip Code:")}</label>
					<input type="text" name="pref_cp" id="pref_cp" value="{$pref.pref_cp}" maxlength="10"/>
				</p>
				<p>
					<label for="pref_ville" class="bline{if $required.pref_ville eq 1} required{/if}">{_T("City:")}</label> 
					<input type="text" name="pref_ville" id="pref_ville" value="{$pref.pref_ville}" maxlength="100"/>
				</p>
				<p>
					<label for="pref_pays" class="bline{if $required.pref_pays eq 1} required{/if}">{_T("Country:")}</label> 
					<input type="text" name="pref_pays" id="pref_pays" value="{$pref.pref_pays}" maxlength="50"/>
				</p>
				<p>
					<label for="pref_website" class="bline{if $required.pref_website eq 1} required{/if}">{_T("Website:")}</label>
					<input type="text" name="pref_website" id="pref_website" value="{$pref.pref_website}" maxlength="100"/>
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T("Galette's parameters:")}</legend>
				<p>
					<label for="pref_lang" class="bline{if $required.pref_lang eq 1} required{/if}">{_T("Default language:")}</label>
					<select name="pref_lang" id="pref_lang">
{foreach key=langue item=langue_t from=$languages}
						<option value="{$langue}" {if $pref.pref_lang eq $langue}selected="selected"{/if} style="padding-left: 30px; background-image: url(lang/{$langue}.gif); background-repeat: no-repeat">{$langue_t|capitalize}</option>
{/foreach}
					</select>
				</p>
				<p>
					<label for="pref_numrows" class="bline{if $required.pref_numrows eq 1} required{/if}">{_T("Lines / Page:")}</label>
					<select name="pref_numrows" id="pref_numrows">
						{html_options options=$pref_numrows_options selected=$pref.pref_numrows}
					</select>
				</p>
				<p>
					<label for="pref_log" class="bline{if $required.pref_log eq 1} required{/if}">{_T("Logging level:")}</label>
					<select name="pref_log" id="pref_log">
						<option value="0" {if $pref.pref_log eq 0}selected="selected"{/if}>{_T("Disabled")}</option>
						<option value="1" {if $pref.pref_log eq 1}selected="selected"{/if}>{_T("Normal")}</option>
						<option value="2" {if $pref.pref_log eq 2}selected="selected"{/if}>{_T("Detailed")}</option>
					</select>
				</p>
				<p>
					<label for="pref_membership_ext" class="bline{if $required.pref_membership_ext eq 1} required{/if}">{_T("Default membership extension:")}</label>
					<input type="text" name="pref_membership_ext" id="pref_membership_ext" value="{$pref.pref_membership_ext}" maxlength="2"/>
					<span class="exemple">{_T("(Months)")}</span>
				</p>
				<p>
					<label for="pref_beg_membership" class="bline{if $required.pref_beg_membership eq 1} required{/if}">{_T("Beginning of membership:")}</label>
					<input type="text" name="pref_beg_membership" id="pref_beg_membership" value="{$pref.pref_beg_membership}" maxlength="5"/>
					<span class="exemple">{_T("(dd/mm)")}</span>
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T("Mail settings:")}</legend>
				<p>
					<label for="pref_email_nom" class="bline{if $required.pref_email_nom eq 1} required{/if}">{_T("Sender name:")}</label>
					<input type="text" name="pref_email_nom" id="pref_email_nom" value="{$pref.pref_email_nom}" maxlength="50"/>
				</p>
				<p>
					<label for="pref_email" class="bline{if $required.pref_email eq 1} required{/if}">{_T("Sender Email:")}</label>
					<input type="text" name="pref_email" id="pref_email" value="{$pref.pref_email}" maxlength="100" size="30"/>
				</p>
				<p>
					<label for="pref_email_reply_to" class="bline{if $required.pref_email_reply_to eq 1} required{/if}">{_T("Reply-To Email:")}</label>
					<input type="text" name="pref_email_reply_to" id="pref_email_reply_to" value="{$pref.pref_email_reply_to}" maxlength="100" size="30"/>
					<span class="exemple">{_T("Leave empty to use Sender Email as reply address")}</span>
				</p>
				<p>
					<span class="bline{if $required.pref_mail_method eq 1} required{/if}">{_T("Emailing method:")}</span>
					<input type="radio" name="pref_mail_method" id="no" value="0" {if $pref.pref_mail_method eq 0}checked="checked"{/if}/><label for="no">{_T("Emailing disabled")}</label><br />
					<input type="radio" name="pref_mail_method" id="php" value="1" {if $pref.pref_mail_method eq 1}checked="checked"{/if}/><label for="php">{_T("PHP mail() function")}</label><br />
					<input type="radio" name="pref_mail_method" id="smtp" value="2" {if $pref.pref_mail_method eq 2}checked="checked"{/if}/><label for="smtp">{_T("Using a SMTP server (slower)")}</label>
				</p>
				<p>
					<label for="pref_mail_smtp" class="bline{if $required.pref_mail_smtp eq 1} required{/if}">{_T("SMTP server:")}</label>
					<input type="text" name="pref_mail_smtp" id="pref_mail_smtp" value="{$pref.pref_mail_smtp}" maxlength="100" size="30"/>
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T("Label generation parameters:")}</legend>
				<p>
					<label for="pref_etiq_marges_v" class="bline{if $required.pref_etiq_marges_v eq 1} required{/if}">{_T("Vertical margins:")}</label>
					<input type="text" name="pref_etiq_marges_v" id="pref_etiq_marges_v" value="{$pref.pref_etiq_marges_v}" maxlength="4"/> mm 
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_etiq_marges_h" class="bline{if $required.pref_etiq_marges_h eq 1} required{/if}">{_T("Horizontal margins:")}</label> 
					<input type="text" name="pref_etiq_marges_h" id="pref_etiq_marges_h" value="{$pref.pref_etiq_marges_h}" maxlength="4"/> mm 
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_etiq_hspace" class="bline{if $required.pref_etiq_hspace eq 1} required{/if}">{_T("Horizontal spacing:")}</label>
					<input type="text" name="pref_etiq_hspace" id="pref_etiq_hspace" value="{$pref.pref_etiq_hspace}" maxlength="4"/> mm
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_etiq_vspace" class="bline{if $required.pref_etiq_vspace eq 1} required{/if}">{_T("Vertical spacing:")}</label>
					<input type="text" name="pref_etiq_vspace" id="pref_etiq_vspace" value="{$pref.pref_etiq_vspace}" maxlength="4"/> mm
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_etiq_hsize" class="bline{if $required.pref_etiq_hsize eq 1} required{/if}">{_T("Label width:")}</label>
					<input type="text" name="pref_etiq_hsize" id="pref_etiq_hsize" value="{$pref.pref_etiq_hsize}" maxlength="4"/> mm
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_etiq_vsize" class="bline{if $required.pref_etiq_vsize eq 1} required{/if}">{_T("Label height:")}</label>
					<input type="text" name="pref_etiq_vsize" id="pref_etiq_vsize" value="{$pref.pref_etiq_vsize}" maxlength="4"/> mm
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_etiq_cols" class="bline{if $required.pref_etiq_cols eq 1} required{/if}">{_T("Number of label columns:")}</label>
					<input type="text" name="pref_etiq_cols" id="pref_etiq_cols" value="{$pref.pref_etiq_cols}" maxlength="4"/>
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_etiq_rows" class="bline{if $required.pref_etiq_rows eq 1} required{/if}">{_T("Number of label lines:")}</label>
					<input type="text" name="pref_etiq_rows" id="pref_etiq_rows" value="{$pref.pref_etiq_rows}" maxlength="4"/>
				</p>
				<p>
					<label for="pref_etiq_corps" class="bline{if $required.pref_etiq_corps eq 1} required{/if}">{_T("Font size:")}</label>
					<input type="text" name="pref_etiq_corps" id="pref_etiq_corps" value="{$pref.pref_etiq_corps}" maxlength="4"/>
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T("Cards generation parameters:")}</legend>
				<p> 
					<label for="pref_card_abrev" class="bline{if $required.pref_card_abrev eq 1} required{/if}">{_T("Short Text (Card Center):")}</label>
					<input type="text" name="pref_card_abrev" id="pref_card_abrev" value="{$pref.pref_card_abrev}" size="10" maxlength="10"/>
					<span class="exemple">{_T("(10 characters max)")}</span>
				</p>
				<p> 
					<label for="pref_card_strip" class="bline{if $required.pref_card_strip eq 1} required{/if}">{_T("Long Text (Bottom Line):")}</label>
					<input type="text" name="pref_card_strip" id="pref_card_strip" value="{$pref.pref_card_strip}" size="40" maxlength="65"/>
					<span class="exemple">{_T("(65 characters max)")}</span>
				</p>
				<p>
					<label for="pref_card_tcol" class="bline{if $required.pref_card_tcol eq 1} required{/if}">{_T("Strip Text Color:")}</label> 
					<input type="text" name="pref_card_tcol" id="pref_card_tcol" value="{$pref.pref_card_tcol}" size="6" maxlength="6"/> 
					<span class="exemple">{_T("(6 hex digits:RRGGBB)")}</span>
				</p>
				<div class="subtitle">{_T("Strip Background colors:")} <span class="exemple">{_T("(Strip color will change according to member's status)")}</span></div>
				<p>
					<label for="pref_card_scol" class="bline{if $required.pref_card_scol eq 1} required{/if}">{_T("Active Member Color:")}</label>
					<input type="text" name="pref_card_scol" id="pref_card_scol" value="{$pref.pref_card_scol}" size="6" maxlength="6"/> 
					<span style="color: #{$pref.pref_card_tcol}; background-color: #{$pref.pref_card_scol};">&nbsp;{$pref.pref_card_abrev}&nbsp;</span>
					<span class="exemple">{_T("(6 hex digits:RRGGBB)")}</span>
				</p>
				<p>
					<label for="pref_card_bcol" class="bline{if $required.pref_card_bcol eq 1} required{/if}">{_T("Board Members Color:")}</label>
					<input type="text" name="pref_card_bcol" id="pref_card_bcol" value="{$pref.pref_card_bcol}" size="6" maxlength="6"/> 
					<span style="color: #{$pref.pref_card_tcol}; background-color: #{$pref.pref_card_bcol};">&nbsp;{$pref.pref_card_abrev}&nbsp;</span>
					<span class="exemple">{_T("(6 hex digits:RRGGBB)")}</span>
				</p>
				<p>
					<label for="pref_card_hcol" class="bline{if $required.pref_card_hcol eq 1}required{/if}">{_T("Honor Members Color:")}</label> 
					<input type="text" name="pref_card_hcol" id="pref_card_hcol" value="{$pref.pref_card_hcol}" size="6" maxlength="6"/> 
					<span style="color: #{$pref.pref_card_tcol}; background-color: #{$pref.pref_card_hcol};">&nbsp;{$pref.pref_card_abrev}&nbsp;</span>
					<span class="exemple">{_T("(6 hex digits:RRGGBB)")}</span>
				</p>
				<div class="subtitle">&nbsp;</div>
				<p>
					<label for="card_logo" class="bline{if $required.card_logo eq 1}required{/if}">{_T("Logo:")}</label>
{if $pref.has_card_logo eq 1}
					<img src="picture.php?id_adh=999999&amp;rand={$time}" class="picture" width="{$pref.card_logo_width}" height="{$pref.card_logo_height}" alt="{_T("Logo")}"/><br/>
					<span>{_T("Delete image")}</span><input type="checkbox" name="del_card_logo" value="1" /><br />
{/if}
					<input type="file" name="card_logo" id="card_logo"/>
				</p>
				<p>
					<label for="pref_bool_display_title" class="bline{if $required.pref_bool_display_title eq 1} required{/if}">{_T("Show title ?")}</label>
					<input type="checkbox" name="pref_bool_display_title" id="pref_bool_display_title" value="1" {if $pref.pref_bool_display_title eq 1}checked="checked"{/if}/>
					<span class="exemple">{_T("(Show or not title in front of name)")}</span>
				</p>
				<p>
					<label for="pref_card_address" class="bline{if $required.pref_card_address eq 1} required{/if}">{_T("Address type:")}</label>
					<select name="pref_card_address" id="pref_card_address">
						<option value="0" {if $pref.pref_card_address eq 0}selected="selected"{/if}>{_T("Email")}</option>
						<option value="1" {if $pref.pref_card_address eq 1}selected="selected"{/if}>{_T("MSN")}</option>
						<option value="2" {if $pref.pref_card_address eq 2}selected="selected"{/if}>{_T("Jabber")}</option>
						<option value="3" {if $pref.pref_card_address eq 3}selected="selected"{/if}>{_T("Web Site")}</option>
						<option value="4" {if $pref.pref_card_address eq 4}selected="selected"{/if}>{_T("ICQ")}</option>
						<option value="5" {if $pref.pref_card_address eq 5}selected="selected"{/if}>{_T("Zip - Town")}</option>
						<option value="6" {if $pref.pref_card_address eq 6}selected="selected"{/if}>{_T("Pseudo")}</option>
						<option value="7" {if $pref.pref_card_address eq 7}selected="selected"{/if}>{_T("Profession")}</option>
					</select>
				</p>
				<p>
					<label for="pref_card_year" class="bline{if $required.pref_card_year eq 1} required{/if}">{_T("Year:")}</label>
					<input type="text" name="pref_card_year" id="pref_card_year" value="{$pref.pref_card_year}" maxlength="4"/>
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_card_marges_v" class="bline{if $required.pref_card_marges_v eq 1} required{/if}">{_T("Vertical margins:")}</label> 
					<input type="text" name="pref_card_marges_v" id="pref_card_marges_v" value="{$pref.pref_card_marges_v}" maxlength="4"/> mm 
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_card_marges_h" class="bline{if $required.pref_card_marges_h eq 1} required{/if}">{_T("Horizontal margins:")}</label>
					<input type="text" name="pref_card_marges_h" id="pref_card_marges_h" value="{$pref.pref_card_marges_h}" maxlength="4"/> mm 
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_card_vspace" class="bline{if $required.pref_card_vspace eq 1} required{/if}">{_T("Vertical spacing:")}</label>
					<input type="text" name="pref_card_vspace" id="pref_card_vspace" value="{$pref.pref_card_vspace}" maxlength="4"/> mm
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
				<p>
					<label for="pref_card_hspace" class="bline{if $required.pref_card_hspace eq 1} required{/if}">{_T("Horizontal spacing:")}</label>
					<input type="text" name="pref_card_hspace" id="pref_card_hspace" value="{$pref.pref_card_hspace}" maxlength="4"/> mm
					<span class="exemple">{_T("(Integer)")}</span>
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T("Admin account (independant of members):")}</legend>
				<p>
					<label for="pref_admin_login" class="bline{if $required.pref_admin_login eq 1} required{/if}">{_T("Username:")}</label>
					<input type="text" name="pref_admin_login" id="pref_admin_login" value="{$pref.pref_admin_login}" maxlength="20"/>
				</p>
				<p>
					<label for="pref_admin_pass" class="bline{if $required.pref_admin_pass eq 1} required{/if}">{_T("Password:")}</label>
					<input type="password" name="pref_admin_pass" id="pref_admin_pass" value="" maxlength="20"/>
				</p>
				<p>
					<label for="pref_admin_pass_check" class="bline{if $required.pref_admin_pass_check eq 1} required{/if}">{_T("Retype password:")}</label>
					<input type="password" name="pref_admin_pass_check" id="pref_admin_pass_check" value="" maxlength="20"/>
				</p>
			</fieldset>

<!-- 			<table> -->
<!--				<tr>
					<th colspan="2" class="separator">{_T("General information:")}</th>
				</tr> 
				<tr> 
					<th {if $required.pref_nom eq 1}style="color: #FF0000;"{/if}>{_T("Name (corporate name) of the association:")}</th>
					<td><input type="text" name="pref_nom" value="{$pref.pref_nom}" maxlength="190"/></td>
				</tr>
				<tr>
					<th>{_T("Logo:")}</th> 
					<td>
{if $pref.has_logo eq 1}
						<img src="picture.php?id_adh=0&amp;rand={$time}" class="picture" width="{$pref.picture_width}" height="{$pref.picture_height}" alt="{_T("Picture")}"/><br/>
						<span>{_T("Delete image")}</span><input type="checkbox" name="del_logo" value="1" /><br />
{/if}
						<input type="file" name="logo" />
					</td>
				</tr>
				<tr>
					<th {if $required.pref_adresse eq 1}style="color: #FF0000;"{/if}>{_T("Address:")}</th> 
					<td>
						<input type="text" name="pref_adresse" value="{$pref.pref_adresse}" maxlength="190" size="42"/><br/>
						<input type="text" name="pref_adresse2" value="{$pref.pref_adresse2}" maxlength="190" size="42"/>
					</td> 
				</tr>         
				<tr>
					<th {if $required.pref_cp eq 1}style="color: #FF0000;"{/if}>{_T("Zip Code:")}</th>
					<td><input type="text" name="pref_cp" value="{$pref.pref_cp}" maxlength="10"/></td>
				</tr>         
				<tr>
					<th {if $required.pref_ville eq 1}style="color: #FF0000;"{/if}>{_T("City:")}</th> 
					<td><input type="text" name="pref_ville" value="{$pref.pref_ville}" maxlength="100"/></td>
				</tr>         
				<tr>
					<th {if $required.pref_pays eq 1}style="color: #FF0000;"{/if}>{_T("Country:")}</th> 
					<td><input type="text" name="pref_pays" value="{$pref.pref_pays}" maxlength="50"/></td>
				</tr>         
				<tr>
					<th {if $required.pref_website eq 1}style="color: #FF0000;"{/if}>{_T("Website:")}</th> 
					<td><input type="text" name="pref_website" value="{$pref.pref_website}" maxlength="100"/></td>
				</tr>-->
<!--				<tr>
					<th colspan="2" class="separator">{_T("Galette's parameters:")}</th>
				</tr> 
				<tr>
					<th {if $required.pref_lang eq 1}style="color: #FF0000;"{/if}>{_T("Default language:")}</th>
					<td>
						<select name="pref_lang">
{foreach key=langue item=langue_t from=$languages}
							<option value="{$langue}" {if $pref.pref_lang eq $langue}selected="selected"{/if} style="padding-left: 30px; background-image: url(lang/{$langue}.gif); background-repeat: no-repeat">{$langue_t|capitalize}</option>
{/foreach}
						</select>
					</td> 
				</tr>         
				<tr>
					<th {if $required.pref_numrows eq 1}style="color: #FF0000;"{/if}>{_T("Lines / Page:")}</th>
					<td>
						<select name="pref_numrows">
							{html_options options=$pref_numrows_options selected=$pref.pref_numrows}
						</select>
					</td>
				</tr>         
				<tr>
					<th {if $required.pref_log eq 1}style="color: #FF0000;"{/if}>{_T("Logging level:")}</th>
					<td>
						<select name="pref_log">
							<option value="0" {if $pref.pref_log eq 0}selected="selected"{/if}>{_T("Disabled")}</option>
							<option value="1" {if $pref.pref_log eq 1}selected="selected"{/if}>{_T("Normal")}</option>
							<option value="2" {if $pref.pref_log eq 2}selected="selected"{/if}>{_T("Detailed")}</option>
						</select>
					</td>
				</tr>         
				<tr>
					<th {if $required.pref_membership_ext eq 1}style="color: #FF0000;"{/if}>{_T("Default membership extension:")}</th>
					<td>
						<input type="text" name="pref_membership_ext" value="{$pref.pref_membership_ext}" maxlength="2"/>
						<span class="exemple">{_T("(Months)")}</span>
					</td>
				</tr>         
				<tr>
					<th {if $required.pref_beg_membership eq 1}style="color: #FF0000;"{/if}>{_T("Beginning of membership:")}</th>
					<td>
						<input type="text" name="pref_beg_membership" value="{$pref.pref_beg_membership}" maxlength="5"/>
						<span class="exemple">{_T("(dd/mm)")}</span>
					</td>
				</tr>         -->
<!--				<tr>
					<th colspan="2" class="separator">{_T("Mail settings:")}</th>
				</tr> 
				<tr>
					<th {if $required.pref_email_nom eq 1}style="color: #FF0000;"{/if}>{_T("Sender name:")}</th> 
					<td><input type="text" name="pref_email_nom" value="{$pref.pref_email_nom}" maxlength="50"/></td>
				</tr>         
				<tr>
					<th {if $required.pref_email eq 1}style="color: #FF0000;"{/if}>{_T("Sender Email:")}</th> 
					<td><input type="text" name="pref_email" value="{$pref.pref_email}" maxlength="100" size="30"/></td>
				</tr>
				<tr>
					<th {if $required.pref_email_reply_to eq 1}style="color: #FF0000;"{/if}>{_T("Reply-To Email:")}</th> 
					<td><input type="text" name="pref_email_reply_to" value="{$pref.pref_email_reply_to}" maxlength="100" size="30"/><br/><span class="exemple">{_T("Leave empty to use Sender Email as reply address")}</span></td>
				</tr>
				<tr>
					<th {if $required.pref_mail_method eq 1}style="color: #FF0000;"{/if}>{_T("Emailing method:")}</th>
					<td>
						<p><input type="radio" name="pref_mail_method" id="no" value="0" {if $pref.pref_mail_method eq 0}checked="checked"{/if}/><label for="no">{_T("Emailing disabled")}</label><br />
						<input type="radio" name="pref_mail_method" id="php" value="1" {if $pref.pref_mail_method eq 1}checked="checked"{/if}/><label for="php">{_T("PHP mail() function")}</label><br />
						<input type="radio" name="pref_mail_method" id="smtp" value="2" {if $pref.pref_mail_method eq 2}checked="checked"{/if}/><label for="smtp">{_T("Using a SMTP server (slower)")}</label></p>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_mail_smtp eq 1}style="color: #FF0000;"{/if}>{_T("SMTP server:")}</th>
					<td><input type="text" name="pref_mail_smtp" value="{$pref.pref_mail_smtp}" maxlength="100" size="30"/></td>
				</tr>-->
<!--				<tr>
					<th colspan="2" class="separator">{_T("Label generation parameters:")}</th>
				</tr>
				<tr>
					<th {if $required.pref_etiq_marges_v eq 1}style="color: #FF0000;"{/if}>{_T("Vertical margins:")}</th> 
					<td>
						<input type="text" name="pref_etiq_marges_v" value="{$pref.pref_etiq_marges_v}" maxlength="4"/> mm 
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_etiq_marges_h eq 1}style="color: #FF0000;"{/if}>{_T("Horizontal margins:")}</th> 
					<td>
						<input type="text" name="pref_etiq_marges_h" value="{$pref.pref_etiq_marges_h}" maxlength="4"/> mm 
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_etiq_hspace eq 1}style="color: #FF0000;"{/if}>{_T("Horizontal spacing:")}</th>
					<td>
						<input type="text" name="pref_etiq_hspace" value="{$pref.pref_etiq_hspace}" maxlength="4"/> mm
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_etiq_vspace eq 1}style="color: #FF0000;"{/if}>{_T("Vertical spacing:")}</th>
					<td>
						<input type="text" name="pref_etiq_vspace" value="{$pref.pref_etiq_vspace}" maxlength="4"/> mm
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_etiq_hsize eq 1}style="color: #FF0000;"{/if}>{_T("Label width:")}</th>
					<td>
						<input type="text" name="pref_etiq_hsize" value="{$pref.pref_etiq_hsize}" maxlength="4"/> mm
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_etiq_vsize eq 1}style="color: #FF0000;"{/if}>{_T("Label height:")}</th>
					<td>
						<input type="text" name="pref_etiq_vsize" value="{$pref.pref_etiq_vsize}" maxlength="4"/> mm
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_etiq_cols eq 1}style="color: #FF0000;"{/if}>{_T("Number of label columns:")}</th>
				<td>
						<input type="text" name="pref_etiq_cols" value="{$pref.pref_etiq_cols}" maxlength="4"/>
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_etiq_rows eq 1}style="color: #FF0000;"{/if}>{_T("Number of label lines:")}</th>
					<td>
						<input type="text" name="pref_etiq_rows" value="{$pref.pref_etiq_rows}" maxlength="4"/>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_etiq_corps eq 1}style="color: #FF0000;"{/if}>{_T("Font size:")}</th>
					<td>
						<input type="text" name="pref_etiq_corps" value="{$pref.pref_etiq_corps}" maxlength="4"/>
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>-->
				<!--<tr>
					<th colspan="2" class="separator">{_T("Cards generation parameters:")}</th>
				</tr>
				<tr> 
					<th {if $required.pref_card_abrev eq 1}style="color: #FF0000;"{/if}>{_T("Short Text (Card Center):")}</th>
					<td><input type="text" name="pref_card_abrev" value="{$pref.pref_card_abrev}" size="10" maxlength="10"/>
						<span class="exemple">{_T("(10 characters max)")}</span>
					</td>
				</tr>
				<tr> 
					<th {if $required.pref_card_strip eq 1}style="color: #FF0000;"{/if}>{_T("Long Text (Bottom Line):")}</th>
					<td><input type="text" name="pref_card_strip" value="{$pref.pref_card_strip}" size="40" maxlength="65"/>
						<span class="exemple">{_T("(65 characters max)")}</span>
						</td>
				</tr>
				<tr>
					<th {if $required.pref_card_tcol eq 1}style="color: #FF0000;"{/if}>{_T("Strip Text Color:")}</th> 
					<td>
						<input type="text" name="pref_card_tcol" value="{$pref.pref_card_tcol}" size="6" maxlength="6"/> 
						<span class="exemple">{_T("(6 hex digits:RRGGBB)")}</span>
					</td>
				</tr>
				<tr>
					<th class="subtitle">{_T("Strip Background colors:")}</th>
					<td class="exemple">{_T("(Strip color will change according to member's status)")}</td>
				</tr>
				<tr>
					<th {if $required.pref_card_scol eq 1}style="color: #FF0000;"{/if}>{_T("Active Member Color:")}</th> 
					<td>
						<input type="text" name="pref_card_scol" value="{$pref.pref_card_scol}" size="6" maxlength="6"/> 
						<span style="color: #{$pref.pref_card_tcol}; background-color: #{$pref.pref_card_scol};">&nbsp;{$pref.pref_card_abrev}&nbsp;</span>
						<span class="exemple">{_T("(6 hex digits:RRGGBB)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_card_bcol eq 1}style="color: #FF0000;"{/if}>{_T("Board Members Color:")}</th> 
					<td>
						<input type="text" name="pref_card_bcol" value="{$pref.pref_card_bcol}" size="6" maxlength="6"/> 
						<span style="color: #{$pref.pref_card_tcol}; background-color: #{$pref.pref_card_bcol};">&nbsp;{$pref.pref_card_abrev}&nbsp;</span>
						<span class="exemple">{_T("(6 hex digits:RRGGBB)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_card_hcol eq 1}style="color: #FF0000;"{/if}>{_T("Honor Members Color:")}</th> 
					<td>
						<input type="text" name="pref_card_hcol" value="{$pref.pref_card_hcol}" size="6" maxlength="6"/> 
						<span style="color: #{$pref.pref_card_tcol}; background-color: #{$pref.pref_card_hcol};">&nbsp;{$pref.pref_card_abrev}&nbsp;</span>
						<span class="exemple">{_T("(6 hex digits:RRGGBB)")}</span>
					</td>
				</tr>
				<tr>
					<th>{_T("Logo:")}</th> 
					<td>
{if $pref.has_card_logo eq 1}
						<img src="picture.php?id_adh=999999&amp;rand={$time}" class="picture" width="{$pref.card_logo_width}" height="{$pref.card_logo_height}" alt="{_T("Logo")}"/><br/>
						<span>{_T("Delete image")}</span><input type="checkbox" name="del_card_logo" value="1" /><br />
{/if}
						<input type="file" name="card_logo" />
					</td>
				</tr>
				<tr>
					<th {if $required.pref_bool_display_title eq 1}style="color: #FF0000;"{/if}>{_T("Show title ?")}</th>
					<td><input type="checkbox" name="pref_bool_display_title" value="1" {if $pref.pref_bool_display_title eq 1}checked="checked"{/if}/>
						<span class="exemple">{_T("(Show or not title in front of name)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_card_address eq 1}style="color: #FF0000;"{/if}>{_T("Address type:")}</th>
					<td>
						<select name="pref_card_address">
							<option value="0" {if $pref.pref_card_address eq 0}selected="selected"{/if}>{_T("Email")}</option>
							<option value="1" {if $pref.pref_card_address eq 1}selected="selected"{/if}>{_T("MSN")}</option>
							<option value="2" {if $pref.pref_card_address eq 2}selected="selected"{/if}>{_T("Jabber")}</option>
							<option value="3" {if $pref.pref_card_address eq 3}selected="selected"{/if}>{_T("Web Site")}</option>
							<option value="4" {if $pref.pref_card_address eq 4}selected="selected"{/if}>{_T("ICQ")}</option>
							<option value="5" {if $pref.pref_card_address eq 5}selected="selected"{/if}>{_T("Zip - Town")}</option>
							<option value="6" {if $pref.pref_card_address eq 6}selected="selected"{/if}>{_T("Pseudo")}</option>
							<option value="7" {if $pref.pref_card_address eq 7}selected="selected"{/if}>{_T("Profession")}</option>
						</select>
					</td>
				</tr>         
				<tr>
					<th {if $required.pref_card_year eq 1}style="color: #FF0000;"{/if}>{_T("Year:")}</th> 
					<td>
						<input type="text" name="pref_card_year" value="{$pref.pref_card_year}" maxlength="4"/>
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_card_marges_v eq 1}style="color: #FF0000;"{/if}>{_T("Vertical margins:")}</th> 
					<td>
						<input type="text" name="pref_card_marges_v" value="{$pref.pref_card_marges_v}" maxlength="4"/> mm 
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_card_marges_h eq 1}style="color: #FF0000;"{/if}>{_T("Horizontal margins:")}</th> 
					<td>
						<input type="text" name="pref_card_marges_h" value="{$pref.pref_card_marges_h}" maxlength="4"/> mm 
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_card_vspace eq 1}style="color: #FF0000;"{/if}>{_T("Vertical spacing:")}</th>
					<td>
						<input type="text" name="pref_card_vspace" value="{$pref.pref_card_vspace}" maxlength="4"/> mm
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_card_hspace eq 1}style="color: #FF0000;"{/if}>{_T("Horizontal spacing:")}</th>
					<td>
						<input type="text" name="pref_card_hspace" value="{$pref.pref_card_hspace}" maxlength="4"/> mm
						<span class="exemple">{_T("(Integer)")}</span>
					</td>
				</tr>-->
				<!--<tr>
					<th colspan="2" class="separator">{_T("Admin account (independant of members):")}</th>
				</tr>
				<tr>
					<th {if $required.pref_admin_login eq 1}style="color: #FF0000;"{/if}>{_T("Username:")}</th>
					<td>
						<input type="text" name="pref_admin_login" value="{$pref.pref_admin_login}" maxlength="20"/>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_admin_pass eq 1}style="color: #FF0000;"{/if}>{_T("Password:")}</th>
					<td>
						<input type="password" name="pref_admin_pass" value="" maxlength="20"/>
					</td>
				</tr>
				<tr>
					<th {if $required.pref_admin_pass_check eq 1}style="color: #FF0000;"{/if}>{_T("Retype password:")}</th>
					<td>
						<input type="password" name="pref_admin_pass_check" value="" maxlength="20"/>
					</td>
				</tr>-->
<!-- 			</table> -->
			<input type="hidden" name="valid" value="1"/>
		</div>
		<div class="button-container">
			<input type="submit" class="submit" value="{_T("Save")}"/>
		</div>
		<p>{_T("NB : The mandatory fields are in")} <span style="color: #FF0000">{_T("red")}</span></p>
		</form>
