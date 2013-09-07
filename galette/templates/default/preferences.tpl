        <form action="preferences.php" method="post" enctype="multipart/form-data" class="tabbed">
        <div id="prefs_tabs">
            <ul>
                <li><a href="#general">{_T string="General"}</a></li>
                <li><a href="#social">{_T string="Social networks"}</a></li>
                <li><a href="#parameters">{_T string="Parameters"}</a></li>
                <li><a href="#mail">{_T string="E-Mail"}</a></li>
                <li><a href="#labels">{_T string="Labels"}</a></li>
                <li><a href="#cards">{_T string="Cards"}</a></li>
{if $login->isSuperAdmin()}
                <li><a href="#admin">{_T string="Admin"}</a></li>
{/if}
            </ul>
            <fieldset class="cssform" id="general">
                <legend>{_T string="General information"}</legend>
                <p>
                    <label for="pref_nom" class="bline">{_T string="Name of the association:"}</label>
                    <input{if $required.pref_nom eq 1} required{/if} type="text" name="pref_nom" id="pref_nom" value="{$pref.pref_nom}" maxlength="190"/>
                </p>
                <p>
                    <label for="pref_slogan" class="bline tooltip" title="{_T string="Enter here a short description for your association, it will be displayed on the index page and into pages' title."}">{_T string="Association's short description:"}</label>
                    <span class="tip">{_T string="Enter here a short description for your association, it will be displayed on the index page and into pages' title."}</span>
                    <input{if isset($required.pref_slogan) and $required.pref_slogan eq 1} required{/if} type="text" class="large" name="pref_slogan" id="pref_slogan" value="{$pref.pref_slogan}"/>
                </p>
                <p>
                    <label for="logo_picture" class="bline">{_T string="Logo:"}</label>
{if $logo->isCustom()}
                    <img src="{$galette_base_path}picture.php?logo=true&amp;rand={$time}" class="picture" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="{_T string="Current logo"}"/><br/>
                    <label for="del_logo">{_T string="Delete image"}</label><input type="checkbox" name="del_logo" id="del_logo" value="1" /><br />
{/if}
                    <input type="file" name="logo" id="logo_picture"{if $GALETTE_MODE eq 'DEMO'} disabled="disabled"{/if}/>
                </p>
                <p>
                    <label for="pref_adresse" class="bline">{_T string="Address:"}</label>
                    <input{if isset($required.pref_adresse) and $required.pref_adresse eq 1} required{/if} type="text" name="pref_adresse" id="pref_adresse" value="{$pref.pref_adresse}" maxlength="190" class="large"/><br/>
                    <label for="pref_adresse2" class="bline libelle">{_T string="Address:"} {_T string=" (continuation)"}</label>
                    <input{if isset($required.pref_adresse) and $required.pref_adresse eq 1} required{/if} type="text" name="pref_adresse2" id="pref_adresse2" value="{$pref.pref_adresse2}" maxlength="190" class="large"/>
                </p>
                <p>
                    <label for="pref_cp" class="bline">{_T string="Zip Code:"}</label>
                    <input{if isset($required.pref_cp) and $required.pref_cp eq 1} required{/if} type="text" name="pref_cp" id="pref_cp" value="{$pref.pref_cp}" maxlength="10"/>
                </p>
                <p>
                    <label for="pref_ville" class="bline">{_T string="City:"}</label>
                    <input{if isset($required.pref_ville) and $required.pref_ville eq 1} required{/if} type="text" name="pref_ville" id="pref_ville" value="{$pref.pref_ville}" maxlength="100"/>
                </p>
                <p>
                    <label for="pref_pays" class="bline">{_T string="Country:"}</label>
                    <input{if isset($required.pref_pays) and $required.pref_pays eq 1} required{/if} type="text" name="pref_pays" id="pref_pays" value="{$pref.pref_pays}" maxlength="50"/>
                </p>
        <div class="p">
          <span class="bline tooltip" title="{_T string="Use either the adress setted below or select user status to retrieve another adress."}">{_T string="Postal adress:"}</span>
          <span class="tip">{_T string="Use either the adress setted below or select a staff member to retrieve he's adress."}</span>
          <label for="pref_postal_adress_0">{_T string="from preferences"}</label>
          <input type="radio" name="pref_postal_adress" id="pref_postal_adress_0" value="{php}echo Galette\Core\Preferences::POSTAL_ADRESS_FROM_PREFS;{/php}" {if $pref.pref_postal_adress eq constant('Galette\Core\Preferences::POSTAL_ADRESS_FROM_PREFS')}checked="checked"{/if}/>
          <label for="pref_postal_adress_1">{_T string="from a staff user"}</label>
          <input type="radio" name="pref_postal_adress" id="pref_postal_adress_1" value="{php}echo Galette\Core\Preferences::POSTAL_ADRESS_FROM_STAFF;{/php}" {if $pref.pref_postal_adress eq constant('Galette\Core\Preferences::POSTAL_ADRESS_FROM_STAFF')}checked="checked"{/if}/>
          <br/><label for="pref_postal_staff_member">{_T string="Staff member"}</label>
          <select name="pref_postal_staff_member" id="pref_postal_staff_member">
            <option value="-1">{_T string="-- Choose a staff member --"}</option>
          {foreach from=$staff_members item=staff}
            <option value="{$staff->id}"{if $staff->id eq $pref.pref_postal_staff_member} selected="selected"{/if}>{$staff->sname} ({$staff->sstatus})</option>
          {/foreach}
          </select>
        </div>
                <p>
                    <label for="pref_website" class="bline">{_T string="Website:"}</label>
                    <input{if isset($required.pref_website) and $required.pref_website eq 1} required{/if} type="text" name="pref_website" id="pref_website" value="{$pref.pref_website}" maxlength="100"/>
                </p>
            </fieldset>

            <fieldset class="cssform" id="social">
                <legend>{_T string="Social networks"}</legend>
                <p>
                    <label for="pref_googleplus" class="bline">{_T string="Google+"}</label>
                    <input type="text" name="pref_googleplus" id="pref_googleplus" value="{$pref.pref_googleplus}" class="large"/>
                </p>
                <p>
                    <label for="pref_facebook" class="bline">{_T string="Facebook"}</label>
                    <input type="text" name="pref_facebook" id="pref_facebook" value="{$pref.pref_facebook}" class="large"/>
                </p>
                <p>
                    <label for="pref_twitter" class="bline">{_T string="Twitter"}</label>
                    <input type="text" name="pref_twitter" id="pref_twitter" value="{$pref.pref_twitter}" class="large"/>
                </p>
                <p>
                    <label for="pref_linkedin" class="bline">{_T string="LinkedIn"}</label>
                    <input type="text" name="pref_linkedin" id="pref_linkedin" value="{$pref.pref_linkedin}" class="large"/>
                </p>
                <p>
                    <label for="pref_viadeo" class="bline">{_T string="Viadeo"}</label>
                    <input type="text" name="pref_viadeo" id="pref_viadeo" value="{$pref.pref_viadeo}" class="large"/>
                </p>
            </fieldset>

            <fieldset class="cssform" id="parameters">
                <legend>{_T string="Galette's parameters"}</legend>
                <p>
                    <label for="pref_lang" class="bline">{_T string="Default language:"}</label>
                    <select name="pref_lang" id="pref_lang">
{foreach item=langue from=$languages}
                        <option value="{$langue->getID()}" {if $pref.pref_lang eq $langue->getID()}selected="selected"{/if} style="padding-left: 30px; background-image: url({$langue->getFlag()}); background-repeat: no-repeat">{$langue->getName()|ucfirst}</option>
{/foreach}
                    </select>
                </p>
                {*<p>
                    <label for="pref_theme" class="bline">{_T string="Default theme:"}</label>
                    <select name="pref_theme" id="pref_theme">
{foreach item=theme from=$themes}
                        <option value="{$theme}" {if $pref.pref_theme eq $theme}selected="selected"{/if}>{$theme|ucfirst}</option>
{/foreach}
                    </select>
                </p>*}
                <p>
                    <label for="pref_numrows" class="bline">{_T string="Lines / Page:"}</label>
                    <select name="pref_numrows" id="pref_numrows">
                        {html_options options=$pref_numrows_options selected=$pref.pref_numrows}
                    </select>
                </p>
                <p>
                    <label for="pref_log" class="bline">{_T string="Logging level:"}</label>
                    <select name="pref_log" id="pref_log">
                        <option value="0" {if $pref.pref_log eq 0}selected="selected"{/if}>{_T string="Disabled"}</option>
                        <option value="1" {if $pref.pref_log eq 1}selected="selected"{/if}>{_T string="Normal"}</option>
                        <option value="2" {if $pref.pref_log eq 2}selected="selected"{/if}>{_T string="Detailed"}</option>
                    </select>
                </p>
                <p>
                    <label for="pref_membership_ext" class="bline">{_T string="Default membership extension:"}</label>
                    <input type="text" name="pref_membership_ext" id="pref_membership_ext" value="{$pref.pref_membership_ext}" maxlength="2"{if isset($required.pref_membership_ext) and $required.pref_membership_ext eq 1} required{/if}/>
                    <span class="exemple">{_T string="(Months)"}</span>
                </p>
                <p>
                    <label for="pref_beg_membership" class="bline">{_T string="Beginning of membership:"}</label>
                    <input type="text" name="pref_beg_membership" id="pref_beg_membership" value="{$pref.pref_beg_membership}" maxlength="5"{if isset($required.pref_beg_membership) and $required.pref_beg_membership eq 1} required{/if}/>
                    <span class="exemple">{_T string="(dd/mm)"}</span>
                </p>
                <p>
                    <label for="pref_bool_publicpages" class="bline">{_T string="Public pages enabled?"}</label>
                    <input type="checkbox" name="pref_bool_publicpages" id="pref_bool_publicpages" value="1" {if $pref.pref_bool_publicpages} checked="checked"{/if}{if isset($required.pref_bool_publicpages) and $required.pref_bool_publicpages eq 1} required{/if}/>
                </p>
                <p id="publicpages_visibility"{if !$pref.pref_bool_publicpages} class="hidden"{/if}>
                    <label for="pref_publicpages_visibility" class="bline">{_T string="Show public pages for"}</label>
                    <select name="pref_publicpages_visibility" id="pref_publicpages_visibility">
                        <option value="{php}echo Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;{/php}"{if $pref.pref_publicpages_visibility eq constant('Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC')} selected="selected"{/if}>{_T string="Everyone"}</option>
                        <option value="{php}echo Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;{/php}"{if $pref.pref_publicpages_visibility eq constant('Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED')} selected="selected"{/if}>{_T string="Up to date members"}</option>
                        <option value="{php}echo Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE;{/php}"{if $pref.pref_publicpages_visibility eq constant('Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE')} selected="selected"{/if}>{_T string="Admin and staff only"}</option>
                    </select>
                </p>
                <p>
                    <label for="pref_bool_selfsubscribe" class="bline">{_T string="Self subscription enabled?"}</label>
                    <input type="checkbox" name="pref_bool_selfsubscribe" id="pref_bool_selfsubscribe" value="1"{if $pref.pref_bool_selfsubscribe} checked="checked"{/if} {if isset($required.pref_bool_selfsubscribe) and $required.pref_bool_selfsubscribe eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_new_contrib_script" class="bline tooltip" title="{_T string="Enter a script URI that would be called after adding a new contribution."}">{_T string="Post new contribution script URI"}</label>
                    <span class="tip">{_T string="Enter a script URI that would be called after adding a new contribution.<br/>Script URI must be prefixed by one of '<em>galette://</em>' for Galette internal call. '<em>file://</em>' for a direct file call, '<em>get://</em>' or '<em>post://</em>' for HTTP calls (prefix will be replaced by http:// in those cases)."}</span>
                    <input type="text" name="pref_new_contrib_script" id="pref_new_contrib_script" value="{$pref.pref_new_contrib_script}"{if isset($required.pref_new_contrib_script) and $required.pref_new_contrib_script eq 1} required{/if}/>
                </p>
            </fieldset>

            <fieldset class="cssform" id="mail">
                <legend>{_T string="Mail settings"}</legend>
    {if $GALETTE_MODE eq 'DEMO'}
                <div>{_T string="Application runs under demo mode. This functionnality is not enabled, sorry."}</div>
    {else}
                <p>
                    <label for="pref_email_nom" class="bline">{_T string="Sender name:"}</label>
                    <input type="text" name="pref_email_nom" id="pref_email_nom" value="{$pref.pref_email_nom}" maxlength="50"{if isset($required.pref_email_nom) and $required.pref_email_nom eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_email" class="bline">{_T string="Sender Email:"}</label>
                    <input type="text" name="pref_email" id="pref_email" value="{$pref.pref_email}" maxlength="100" size="30"{if isset($required.pref_email) and $required.pref_email eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_email_reply_to" class="bline tooltip" title="{_T string="Leave empty to use Sender Email as reply address"}">{_T string="Reply-To Email:"}</label>
                    <span class="tip">{_T string="Leave empty to use Sender Email as reply address"}</span>
                    <input type="text" name="pref_email_reply_to" id="pref_email_reply_to" value="{$pref.pref_email_reply_to}" maxlength="100" size="30"{if isset($reminder.pref_email_reply_to) and $required.pref_email_reply_to eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_email_newadh" class="bline tooltip" title="{_T string="Recipient of new online registation emails"}">{_T string="Members administrator's Email:"}</label>
                    <span class="tip">{_T string="Recipient of new online registation emails"}</span>
                    <input type="text" name="pref_email_newadh" id="pref_email_newadh" value="{$pref.pref_email_newadh}" maxlength="100" size="30"{if isset($required.pref_email_newadh) and $required.pref_email_newadh eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_bool_mailadh" class="bline tooltip" title="{_T string="Sends an email each time a new member registers online"}">{_T string="Send email to administrators ?"}</label>
                    <span class="tip">{_T string="Sends an email each time a new member registers online"}</span>
                    <input type="checkbox" name="pref_bool_mailadh" id="pref_bool_mailadh" value="1" {if $pref.pref_bool_mailadh eq 1}checked="checked"{/if}{if isset($required.pref_bool_mailadh) and $required.pref_bool_mailadh eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_editor_enabled" class="bline tooltip" title="{_T string="Should HTML editor be activated on page load ?"}">{_T string="Activate HTML editor ?"}</label>
                    <span class="tip">{_T string="Should HTML editor be activated on page load ?"}</span>
                    <input type="checkbox" name="pref_editor_enabled" id="pref_editor_enabled" value="1" {if $pref.pref_editor_enabled eq 1}checked="checked"{/if}{if isset($required.pref_editor_enabled) and $required.pref_editor_enabled eq 1} required{/if}/>
                </p>
                <div class="p">
                    <span class="bline"{if isset($required.pref_mail_method) and $required.pref_mail_method eq 1} required{/if}>{_T string="Emailing method:"}</span>
                    <ul>
                        <li>
                            <input type="radio" name="pref_mail_method" id="no" value="{php}echo Galette\Core\GaletteMail::METHOD_DISABLED;{/php}" {if $pref.pref_mail_method eq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}checked="checked"{/if}/><label for="no">{_T string="Emailing disabled"}</label>
                        </li>
                        <li>
                            <input type="radio" name="pref_mail_method" id="php" value="{php}echo Galette\Core\GaletteMail::METHOD_PHPMAIL;{/php}" {if $pref.pref_mail_method eq constant('Galette\Core\GaletteMail::METHOD_PHPMAIL')}checked="checked"{/if}/><label for="php">{_T string="PHP mail() function"}</label>
                        </li>
                        <li>
                            <input type="radio" name="pref_mail_method" id="smtp" value="{php}echo Galette\Core\GaletteMail::METHOD_SMTP;{/php}" {if $pref.pref_mail_method eq constant('Galette\Core\GaletteMail::METHOD_SMTP')}checked="checked"{/if}/><label for="smtp">{_T string="Using a SMTP server (slower)"}</label>
                        </li>
                        <li>
                            <input type="radio" name="pref_mail_method" id="gmail" value="{php}echo Galette\Core\GaletteMail::METHOD_GMAIL;{/php}" {if $pref.pref_mail_method eq constant('Galette\Core\GaletteMail::METHOD_GMAIL')}checked="checked"{/if}/><label for="gmail">{_T string="Using GMAIL as SMTP server (slower)"}</label>
                        </li>
                        <li>
                            <input type="radio" name="pref_mail_method" id="sendmail" value="{php}echo Galette\Core\GaletteMail::METHOD_SENDMAIL;{/php}" {if $pref.pref_mail_method eq constant('Galette\Core\GaletteMail::METHOD_SENDMAIL')}checked="checked"{/if}/><label for="sendmail">{_T string="Using Sendmail server"}</label>
                        </li>
                        <li>
                            <input type="radio" name="pref_mail_method" id="qmail" value="{php}echo Galette\Core\GaletteMail::METHOD_QMAIL;{/php}" {if $pref.pref_mail_method eq constant('Galette\Core\GaletteMail::METHOD_QMAIL')}checked="checked"{/if}/><label for="qmail">{_T string="Using QMAIL server"}</label>
                        </li>
                    </ul>
                </div>
                <div id="smtp_parameters"{if $pref.pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_SMTP')} style="display: none;"{/if}>
                    <p>
                        <label for="pref_mail_smtp_host" class="bline">{_T string="SMTP server:"}</label>
                        <input type="text" name="pref_mail_smtp_host" id="pref_mail_smtp_host" value="{$pref.pref_mail_smtp_host}" maxlength="100" size="30"/{if isset($required.pref_mail_smtp_host) and $required.pref_mail_smtp_host eq 1} required{/if}>
                    </p>
                    <p>
                        <label for="pref_mail_smtp_port" class="bline">{_T string="SMTP port:"}</label>
                        <input type="text" name="pref_mail_smtp_port" id="pref_mail_smtp_port" value="{$pref.pref_mail_smtp_port}" size="10"{if isset($required.pref_mail_smtp_port) and $required.pref_mail_smtp_port eq 1} required{/if}/>
                    </p>
                    <p>
                        <label for="pref_mail_smtp_auth" class="bline tooltip" title="{_T string="Do you want to use SMTP authentication?"}">{_T string="Use SMTP authentication?"}</label>
                        <span class="tip">{_T string="Would emailing use any SMTP authentication? You'll have to provide username and passwrod below. For GMail, authentication will always be on."}</span>
                        <input type="checkbox" name="pref_mail_smtp_auth" id="pref_mail_smtp_auth" value="1" {if $pref.pref_mail_smtp_auth eq 1}checked="checked"{/if}{if isset($required.pref_mail_smtp_auth) and $required.pref_mail_smtp_auth eq 1} required{/if}/>
                    </p>
                    <p>
                        <label for="pref_mail_smtp_secure" class="bline tooltip" title="{_T string="Do you want to use SMTP authentication?"}">{_T string="Use TLS for SMTP?"}</label>
                        <span class="tip">{_T string="Do you want to use server's TLS capabilities?<br/>For GMail, this will always be on."}</span>
                        <input type="checkbox" name="pref_mail_smtp_secure" id="pref_mail_smtp_secure" value="1" {if $pref.pref_mail_smtp_secure eq 1}checked="checked"{/if}{if isset($required.pref_mail_smtp_secure) and $required.pref_mail_smtp_secure eq 1} required{/if}/>
                    </p>
                </div>
                <div id="smtp_auth"{if $pref.pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_SMTP') && $pref.pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_GMAIL')} style="display: none;"{/if}>
                    <p>
                        <label for="pref_mail_smtp_user" class="bline">{_T string="SMTP (or GMail) user:"}</label>
                        <input type="text" name="pref_mail_smtp_user" id="pref_mail_smtp_user" value="{$pref.pref_mail_smtp_user}" maxlength="100" size="30"{if isset($required.pref_mail_smtp_user) and $required.pref_mail_smtp_user eq 1} required{/if}/>
                    </p>
                    <p>
                        <label for="pref_mail_smtp_password" class="bline">{_T string="SMTP (or GMail) password:"}</label>
                        <input type="password" name="pref_mail_smtp_password" id="pref_mail_smtp_password" value="{$pref.pref_mail_smtp_password}" autocomplete="off" maxlength="100" size="30"{if isset($required.pref_mail_smtp_password) and $required.pref_mail_smtp_password eq 1} required{/if}/>
                    </p>
                </div>
                <p>
                    <label for="pref_mail_sign" class="bline tooltip" title="{_T string="Enter here the text that will be automatically set as signature for all outgoing emails."}">{_T string="Mail signature"}</label>
                    <span class="tip">{_T string="The text that will be automatically set as signature for all outgoing emails.<br/>Variables are quoted with braces, are upper case, and will be replaced automatically.<br/>Refer to the doc to know what variables ara available. "}</span>
                    <textarea name="pref_mail_sign" id="pref_mail_sign">{$pref.pref_mail_sign}</textarea>
                </p>
    {/if}
            </fieldset>

            <fieldset class="cssform" id="labels">
                <legend>{_T string="Label generation parameters"}</legend>
                <p>
                    <label for="pref_etiq_marges_v" class="bline">{_T string="Vertical margins:"}</label>
                    <input type="text" name="pref_etiq_marges_v" id="pref_etiq_marges_v" value="{$pref.pref_etiq_marges_v}" maxlength="4"{if isset($required.pref_etiq_marges_v) and $required.pref_etiq_marges_v eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_etiq_marges_h" class="bline">{_T string="Horizontal margins:"}</label>
                    <input type="text" name="pref_etiq_marges_h" id="pref_etiq_marges_h" value="{$pref.pref_etiq_marges_h}" maxlength="4"{if isset($required.pref_etiq_marges_h) and $required.pref_etiq_marges_h eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_etiq_hspace" class="bline">{_T string="Horizontal spacing:"}</label>
                    <input type="text" name="pref_etiq_hspace" id="pref_etiq_hspace" value="{$pref.pref_etiq_hspace}" maxlength="4"{if isset($required.pref_etiq_hspace) and $required.pref_etiq_hspace eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_etiq_vspace" class="bline">{_T string="Vertical spacing:"}</label>
                    <input type="text" name="pref_etiq_vspace" id="pref_etiq_vspace" value="{$pref.pref_etiq_vspace}" maxlength="4"{if isset($required.pref_etiq_vspace) and $required.pref_etiq_vspace eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_etiq_hsize" class="bline">{_T string="Label width:"}</label>
                    <input type="text" name="pref_etiq_hsize" id="pref_etiq_hsize" value="{$pref.pref_etiq_hsize}" maxlength="4"{if isset($required.pref_etiq_hsize) and $required.pref_etiq_hsize eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_etiq_vsize" class="bline">{_T string="Label height:"}</label>
                    <input type="text" name="pref_etiq_vsize" id="pref_etiq_vsize" value="{$pref.pref_etiq_vsize}" maxlength="4"{if isset($required.pref_etiq_vsize) and $required.pref_etiq_vsize eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_etiq_cols" class="bline">{_T string="Number of label columns:"}</label>
                    <input type="text" name="pref_etiq_cols" id="pref_etiq_cols" value="{$pref.pref_etiq_cols}" maxlength="4"{if isset($required.pref_etiq_cols) and $required.pref_etiq_cols eq 1} required{/if}/>
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_etiq_rows" class="bline">{_T string="Number of label lines:"}</label>
                    <input type="text" name="pref_etiq_rows" id="pref_etiq_rows" value="{$pref.pref_etiq_rows}" maxlength="4"{if isset($required.pref_etiq_rows) and $required.pref_etiq_rows eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_etiq_corps" class="bline">{_T string="Font size:"}</label>
                    <input type="text" name="pref_etiq_corps" id="pref_etiq_corps" value="{$pref.pref_etiq_corps}" maxlength="4"{if isset($required.pref_etiq_corps) and $required.pref_etiq_corps eq 1} required{/if}/>
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
            </fieldset>

            <fieldset class="cssform" id="cards">
                <legend>{_T string="Cards generation parameters"}</legend>
                <p>
                    <label for="pref_card_abrev" class="bline">{_T string="Short Text (Card Center):"}</label>
                    <input type="text" name="pref_card_abrev" id="pref_card_abrev" value="{$pref.pref_card_abrev}" size="10" maxlength="10"{if isset($required.pref_card_abrev) and $required.pref_card_abrev eq 1} required{/if}/>
                    <span class="exemple">{_T string="(10 characters max)"}</span>
                </p>
                <p>
                    <label for="pref_card_strip" class="bline">{_T string="Long Text (Bottom Line):"}</label>
                    <input type="text" name="pref_card_strip" id="pref_card_strip" value="{$pref.pref_card_strip}" size="40" maxlength="65"{if isset($required.pref_card_strip) and $required.pref_card_strip eq 1} required{/if}/>
                    <span class="exemple">{_T string="(65 characters max)"}</span>
                </p>
                <p>
                    <label for="pref_card_tcol" class="bline tooltip" title="{_T string="Hexadecimal color notation: #RRGGBB"}">{_T string="Strip Text Color:"}</label>
                    <span class="tip">{_T string="Hexadecimal color notation: #RRGGBB"}</span>
                    <input type="text" name="pref_card_tcol" id="pref_card_tcol" value="{$pref.pref_card_tcol}" size="7" maxlength="7" class="hex"{if isset($required.pref_card_tcol) and $required.pref_card_tcol eq 1} required{/if}/>
                </p>
                <div class="subtitle">{_T string="Strip Background colors:"} <span class="exemple">{_T string="(Strip color will change according to member's status)"}</span></div>
                <p>
                    <label for="pref_card_scol" class="bline tooltip" title="{_T string="Hexadecimal color notation: #RRGGBB"}">{_T string="Active Member Color:"}</label>
                    <span class="tip">{_T string="Hexadecimal color notation: #RRGGBB"}</span>
                    <input type="text" name="pref_card_scol" id="pref_card_scol" value="{$pref.pref_card_scol}" size="7" maxlength="7" class="hex"{if isset($required.pref_card_scol) and $required.pref_card_scol eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_card_bcol" class="bline tooltip" title="{_T string="Hexadecimal color notation: #RRGGBB"}">{_T string="Board Members Color:"}</label>
                    <span class="tip">{_T string="Hexadecimal color notation: #RRGGBB"}</span>
                    <input type="text" name="pref_card_bcol" id="pref_card_bcol" value="{$pref.pref_card_bcol}" size="7" maxlength="7" class="hex"{if isset($required.pref_card_bcol) and $required.pref_card_bcol eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_card_hcol" class="bline tooltip" title="{_T string="Hexadecimal color notation: #RRGGBB"}">{_T string="Honor Members Color:"}</label>
                    <span class="tip">{_T string="Hexadecimal color notation: #RRGGBB"}</span>
                    <input type="text" name="pref_card_hcol" id="pref_card_hcol" value="{$pref.pref_card_hcol}" size="7" maxlength="7" class="hex"{if isset($required.pref_card_hcol) and $required.pref_card_hcol eq 1}required{/if}/>
                </p>
                <div class="subtitle">&nbsp;</div>
                <p>
                    <label for="card_logo" class="bline"{if isset($required.card_logo) and $required.card_logo eq 1}required{/if}>{_T string="Logo:"}</label>
{if $print_logo->isCustom()}
                    <img src="{$galette_base_path}picture.php?print_logo=true&amp;rand={$time}" class="picture" width="{$print_logo->getOptimalWidth()}" height="{$print_logo->getOptimalHeight()}" alt="{_T string="Current logo for printing"}"/><br/>
                    <label for="del_card_logo">{_T string="Delete image"}</label><input type="checkbox" name="del_card_logo" id="del_card_logo" value="1" /><br />
{/if}
                    <input type="file" name="card_logo" id="card_logo"{if $GALETTE_MODE eq 'DEMO'} disabled="disabled"{/if}/>
                </p>
                <p>
                    <label for="pref_card_self" class="bline">{_T string="Allow members to print card ?"}</label>
                    <input type="checkbox" name="pref_card_self" id="pref_card_self" value="1" {if $pref.pref_card_self eq 1}checked="checked"{/if}{if isset($required.pref_bool_display_title) and $required.pref_bool_display_title eq 1} required{/if}/>
                    <span class="exemple">{_T string="(Members will be able to generate their own member card)"}</span>
                </p>
                <p>
                    <label for="pref_bool_display_title" class="bline">{_T string="Show title ?"}</label>
                    <input type="checkbox" name="pref_bool_display_title" id="pref_bool_display_title" value="1" {if $pref.pref_bool_display_title eq 1}checked="checked"{/if}{if isset($required.pref_bool_display_title) and $required.pref_bool_display_title eq 1} required{/if}/>
                    <span class="exemple">{_T string="(Show or not title in front of name)"}</span>
                </p>
                <p>
                    <label for="pref_card_address" class="bline">{_T string="Address type:"}</label>
                    <select name="pref_card_address" id="pref_card_address">
                        <option value="0" {if $pref.pref_card_address eq 0}selected="selected"{/if}>{_T string="Email"}</option>
                        <option value="1" {if $pref.pref_card_address eq 1}selected="selected"{/if}>{_T string="MSN"}</option>
                        <option value="2" {if $pref.pref_card_address eq 2}selected="selected"{/if}>{_T string="Jabber"}</option>
                        <option value="3" {if $pref.pref_card_address eq 3}selected="selected"{/if}>{_T string="Web Site"}</option>
                        <option value="4" {if $pref.pref_card_address eq 4}selected="selected"{/if}>{_T string="ICQ"}</option>
                        <option value="5" {if $pref.pref_card_address eq 5}selected="selected"{/if}>{_T string="Zip - Town"}</option>
                        <option value="6" {if $pref.pref_card_address eq 6}selected="selected"{/if}>{_T string="Nickname"}</option>
                        <option value="7" {if $pref.pref_card_address eq 7}selected="selected"{/if}>{_T string="Profession"}</option>
                    </select>
                    <span class="exemple">{_T string="(Choose address printed below name)"}</span>
                </p>
                <p>
                    <label for="pref_card_year" class="bline">{_T string="Year:"}</label>
                    <input type="text" name="pref_card_year" id="pref_card_year" value="{$pref.pref_card_year}" maxlength="4"{if isset($required.pref_card_year) and $required.pref_card_year eq 1} required{/if}/>
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_card_marges_v" class="bline">{_T string="Vertical margins:"}</label>
                    <input type="text" name="pref_card_marges_v" id="pref_card_marges_v" value="{$pref.pref_card_marges_v}" maxlength="4"{if isset($required.pref_card_marges_v) and $required.pref_card_marges_v eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_card_marges_h" class="bline">{_T string="Horizontal margins:"}</label>
                    <input type="text" name="pref_card_marges_h" id="pref_card_marges_h" value="{$pref.pref_card_marges_h}" maxlength="4"{if isset($required.pref_card_marges_h) and $required.pref_card_marges_h eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_card_vspace" class="bline">{_T string="Vertical spacing:"}</label>
                    <input type="text" name="pref_card_vspace" id="pref_card_vspace" value="{$pref.pref_card_vspace}" maxlength="4"{if isset($required.pref_card_vspace) and $required.pref_card_vspace eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
                <p>
                    <label for="pref_card_hspace" class="bline">{_T string="Horizontal spacing:"}</label>
                    <input type="text" name="pref_card_hspace" id="pref_card_hspace" value="{$pref.pref_card_hspace}" maxlength="4"{if isset($required.pref_card_hspace) and $required.pref_card_hspace eq 1} required{/if}/> mm
                    <span class="exemple">{_T string="(Integer)"}</span>
                </p>
            </fieldset>

{if $login->isSuperAdmin()}
            <fieldset class="cssform" id="admin">
                <legend>{_T string="Admin account (independant of members)"}</legend>
    {if $GALETTE_MODE eq 'DEMO'}
                <div>{_T string="Application runs under demo mode. This functionnality is not enabled, sorry."}</div>
    {else}
                <p>
                    <label for="pref_admin_login" class="bline">{_T string="Username:"}</label>
                    <input type="text" name="pref_admin_login" id="pref_admin_login" value="{$pref.pref_admin_login}" maxlength="20"{if isset($required.pref_admin_login) and $required.pref_admin_login eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_admin_pass" class="bline">{_T string="Password:"}</label>
                    <input type="password" name="pref_admin_pass" id="pref_admin_pass" value="" maxlength="20" autocomplete="off"{if isset($required.pref_admin_pass) and $required.pref_admin_pass eq 1} required{/if}/>
                </p>
                <p>
                    <label for="pref_admin_pass_check" class="bline">{_T string="Retype password:"}</label>
                    <input type="password" name="pref_admin_pass_check" id="pref_admin_pass_check" value="" maxlength="20"{if isset($required.pref_admin_pass_check) and $required.pref_admin_pass_check eq 1} required{/if}/>
                </p>
    {/if}
            </fieldset>
{/if}
        </div>
        <div class="button-container">
            <input type="hidden" name="valid" value="1"/>
            <input type="hidden" name="pref_theme" value="default"/>
            <input type="submit" id="btnsave" value="{_T string="Save"}"/>
        </div>
        <p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
        </form>
        <script type="text/javascript">
            $('#prefs_tabs').tabs();

            $('#no,#php,#qmail').click(function(){
                $('#smtp_parameters,#smtp_auth').hide();
            });
            $('#smtp,#gmail').click(function(){
                $('#smtp_parameters,#smtp_auth').show();
            });
            $('#gmail').click(function(){
                $('#smtp_parameters').hide();
                $('#smtp_auth').show();
            });


            $(function(){
                //for color pickers
                // hex inputs
                $('input.hex')
                    .validHex()
                    .keyup(function() {
                        $(this).validHex();
                    })
                    .click(function(){
                        $(this).addClass('focus');
                        $('#picker').remove();
                        $('div.picker-on').removeClass('picker-on');
                        $(this).after('<div id="picker"></div>').parent().addClass('picker-on');
                        $('#picker').farbtastic(this);
                        return false;
                    })
                    .wrap('<div class="hasPicker"></div>')
                    .applyFarbtastic();

                //general app click cleanup
                $('body').click(function() {
                    $('div.picker-on').removeClass('picker-on');
                    $('#picker').remove();
                    $('input.focus, select.focus').removeClass('focus');
                });

                $('#pref_bool_publicpages').change(function(){
                    $('#publicpages_visibility').toggleClass('hidden');
                });
            });

            //color pickers setup (sets bg color of inputs)
            $.fn.applyFarbtastic = function() {
                return this.each(function() {
                    $('<div/>').farbtastic(this).remove();
                });
            };

            // validation for hex inputs
            $.fn.validHex = function() {

                return this.each(function() {

                    var value = $(this).val();
                    value = value.replace(/[^#a-fA-F0-9]/g, ''); // non [#a-f0-9]
                    if(value.match(/#/g) && value.match(/#/g).length > 1) value = value.replace(/#/g, ''); // ##
                    if(value.indexOf('#') == -1) value = '#'+value; // no #
                    if(value.length > 7) value = value.substr(0,7); // too many chars

                    $(this).val(value);

                });

            };
        </script>
