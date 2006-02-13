<?
/* mailing_adherents.php
 * - Mailing
 * Copyright (c) 2005 Frédéric Jaqcuot
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
	include(WEB_ROOT."includes/session.inc.php");

	if ($_SESSION["logged_status"]==0)
	{
		header("location: index.php");
		die();
	}
	if ($_SESSION["admin_status"]==0)
	{
		header("location: voir_adherent.php");
		die();
	}
	
	include(WEB_ROOT."includes/functions.inc.php");
	include(WEB_ROOT."includes/i18n.inc.php");
	include(WEB_ROOT."includes/smarty.inc.php");

	$error_detected = array();
	$warning_detected = array();
  $data = array();

	$mailing_adh = array();
	if (isset($_SESSION['galette']['mailing']))
		$mailing_adh = $_SESSION['galette']['mailing'];
	else
		die();

	$reachable_members = array();
	$unreachable_members = array();

	$member_id_string = "";
	foreach ($mailing_adh as $id_adh)
		$member_id_string .= $id_adh.",";
	$member_id_string = substr($member_id_string,0,-1);
	$sql = "SELECT id_adh, email_adh
					FROM ".PREFIX_DB."adherents
					WHERE id_adh IN ($member_id_string)";
	$result_members = &$DB->Execute($sql);
	while (!$result_members->EOF)
	{
		if ($result_members->fields[1]=='')
			$unreachable_members[]=$result_members->fields[0];
		else
			$reachable_members[]=$result_members->fields[0];
		$result_members->MoveNext();
	}

	if (isset($_POST["mailing_done"]))
		header("location: gestion_adherents.php");

	$etape = 0;
	if (isset($_POST["mailing_go"]) || isset($_POST["mailing_reset"]) || isset($_POST["mailing_confirm"]))
	{
		if ($_POST['mailing_objet']=="")
			$error_detected[] = _T("Please type an object for the message.");
		else
			$data['mailing_objet']=htmlspecialchars($_POST['mailing_objet'],ENT_QUOTES);

		if ($_POST['mailing_corps']=="")
			$error_detected[] = _T("Please enter a message.");
		else
			$data['mailing_corps'] = $_POST['mailing_corps'];

		if (isset($_POST['mailing_html']))
			$data['mailing_html']=$_POST['mailing_html'];
		else
			$data['mailing_html']=0;

		$data['mailing_corps_display']=htmlspecialchars($data['mailing_corps'],ENT_QUOTES);

		if (count($error_detected)==0 && !isset($_POST["mailing_reset"]))
			$etape = 1;
	}

	if (isset($_POST["mailing_confirm"]) && count($error_detected)==0)
	{
		$etape = 2;
		$member_id_string = "";
		foreach ($reachable_members as $id_adh)
			$member_id_string .= $id_adh.",";
		$member_id_string = substr($member_id_string,0,-1);
		// TODO : interpret cutom tags to include personal data in mails
		$sql = "SELECT id_adh, email_adh, nom_adh, prenom_adh
						FROM ".PREFIX_DB."adherents
						WHERE id_adh IN ($member_id_string)";
		$result_members = &$DB->Execute($sql);
		if ($data['mailing_html']==0)
			$content_type = "text/plain";
		else
			$content_type = "text/html";
		$mail_result = "";
		$email_adh = "";
		while (!$result_members->EOF)
		{
			$mail_result = custom_mail($result_members->fields[1],
																	$data['mailing_objet'],
																	$data['mailing_corps'],
																	$content_type);
			if( $mail_result == 1) {
				$email_adh = $result_members->fields[1];
				dblog(_T("Send mail to :")." \"" . $email_adh . "\"", $sql);
				$warning_detected[] = _T("Mail sent to :")." \"" . $email_adh . "\"";
			} else {
      switch ($mail_result) {
        case 2 :
          dblog(_T("Email sent is desactived in the preferences. Ask galette admin."));
          $error_detected[] = _T("Email sent is desactived in the preferences. Ask galette admin");
          break;
        case 3 :
          dblog(_T("A problem happened while sending mail to :")." \"" . $email_adh . "\"");
          $error_detected[] = _T("A problem happened while sending mail to :")." \"" . $email_adh . "\"";
          break;
        case 4 :
          dblog(_T("The server mail filled in the preferences cannot be reached. Ask Galette admin"));
          $error_detected[] = _T("The server mail filled in the preferences cannot be reached. Ask Galette admin");
          break;
        case 5 :
          dblog(_T("**IMPORTANT** There was a probably breaking attempt when sending mail to :")." \"" . $email_adh . "\"");
          $error_detected[] = _T("**IMPORTANT** There was a probably breaking attempt when sending mail to :")." \"" . $email_adh . "\"";
          break;
        default :
          dblog(_T("A problem happened while sending mail to :")." \"" . $email_adh . "\"");
          $error_detected[] = _T("A problem happened while sending mail to :")." \"" . $email_adh . "\"";
          break;
      }
    }
			$result_members->MoveNext();
		}
	}

	$_SESSION['galette']['labels']=$unreachable_members;

	$nb_reachable_members = count($reachable_members);
	$nb_unreachable_members = count($unreachable_members);

	$tpl->assign("warning_detected",$warning_detected);
	$tpl->assign("error_detected",$error_detected);
	$tpl->assign("nb_reachable_members",$nb_reachable_members);
	$tpl->assign("nb_unreachable_members",$nb_unreachable_members);
	$tpl->assign("data",$data);
	$tpl->assign("etape",$etape);
	$content = $tpl->fetch("mailing_adherents.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
