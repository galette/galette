		<h1 class="titre">{_T("Settings")}</h1>
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
			<table>
				<tr>
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
				</tr>         
				<tr>
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
				</tr>
				<tr>
					<th colspan="2" class="separator">{_T("Label generation parameters:")}</th>
				</tr>
				<tr>
					<th {if $required.pref_etiq_marges eq 1}style="color: #FF0000;"{/if}>{_T("Margins:")}</th> 
					<td>
						<input type="text" name="pref_etiq_marges" value="{$pref.pref_etiq_marges}" maxlength="4"/> mm 
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
				</tr>
				<tr>
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
						<input type="text" name="pref_admin_pass" value="{$pref.pref_admin_pass}" maxlength="20"/>
					</td>
				</tr>
			</table>
		</div>
		<input type="hidden" name="valid" value="1"/>
		<br/>
		<div class="button-container">
			<input type="submit" class="submit" value="{_T("Save")}"/>
		</div>
		<p>{_T("NB : The mandatory fields are in")} <span style="color: #FF0000">{_T("red")}</span></p>
		</form>
