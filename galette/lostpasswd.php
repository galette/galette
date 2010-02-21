<?php
/* lostpasswd.php
 * - Lost password
 * Copyright (c) 2004 Stéphane Salès
 * Copyright (c) 2007-2010 Johan Cwiklinski <johan@x-tnd.be>
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

	include("config/config.inc.php");
	include(WEB_ROOT."includes/database.inc.php");
	include(WEB_ROOT."includes/functions.inc.php");
	include(WEB_ROOT."includes/session.inc.php");
  include_once(WEB_ROOT."includes/i18n.inc.php");
  include_once(WEB_ROOT."includes/smarty.inc.php");

	// initialize warnings
	$error_detected = array();
	$warning_detected = array();


	function isEmail($login) {
		if( empty($login) ) {
			$GLOBALS["error_detected"] = _T("empty login");
		} else {
			$req = "SELECT email_adh
				FROM ".PREFIX_DB."adherents
				WHERE login_adh=" . $GLOBALS['DB']->qstr($login, get_magic_quotes_gpc());
			$result = &$GLOBALS["DB"]->Execute($req);

			if ($result->EOF) {
				$GLOBALS["error_detected"] = _T("this login doesn't exist");
				dblog("Nonexistent login sent via the lost password form. Login:"." \"" . $login ."\"");
			}else{
				$email=$result->fields[0];
				if( empty($email) ) {
					$GLOBALS["error_detected"] = _T("This account doesn't have a valid email address. Please contact an administrator.");
					dblog("Someone asked to recover his password but had no email. Login:"." \"" . $login . "\"");
				}else
				return $email;
			}
		}
	}

	// Validation
	if (isset($_POST['valid']) && $_POST['valid'] == "1") {
		$login_adh=$_POST['login'];
		//if field contain the character @ we consider that is an email
		if ( strpos($login_adh,'@') !== FALSE ) {
			$query = "SELECT login_adh from ".PREFIX_DB."adherents where email_adh=".$DB->qstr($login_adh, get_magic_quotes_gpc());
			$result = &$DB->Execute($query);
			$login_adh = $result->fields[0];
		}
		$email_adh=isEmail($login_adh);

		//send the password
		if(	$email_adh!="" )
		{
			$query = "SELECT id_adh from ".PREFIX_DB."adherents where login_adh=".$DB->qstr($login_adh, get_magic_quotes_gpc());
			$result = &$DB->Execute($query);
			if ($result->EOF) {
				$warning_detected = _T("There is  no password for user :")." \"" . $login_adh . "\"";
				//TODO need to clean die here
      } else {
				$id_adh = $result->fields[0];
      }
			//make temp password
			$tmp_passwd = makeRandomPassword(7);
			$hash = md5($tmp_passwd);
			//delete old tmp_passwd
			$query = "DELETE FROM ".PREFIX_DB."tmppasswds";
			$query .= " WHERE id_adh = $id_adh ";
			if (!$DB->Execute($query))
				$warning_detected = _T("delete failed");
			//insert temp passwd in database
			$query = "INSERT INTO ".PREFIX_DB."tmppasswds";
			$query .= " (id_adh, tmp_passwd, date_crea_tmp_passwd)";
			$query .= " VALUES($id_adh, '$hash', ".$DB->DBTimeStamp(time()).")";
			if (!$DB->Execute($query))
				$warning_detected = _T("There was a database error when inserting data");
				//$warning_detected = $DB->ErrorMsg();
			//prepare mail and send it
			$mail_subject = _T("Your Galette identifiers");
			$mail_text =  _T("Hello,")."\n";
			$mail_text .= "\n";
			$mail_text .= _T("Someone (probably you) asked to recover your password.")."\n";
			$mail_text .= "\n";
			$mail_text .= _T("Please login at this address to set your new password :")."\n";
			$mail_text .= HTTP."://".$_SERVER["SERVER_NAME"].dirname($_SERVER["REQUEST_URI"])."/change_passwd.php?hash=$hash\n";
			$mail_text .= "\n";
			//$mail_text .= _T("Username:")." ".custom_html_entity_decode($login_adh, ENT_QUOTES)."\n";
			//$mail_text .= _T("Temporary password:")." ".custom_html_entity_decode($hash, ENT_QUOTES)."\n";
			//$mail_text .= "\n";
			$mail_text .= _T("See you soon!")."\n";
			$mail_text .= "\n";
			$mail_text .= _T("(this mail was sent automatically)")."\n";
			//$mail_headers = "From: ".PREF_EMAIL_NOM." <".PREF_EMAIL.">\n";
      $mail_result = custom_mail($email_adh,$mail_subject,$mail_text);
			if( $mail_result == 1) {
				dblog("Password sent. Login:"." \"" . $login_adh . "\"");
				$warning_detected = _T("Password sent. Login:")." \"" . $login_adh . "\"";
				//$password_sent = true;
			} else {
        switch ($mail_result) {
          case 2 :
            dblog("Email sent is disabled in the preferences");
            $warning_detected = _T("Email sent is disabled in the preferences. Ask galette admin");
            break;
          case 3 :
            dblog("A problem happened while sending password for account:"." \"" . $login_adh . "\"");
            $warning_detected = _T("A problem happened while sending password for account:")." \"" . $login_adh . "\"";
            break;
          case 4 :
            dblog("The mail server filled in the preferences cannot be reached");
            $warning_detected = _T("The mail server filled in the preferences cannot be reached. Ask Galette admin");
            break;
					case 5 :
						dblog("**IMPORTANT** There was a probably breaking attempt when sending mail to :"." \"" . $email_adh . "\"");
						$error_detected[] = _T("**IMPORTANT** There was a probably breaking attempt when sending mail to :")." \"" . $email_adh . "\"";
						break;
          default :
            dblog("A problem happened while sending password for account:"." \"" . $login_adh . "\"");
            $warning_detected = _T("A problem happened while sending password for account:")." \"" . $login_adh . "\"";
            break;
        }
			}
		}
	}

	$tpl->assign("error_detected",$error_detected);
	$tpl->assign("warning_detected",$warning_detected);

  // display page
	$tpl->display("lostpasswd.tpl");
?>
