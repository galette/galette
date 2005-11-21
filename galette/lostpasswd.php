<?php
/* lostpasswd.php
 * - Lost password
 * Copyright (c) 2004 Stéphane Salès
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

	include("includes/config.inc.php");
	include(WEB_ROOT."includes/database.inc.php");
	include(WEB_ROOT."includes/functions.inc.php");
	include(WEB_ROOT."includes/session.inc.php");
  include_once(WEB_ROOT."includes/i18n.inc.php");
  include_once(WEB_ROOT."includes/smarty.inc.php");

	// initialize warnings
	$error_detected = array();
	$warning_detected = array();
  global $_POST, $_GET, $pref_lang;
  if (isset($_POST["pref_lang"])) $pref_lang=$_POST["pref_lang"];
  if (isset($_GET["pref_lang"])) $pref_lang=$_GET["pref_lang"];
  if (!isset($pref_lang)) $pref_lang=PREF_LANG;


	function isEmail($login) {
		if( empty($login) ) {
			$GLOBALS["error_detected"] = _T("empty login");
		} else {
			$req = "SELECT email_adh
				FROM ".PREFIX_DB."adherents
				WHERE login_adh=".txt_sqls($login);
			$result = &$GLOBALS["DB"]->Execute($req);

			if ($result->EOF) {
				$GLOBALS["error_detected"] = _T("this login doesn't exist");
				dblog(_T("Nonexistent login sent via the lost password form. Login:")." \"" . $login ."\"");
			}else{
				$email=$result->fields[0];
				if( empty($email) ) {
					$GLOBALS["error_detected"] = _T("This account doesn't have a valid email address. Please contact an administrator.");
					dblog(_T("Someone asked to recover his password but had no email. Login:")." \"" . $login . "\"");
				}else
				return $email;
			}
		}
	}

	//if( isset($_POST["login"]) ) {}
	// Validation
	if (isset($_POST['valid']) && $_POST['valid'] == "1") {
		$login_adh=$_POST['login'];
		$email_adh=isEmail($login_adh);

		//send the password
		if(	$email_adh!="" )
		{
			$req = "SELECT mdp_adh from ".PREFIX_DB."adherents where login_adh=".txt_sqls($login_adh);
			$result = &$DB->Execute($req);
			if (!$result->EOF)
				$mdp_adh = $result->fields[0];
			$mail_subject = _T("Your Galette identifiers");
			$mail_text =  _T("Hello,")."\n";
			$mail_text .= "\n";
			$mail_text .= _T("Someone (probably you) asked to recover your password.")."\n";
			$mail_text .= "\n";
			$mail_text .= _T("Please login at this address:")."\n";
			$mail_text .= HTTP."://".$_SERVER["SERVER_NAME"].dirname($_SERVER["REQUEST_URI"])."\n";
			$mail_text .= "\n";
			$mail_text .= _T("Username:")." ".custom_html_entity_decode($login_adh, ENT_QUOTES)."\n";
			$mail_text .= _T("Password:")." ".custom_html_entity_decode($mdp_adh, ENT_QUOTES)."\n";
			$mail_text .= "\n";
			$mail_text .= _T("See you soon!")."\n";
			$mail_text .= "\n";
			$mail_text .= _T("(this mail was sent automatically)")."\n";
			$mail_headers = "From: ".PREF_EMAIL_NOM." <".PREF_EMAIL.">\n";
			if(  mail($email_adh,$mail_subject,$mail_text, $mail_headers) ) {
				dblog(_T("Password sent. Login:")." \"" . $login_adh . "\"");
				$warning_detected = _T("Password sent. Login:")." \"" . $login_adh . "\"";
				$password_sent = true;
			}else{
				dblog(_T("A problem happened while sending password for account:")." \"" . $login_adh . "\"");
				$warning_detected = _T("A problem happened while sending password for account:")." \"" . $login_adh . "\"";
			}
		}
	}

	$tpl->assign("error_detected",$error_detected);
	$tpl->assign("warning_detected",$warning_detected);

  // display page
  $tpl->assign("languages",drapeaux());
	$tpl->display("lostpasswd.tpl");
?>
