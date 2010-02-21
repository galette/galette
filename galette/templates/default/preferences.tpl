		<h1 class="titre">{_T string="Settings"}</h1>
		<form action="preferences.php" method="post" enctype="multipart/form-data">
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
				<legend>{_T string="General information:"}</legend>
				<p>
					<label for="pref_nom" class="bline{if $required.pref_nom eq 1} required{/if}">{_T string="Name (corporate name) of the association:"}</label>
					<input type="text" name="pref_nom" id="pref_nom" value="{$pref.pref_nom}" maxlength="190"/>
				</p>
				<p>
					<label for="logo_picture" class="bline">{_T string="Logo:"}</label>
{if $pref.has_logo eq 1}
					<img src="picture.php?id_adh=0&amp;rand={$time}" class="picture" width="{$pref.picture_width}" height="{$pref.picture_height}" alt="{_T string="Picture"}"/><br/>
					<label for="del_logo">{_T string="Delete image"}</label><input type="checkbox" name="del_logo" id="del_logo" value="1" /><br />
{/if}
					<input type="file" name="logo" id="logo_picture"/>
				</p>
				<p>
					<label for="pref_adresse" class="bline{if $required.pref_adresse eq 1} required{/if}">{_T string="Address:"}</label>
					<input type="text" name="pref_adresse" id="pref_adresse" value="{$pref.pref_adresse}" maxlength="190" size="42"/><br/>
					<input type="text" name="pref_adresse2" id="pref_adresse2" value="{$pref.pref_adresse2}" maxlength="190" size="42"/>
				</p>
				<p>
					<label for="pref_cp" class="bline{if $required.pref_cp eq 1} required{/if}">{_T string="Zip Code:"}</label>
					<input type="text" name="pref_cp" id="pref_cp" value="{$pref.pref_cp}" maxlength="10"/>
				</p>
				<p>
					<label for="pref_ville" class="bline{if $required.pref_ville eq 1} required{/if}">{_T string="City:"}</label>
					<input type="text" name="pref_ville" id="pref_ville" value="{$pref.pref_ville}" maxlength="100"/>
				</p>
				<p>
					<label for="pref_pays" class="bline{if $required.pref_pays eq 1} required{/if}">{_T string="Country:"}</label>
					<input type="text" name="pref_pays" id="pref_pays" value="{$pref.pref_pays}" maxlength="50"/>
				</p>
				<p>
					<label for="pref_website" class="bline{if $required.pref_website eq 1} required{/if}">{_T string="Website:"}</label>
					<input type="text" name="pref_website" id="pref_website" value="{$pref.pref_website}" maxlength="100"/>
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T string="Galette's parameters:"}</legend>
				<p>
					<label for="pref_lang" class="bline{if $required.pref_lang eq 1} required{/if}">{_T string="Default language:"}</label>
					<select name="pref_lang" id="pref_lang">
{foreach key=langue item=langue_t from=$languages}
						<option value="{$langue}" {if $pref.pref_lang eq $langue}selected="selected"{/if} style="padding-left: 30px; background-image: url(lang/{$langue}.gif); background-repeat: no-repeat">{$langue_t|capitalize}</option>
{/foreach}
					</select>
				</p>
				<p>
					<label for="pref_numrows" class="bline{if $required.pref_numrows eq 1} required{/if}">{_T string="Lines / Page:"}</label>
					<select name="pref_numrows" id="pref_numrows">
						{html_options options=$pref_numrows_options selected=$pref.pref_numrows}
					</select>
				</p>
				<p>
					<label for="pref_log" class="bline{if $required.pref_log eq 1} required{/if}">{_T string="Logging level:"}</label>
					<select name="pref_log" id="pref_log">
						<option value="0" {if $pref.pref_log eq 0}selected="selected"{/if}>{_T string="Disabled"}</option>
						<option value="1" {if $pref.pref_log eq 1}selected="selected"{/if}>{_T string="Normal"}</option>
						<option value="2" {if $pref.pref_log eq 2}selected="selected"{/if}>{_T string="Detailed"}</option>
					</select>
				</p>
				<p>
					<label for="pref_membership_ext" class="bline{if $required.pref_membership_ext eq 1} required{/if}">{_T string="Default membership extension:"}</label>
					<input type="text" name="pref_membership_ext" id="pref_membership_ext" value="{$pref.pref_membership_ext}" maxlength="2"/>
					<span class="exemple">{_T string="(Months)"}</span>
				</p>
				<p>
					<label for="pref_beg_membership" class="bline{if $required.pref_beg_membership eq 1} required{/if}">{_T string="Beginning of membership:"}</label>
					<input type="text" name="pref_beg_membership" id="pref_beg_membership" value="{$pref.pref_beg_membership}" maxlength="5"/>
					<span class="exemple">{_T string="(dd/mm)"}</span>
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T string="Mail settings:"}</legend>
				<p>
					<label for="pref_email_nom" class="bline{if $required.pref_email_nom eq 1} required{/if}">{_T string="Sender name:"}</label>
					<input type="text" name="pref_email_nom" id="pref_email_nom" value="{$pref.pref_email_nom}" maxlength="50"/>
				</p>
				<p>
					<label for="pref_email" class="bline{if $required.pref_email eq 1} required{/if}">{_T string="Sender Email:"}</label>
					<input type="text" name="pref_email" id="pref_email" value="{$pref.pref_email}" maxlength="100" size="30"/>
				</p>
				<p>
					<label for="pref_email_reply_to" class="bline{if $required.pref_email_reply_to eq 1} required{/if}">{_T string="Reply-To Email:"}</label>
					<input type="text" name="pref_email_reply_to" id="pref_email_reply_to" value="{$pref.pref_email_reply_to}" maxlength="100" size="30"/>
					<span class="exemple">{_T string="Leave empty to use Sender Email as reply address"}</span>
				</p>
				<p>
					<span class="bline{if $required.pref_mail_method eq 1} required{/if}">{_T string="Emailing method:"}</span>
					<input type="radio" name="pref_mail_method" id="no" value="0" {if $pref.pref_mail_method eq 0}checked="checked"{/if}/><label for="no">{_T string="Emailing disabled"}</label><br />
					<input type="radio" name="pref_mail_method" id="php" value="1" {if $pref.pref_mail_method eq 1}checked="checked"{/if}/><label for="php">{_T string="PHP mail() function"}</label><br />
					<input type="radio" name="pref_mail_method" id="smtp" value="2" {if $pref.pref_mail_method eq 2}checked="checked"{/if}/><label for="smtp">{_T string="Using a SMTP server (slower)"}</label>
				</p>
				<p>
					<label for="pref_mail_smtp" class="bline{if $required.pref_mail_smtp eq 1} required{/if}">{_T string="SMTP server:"}</label>
					<input type="text" name="pref_mail_smtp" id="pref_mail_smtp" value="{$pref.pref_mail_smtp}" maxlength="100" size="30"/>
				</p>
			</fieldset>

			<fieldset class="cssform">
				<legend>{_T string="Label generation parameters:"}</legend>
				<p>
					<label for="pref_etiq_marges_v" class="bline{if $required.pref_etiq_marges_v eq 1} required{/if}">{_T string="Vertical margins:"}</label>
					<input type="text" name="pref_etiq_marges_v" id="pref_etiq_marges_v" value="{$pref.pref_etiq_marges_v}" maxlength="4"/> mm
					<span class="exemple">{_T string="(Integer)"}</span>
				</p>
				<p>
					<label for="pref_etiq_marges_h" class="bline{if $required.pref_etiq_marges_h eq 1} required{/if}">{_T string="Horizontal margins:"}</label>
					<input type="text" name="pref_etiq_marges_h" id="pref_etiq_marges_h" value="{$pref.pref_etiq_marges_h}" maxlength="4"/> mm
					<span class="exemple">{_T string="(Integer)"}</span>
				</p>
				<p>
					<label for="pref_etiq_hspace" class="bline{if $required.pref_etiq_hspace eq 1} required{/if}">{_T string="Horizontal spacing:"}</label>
					<input type="text" name="pref_etiq_hspace" id="pref_etiq_hspace" value="{$pref.pref_etiq_hspace}" maxlength="4"/> mm
					<span class="exemple">{_T string="(Integer)"}</span>
				</p>
				<p>
					<label for="pref_etiq_vspace" class="bline{if $required.pref_etiq_vspace eq 1} required{/if}">{_T string="Vertical spacing:"}</label>
					<input type="text" name="pref_etiq_vspace" id="pref_etiq_vspace" value="{$pref.pref_etiq_vspace}" maxlength="4"/> mm
					<span class="exemple">{_T string="(Integer)"}</span>
				</p>
				<p>
					<label for="pref_etiq_hsize" class="bline{if $required.pref_etiq_hsize eq 1} required{/if}">{_T string="Label width:"}</label>
					<input type="text" name="pref_etiq_hsize" id="pref_etiq_hsize" value="{$pref.pref_etiq_hsize}" maxlength="4"/> mm
					<span class="exemple">{_T string="(Integer)"}</span>
				</p>
				<p>
					<label for="pref_etiq_vsize" class="bline{if $required.pref_etiq_vsize eq 1} required{/if}">{_T string="Label height:"}</label>
					<input type="text" name="pref_etiq_vsize" id="pref_etiq_vsize" value="{$pref.pref_etiq_vsize}" maxlength="4"/> mm
					<span class="exemple">{_T string="(Integer)"}</span>
				</p>
				<p>
					<label for="pref_etiq_cols" class="bline{if $required.pref_etiq_cols eq 1} required{/if}">{_T string="Number of label columns:"}</label>
					<input type="text" name="pref_etiq_cols" id="pref_etiq_cols" value="{$pref.pref_etiq_cols}" maxlength="4"/>
					<span class="exemple">{_T string="(Integer)"}</span>
				</p>
				<p>
					<label for="pref_etiq_rows" class="bline{if $required.pref_etiq_rows eq 1} required{/if}">{_T string="Number of label lines:"}</label>
					<input type="text" name="pref_etiq_rows" id="pref_etiq_rows" value="{$pref.pref_etiq_rows}" maxlength="4"/>
				</p>
				<p>
					<label for="pref_etiq_corps" class="bline{if $required.pref_etiq_corps eq 1} required{/if}">{_T string="Font size:"}</label>
					<input type="text" name="pref_etiq_corps" id="pref_etiq_corps" value="{$pref.pref_etiq_corps}" maxlength="4"/>
					<span class="exemple">{_T string="(Integer)"}</span>
				</p>
			</fieldset>
{if $is_super_admin eq true}
			<fieldset class="cssform">
				<legend>{_T string="Admin account (independant of members):"}</legend>
				<p>
					<label for="pref_admin_login" class="bline{if $required.pref_admin_login eq 1} required{/if}">{_T string="Username:"}</label>
					<input type="text" name="pref_admin_login" id="pref_admin_login" value="{$pref.pref_admin_login}" maxlength="20"/>
				</p>
				<p>
					<label for="pref_admin_pass" class="bline{if $required.pref_admin_pass eq 1} required{/if}">{_T string="Password:"}</label>
					<input type="password" name="pref_admin_pass" id="pref_admin_pass" value="" maxlength="20"/>
				</p>
				<p>
					<label for="pref_admin_pass_check" class="bline{if $required.pref_admin_pass_check eq 1} required{/if}">{_T string="Retype password:"}</label>
					<input type="password" name="pref_admin_pass_check" id="pref_admin_pass_check" value="" maxlength="20"/>
				</p>
			</fieldset>
{/if}
			<input type="hidden" name="valid" value="1"/>
		</div>
		<div class="button-container">
			<input type="submit" class="submit" value="{_T string="Save"}"/>
		</div>
		<p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
		</form>
