		<h1 id="titre">{_T("Settings")}</h1>
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
		<ul id="tabs">
			<li><a href="#admin">{_T("Admin")}</a></li>
			<li><a href="#cards">{_T("Cards")}</a></li>
			<li><a href="#labels">{_T("Labels")}</a></li>
			<li><a href="#mail">{_T("E-Mail")}</a></li>
			<li><a href="#parameters">{_T("Parameters")}</a></li>
			<li class="current_tab"><a href="#general">{_T("General")}</a></li>
		</ul>
		<div class="bigtable tabbed">
			<fieldset class="cssform" id="general">
				<legend>{_T("General information:")}</legend>
				<p>
					<label for="pref_nom" class="bline{if $required.pref_nom eq 1} required{/if}">{_T("Name (corporate name) of the association:")}</label>
					<input type="text" name="pref_nom" id="pref_nom" value="{$pref.pref_nom}" maxlength="190"/>
				</p>
				<p>
					<label for="pref_slogan" class="bline{if $required.pref_slogan eq 1} required{/if} tooltip" title="{_T("Enter here a short description for your association, it will be displayed on the index page and into pages' title.")}">{_T("Association's short description:")}</label>
					<span class="tip">{_T("Enter here a short description for your association, it will be displayed on the index page and into pages' title.")}</span>
					<input type="text" class="large" name="pref_slogan" id="pref_slogan" value="{$pref.pref_slogan}"/>
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
					<input type="text" name="pref_adresse" id="pref_adresse" value="{$pref.pref_adresse}" maxlength="190" class="large"/><br/>
					<label for="pref_adresse2" class="bline libelle{if $required.pref_adresse eq 1} required{/if}">{_T("Address:")} {_T(" (continuation)")}</label>
					<input type="text" name="pref_adresse2" id="pref_adresse2" value="{$pref.pref_adresse2}" maxlength="190" class="large"/>
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

			<fieldset class="cssform" id="parameters">
				<legend>{_T("Galette's parameters:")}</legend>
				<p>
					<label for="pref_lang" class="bline{if $required.pref_lang eq 1} required{/if}">{_T("Default language:")}</label>
					<select name="pref_lang" id="pref_lang">
{foreach item=langue from=$languages}
						<option value="{$langue->getID()}" {if $pref.pref_lang eq $langue->getID()}selected="selected"{/if} style="padding-left: 30px; background-image: url({$langue->getFlag()}); background-repeat: no-repeat">{$langue->getName()|capitalize}</option>
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

			<fieldset class="cssform" id="mail">
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
					<label for="pref_email_reply_to" class="bline{if $required.pref_email_reply_to eq 1} required{/if} tooltip" title="{_T("Leave empty to use Sender Email as reply address")}">{_T("Reply-To Email:")}</label>
					<span class="tip">{_T("Leave empty to use Sender Email as reply address")}</span>
					<input type="text" name="pref_email_reply_to" id="pref_email_reply_to" value="{$pref.pref_email_reply_to}" maxlength="100" size="30"/>
				</p>
				<p>
					<label for="pref_email_newadh" class="bline{if $required.pref_email_newadh eq 1} required{/if} tooltip" title="{_T("Recipient of new online registation emails")}">{_T("Members administrator's Email:")}</label>
					<span class="tip">{_T("Recipient of new online registation emails")}</span>
					<input type="text" name="pref_email_newadh" id="pref_email_newadh" value="{$pref.pref_email_newadh}" maxlength="100" size="30"/>
				</p>
				<p>
					<label for="pref_bool_mailadh" class="bline{if $required.pref_bool_mailadh eq 1} required{/if} tooltip" title="{_T("Sends an email each time a new member registers online")}">{_T("Send email to administrators ?")}</label>
					<span class="tip">{_T("Sends an email each time a new member registers online")}</span>
					<input type="checkbox" name="pref_bool_mailadh" id="pref_bool_mailadh" value="1" {if $pref.pref_bool_mailadh eq 1}checked="checked"{/if}/>
				</p>
				<p>
					<label for="pref_editor_enabled" class="bline{if $required.pref_editor_enabled eq 1} required{/if} tooltip" title="{_T("Should HTML editor be activated on page load ?")}">{_T("Activate HTML editor ?")}</label>
					<span class="tip">{_T("Should HTML editor be activated on page load ?")}</span>
					<input type="checkbox" name="pref_editor_enabled" id="pref_editor_enabled" value="1" {if $pref.pref_editor_enabled eq 1}checked="checked"{/if}/>
				</p>
				<div class="p">
					<span class="bline{if $required.pref_mail_method eq 1} required{/if}">{_T("Emailing method:")}</span>
					<ul>
						<li>
							<input type="radio" name="pref_mail_method" id="no" value="0" {if $pref.pref_mail_method eq 0}checked="checked"{/if}/><label for="no">{_T("Emailing disabled")}</label>
						</li>
						<li>
							<input type="radio" name="pref_mail_method" id="php" value="1" {if $pref.pref_mail_method eq 1}checked="checked"{/if}/><label for="php">{_T("PHP mail() function")}</label>
						</li>
						<li>
							<input type="radio" name="pref_mail_method" id="smtp" value="2" {if $pref.pref_mail_method eq 2}checked="checked"{/if}/><label for="smtp">{_T("Using a SMTP server (slower)")}</label>
						</li>
					</ul>
				</div>
				<p>
					<label for="pref_mail_smtp" class="bline{if $required.pref_mail_smtp eq 1} required{/if}">{_T("SMTP server:")}</label>
					<input type="text" name="pref_mail_smtp" id="pref_mail_smtp" value="{$pref.pref_mail_smtp}" maxlength="100" size="30"/>
				</p>
			</fieldset>

			<fieldset class="cssform" id="labels">
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

			<fieldset class="cssform" id="cards">
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
					<label for="pref_card_tcol" class="bline{if $required.pref_card_tcol eq 1} required{/if} tooltip" title="{_T("Hexadecimal color notation: #RRGGBB")}">{_T("Strip Text Color:")}</label>
					<span class="tip">{_T("Hexadecimal color notation: #RRGGBB")}</span>
					<input type="text" name="pref_card_tcol" id="pref_card_tcol" value="{$pref.pref_card_tcol}" size="7" maxlength="7" class="color_selector"/>
					<span id="pref_card_tcol_toggle" class="picker" style="background-color:{$pref.pref_card_tcol};">&nbsp;</span>
				</p>
				<div class="subtitle">{_T("Strip Background colors:")} <span class="exemple">{_T("(Strip color will change according to member's status)")}</span></div>
				<p>
					<label for="pref_card_scol" class="bline{if $required.pref_card_scol eq 1} required{/if} tooltip" title="{_T("Hexadecimal color notation: #RRGGBB")}">{_T("Active Member Color:")}</label>
					<span class="tip">{_T("Hexadecimal color notation: #RRGGBB")}</span>
					<input type="text" name="pref_card_scol" id="pref_card_scol" value="{$pref.pref_card_scol}" size="7" maxlength="7" class="color_selector"/>
					<span id="pref_card_scol_toggle" class="picker" style="background-color:{$pref.pref_card_scol};">&nbsp;</span>
				</p>
				<p>
					<label for="pref_card_bcol" class="bline{if $required.pref_card_bcol eq 1} required{/if} tooltip" title="{_T("Hexadecimal color notation: #RRGGBB")}">{_T("Board Members Color:")}</label>
					<span class="tip">{_T("Hexadecimal color notation: #RRGGBB")}</span>
					<input type="text" name="pref_card_bcol" id="pref_card_bcol" value="{$pref.pref_card_bcol}" size="7" maxlength="7" class="color_selector"/>
					<span id="pref_card_bcol_toggle" style="background-color:{$pref.pref_card_bcol};" class="picker">&nbsp;</span>
				</p>
				<p>
					<label for="pref_card_hcol" class="bline{if $required.pref_card_hcol eq 1}required{/if} tooltip" title="{_T("Hexadecimal color notation: #RRGGBB")}">{_T("Honor Members Color:")}</label>
					<span class="tip">{_T("Hexadecimal color notation: #RRGGBB")}</span>
					<input type="text" name="pref_card_hcol" id="pref_card_hcol" value="{$pref.pref_card_hcol}" size="7" maxlength="7" class="color_selector"/>
					<span id="pref_card_hcol_toggle" style="background-color:{$pref.pref_card_hcol};" class="picker">&nbsp;</span>
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
					<label for="pref_card_self" class="bline{if $required.pref_bool_display_title eq 1} required{/if}">{_T("Allow members to print card ?")}</label>
					<input type="checkbox" name="pref_card_self" id="pref_card_self" value="1" {if $pref.pref_card_self eq 1}checked="checked"{/if}/>
					<span class="exemple">{_T("(Members will be able to generate their own member card)")}</span>
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
					<span class="exemple">{_T("(Choose address printed below name)")}</span>
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

			<fieldset class="cssform" id="admin">
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
			<input type="hidden" name="valid" value="1"/>
		</div>
		<div class="button-container">
			<input type="submit" class="submit" value="{_T("Save")}"/>
		</div>
		<p>{_T("NB : The mandatory fields are in")} <span class="required">{_T("red")}</span></p>
		</form>
		<script type="text/javascript">
			//<![CDATA[
			//let's round some corners
			$('#tabs li').corner('top');
			$('.tabbed').corner('bottom');

			//if javascript active, hide tabs
			$('fieldset.cssform').slideUp('fast');
			//and then, show only the default one
			$('fieldset.cssform:first-child').slideDown('fast');
			$('fieldset.cssform:first-child').fadeIn('slow');

			//what to do when tab clicked
			$('#tabs li a').click(function(){ldelim}
				$('fieldset.cssform').slideUp('fast');
				$('.current_tab').removeClass();
				$(this).parent().addClass('current_tab');
				$($(this).attr('href')).slideDown('slow');
			{rdelim});

			//for color pickers
			$(function(){ldelim}
				$('.picker').each(function(){ldelim}
					$(this).attr('style', '');
				{rdelim});

				$('#pref_card_tcol_toggle').farbtastic('#pref_card_tcol');
				$('#pref_card_scol_toggle').farbtastic('#pref_card_scol');
				$('#pref_card_bcol_toggle').farbtastic('#pref_card_bcol');
				$('#pref_card_hcol_toggle').farbtastic('#pref_card_hcol');
				$('#pref_card_scol_toggle, #pref_card_bcol_toggle, #pref_card_hcol_toggle, #pref_card_tcol_toggle').hide();

				$('.color_selector').each(function(){ldelim}
					$(this).after(' <a href="#" id="'+$(this).attr('id')+'_show">{_T("Show/Hide color selector")}</a>');
				{rdelim});

				$('#pref_card_tcol_show').toggle(function(){ldelim}
					$('#pref_card_tcol_toggle').fadeIn();
				{rdelim},function(){ldelim}
					$('#pref_card_tcol_toggle').fadeOut();
				{rdelim});

				$('#pref_card_scol_show').toggle(function(){ldelim}
					$('#pref_card_scol_toggle').fadeIn();
				{rdelim},function(){ldelim}
					$('#pref_card_scol_toggle').fadeOut();
				{rdelim});

				$('#pref_card_bcol_show').toggle(function(){ldelim}
					$('#pref_card_bcol_toggle').fadeIn();
				{rdelim},function(){ldelim}
					$('#pref_card_bcol_toggle').fadeOut();
				{rdelim});

				$('#pref_card_hcol_show').toggle(function(){ldelim}
					$('#pref_card_hcol_toggle').fadeIn();
				{rdelim},function(){ldelim}
					$('#pref_card_hcol_toggle').fadeOut();
				{rdelim});
			{rdelim});
			//]]>
		</script>
