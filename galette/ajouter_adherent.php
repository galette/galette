<?php
/** 
 * Saisie d'un adhérent
 *
 * Permet de saisir un nouvel adhérent ou d'en modifier un existant
 * 
 * @package    Galette
 * @author     Frédéric Jaqcuot
 * @copyright  2004 Frédéric Jaqcuot
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GPL License 2.0
 * @version    $Id$
 * @since      Disponible depuis la Release 0.62
 */
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//

/**
 * 
 */
require_once('includes/galette.inc.php');

if ($_SESSION["logged_status"]==0)
{
	header("location: index.php");
	die();
}

include("includes/dynamic_fields.inc.php");

// new or edit
$adherent["id_adh"] = "";
if ($_SESSION["admin_status"]==1)
{
	$adherent["id_adh"] = get_numeric_form_value("id_adh", "");
	// disable some fields
	$disabled = array(
			'id_adh' => 'disabled="disabled"',
			'date_echeance' => 'disabled="disabled"'
		);
}
else
{
	$adherent["id_adh"] = $_SESSION["logged_id_adh"];
	// disable some fields
	$disabled = array(
			'titre_adh' => 'disabled',
			'id_adh' => 'disabled="disabled"',
			'nom_adh' => 'disabled="disabled"',
			'prenom_adh' => 'disabled="disabled"',
			'date_crea_adh' => 'disabled="disabled"',
			'id_statut' => 'disabled="disabled"',
			'activite_adh' => 'disabled="disabled"',
			'bool_exempt_adh' => 'disabled="disabled"',
			'bool_admin_adh' => 'disabled="disabled"',
			'date_echeance' => 'disabled="disabled"',
			'info_adh' => 'disabled="disabled"'
		);
}

// initialize warnings
$error_detected = array();
$warning_detected = array();
$confirm_detected = array();

// flagging required fields
include(WEB_ROOT."classes/required.class.php");
	
$requires = new Required();
$required = $requires->getRequired();

// password required if we create a new member
if ($adherent["id_adh"]=='')
	$required['mdp_adh'] = 1;
else
	unset($required['mdp_adh']);

// flagging required fields invisible to members
if ($_SESSION["admin_status"]==1)
{
	$required['activite_adh'] = 1;
	$required['id_statut'] = 1;
}

// Validation
if (isset($_POST["id_adh"]))
{
	$update_string = '';
	$insert_string_fields = '';
	$insert_string_values = '';

	$adherent['dyn'] = extract_posted_dynamic_fields($DB, $_POST, $disabled);

	// checking posted values for 'regular' fields
	$fields = &$DB->MetaColumns(PREFIX_DB."adherents");
	while (list($key, $properties) = each($fields))
	{
		$key = strtolower($key);
		if (isset($_POST[$key]))
			$value = trim($_POST[$key]);
		else
			$value = '';
		// if the field is enabled, check it
		if (!isset($disabled[$key]))
		{
			// fill up the adherent structure
			$adherent[$key] = htmlentities(stripslashes($value),ENT_QUOTES);

			// now, check validity
			if ($value != "")
				switch ($key)
				{
					// dates
					case 'date_crea_adh':
					case 'ddn_adh':
						if (ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $value, $array_jours))
						{
							if (checkdate($array_jours[2],$array_jours[1],$array_jours[3]))
								$value = $DB->DBDate($array_jours[3].'-'.$array_jours[2].'-'.$array_jours[1]);
							else
								$error_detected[] = _T("- Non valid date!");
						}
						else
							$error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!");
						break;
					case 'email_adh':
					case 'msn_adh':
						if (!is_valid_email($value))
						        $error_detected[] = _T("- Non-valid E-Mail address!")." (".$key.")";
						break;
					case 'url_adh':
						if (!is_valid_web_url($value))
							$error_detected[] = _T("- Non-valid Website address! Maybe you've skipped the http:// ?");
						elseif ($value=='http://')
							$value = '';
						break;
					case 'login_adh':
						if (strlen($value)<4) {
							$error_detected[] = _T("- The username must be composed of at least 4 characters!");
						} else {
							//check if login does not contain the @ character
							if ( strpos($value,'@') != FALSE ) {
								$error_detected[] = _T("- The username cannot contain the @ character");
							}	else	{
								//check if login is already taken
								$requete = "SELECT id_adh
										FROM ".PREFIX_DB."adherents
										WHERE login_adh=". $DB->qstr($value, get_magic_quotes_gpc());
								if ($adherent['id_adh'] != '')
									$requete .= " AND id_adh!=" . $DB->qstr($adherent['id_adh'], get_magic_quotes_gpc());
								$result = &$DB->Execute($requete);
								if (!$result->EOF || $value==PREF_ADMIN_LOGIN)
									$error_detected[] = _T("- This username is already used by another member !");
							}
						}
						break;
					case 'mdp_adh':
						if (isset($_POST['mdp_adh2']))
						if ($_POST['mdp_adh2']==$value)
						{
							if (strlen($value)<4)
								$error_detected[] = _T("- The password must be of at least 4 characters!");
							else
								$value = md5($value);
						}
						else
							$error_detected[] = _T("- The passwords don't match!");
				}

				switch($key)
				{
					case 'date_crea_adh':
					case 'ddn_adh':
						// dates already quoted
						if ($value=='')
							$value='null';
						break;
					default:
						$value = $DB->qstr($value, get_magic_quotes_gpc());
				}

				if ($key=='mdp_adh' && $_POST['mdp_adh']=='')
				{	
					// don't update
				}
				else
				{
					$update_string .= ", ".$key."=".$value;
					$insert_string_fields .= ", ".$key;
					$insert_string_values .= ", ".$value;
				}
		}
	}
		
	// missing relations
	if (isset($adherent["mail_confirm"]))
	{
		if (!isset($adherent["email_adh"]))
			$error_detected[] = _T("- You can't send a confirmation by email if the member hasn't got an address!");
		elseif ($adherent["email_adh"]=="")
			$error_detected[] = _T("- You can't send a confirmation by email if the member hasn't got an address!");
	}

	// missing required fields?
	while (list($key,$val) = each($required))
	{
		if (!isset($disabled[$key]) && (!isset($adherent[$key]) || trim($adherent[$key])==''))
			$error_detected[] = _T("- Mandatory field empty.")." ($key)";
	}

	if (count($error_detected)==0)
	{
		if ($adherent["id_adh"] == "")
		{
			$requete = "INSERT INTO ".PREFIX_DB."adherents
			(" . substr($insert_string_fields,1) . ")
			VALUES (" . substr($insert_string_values,1) . ")";
			if (!$DB->Execute($requete))
				print substr($insert_string_values,1).": ".$DB->ErrorMsg();
			$adherent['id_adh'] = get_last_auto_increment($DB, PREFIX_DB."adherents", "id_adh");
			// to allow the string to be extracted for translation
			$foo = _T("Member card added");

			// logging
			//nom_adh and prenom_adh is not sent when form is used by a simple user
			//dblog('Member card updated:',strtoupper($_POST["nom_adh"]).' '.$_POST["prenom_adh"], $requete);
			dblog('Member card updated:',strtoupper($_POST["login_adh"]),$requete);
		}
		else
		{
			$requete = "UPDATE ".PREFIX_DB."adherents
				SET " . substr($update_string,1) . "
				WHERE id_adh=" . $adherent['id_adh'];
			$DB->Execute($requete);

			// to allow the string to be extracted for translation
			$foo = _T("Member card updated:");

			// logging
			//nom_adh and prenom_adh is not sent when form is used by a simple user
			//dblog('Member card updated:',strtoupper($_POST["nom_adh"]).' '.$_POST["prenom_adh"], $requete);
			dblog('Member card updated:',strtoupper($_POST["login_adh"]), $requete);
		}

		// picture upload
		if (isset($_FILES['photo']))
			if ($_FILES['photo']['tmp_name'] !='')
				if (is_uploaded_file($_FILES['photo']['tmp_name']))
					if (!picture::store($adherent['id_adh'], $_FILES['photo']['tmp_name'], $_FILES['photo']['name']))
						$error_detected[] = _T("- Only .jpg, .gif and .png files are allowed.");
        
		if (isset($_POST['del_photo']))
			if (!picture::delete($adherent['id_adh']))
				$error_detected[] = _T("Delete failed");

		if (isset($_POST["mail_confirm"]))
			if ($_POST["mail_confirm"]=="1")
				if (isset($adherent['email_adh']) && $adherent['email_adh']!="")
				{
					$mail_subject = _T("Your Galette identifiers");
					$mail_text =  _T("Hello,")."\n";
					$mail_text .= "\n";
					$mail_text .= _T("You've just been subscribed on the members management system of the association.")."\n";
					$mail_text .= _T("It is now possible to follow in real time the state of your subscription")."\n";
					$mail_text .= _T("and to update your preferences from the web interface.")."\n";
					$mail_text .= "\n";
					$mail_text .= _T("Please login at this address:")."\n";
					$mail_text .= "http://".$_SERVER["SERVER_NAME"].dirname($_SERVER["REQUEST_URI"])."\n";
					$mail_text .= "\n";
					$mail_text .= _T("Username:")." ".custom_html_entity_decode($adherent['login_adh'])."\n";
					$mail_text .= _T("Password:")." ".custom_html_entity_decode($adherent['mdp_adh'])."\n";
					$mail_text .= "\n";
					$mail_text .= _T("See you soon!")."\n";
					$mail_text .= "\n";
					$mail_text .= _T("(this mail was sent automatically)")."\n";
					$mail_result = custom_mail($adherent['email_adh'],$mail_subject,$mail_text);
					//TODO: duplicate piece of code with mailing_adherent
					if( $mail_result == 1) {
						dblog("Send subscription mail to :".$_POST["email_adh"], $requete);
						$warning_detected[] = _T("Password sent. Login:")." \"" . $adherent['login_adh'] . "\"";
						//$password_sent = true;
					}else{
						switch ($mail_result) {
							case 2 :
								dblog("Email sent is disabled in the preferences. Ask galette admin.");
								$error_detected[] = _T("Email sent is disabled in the preferences. Ask galette admin");
								break;
							case 3 :
								dblog("A problem happened while sending password for account:"." \"" . $_POST["email_adh"] . "\"");
								$error_detected[] = _T("A problem happened while sending password for account:")." \"" . $_POST["email_adh"] . "\"";
								break;
							case 4 :
								dblog("The mail server filled in the preferences cannot be reached. Ask Galette admin");
								$error_detected[] = _T("The mail server filled in the preferences cannot be reached. Ask Galette admin");
								break;
							case 5 :
								dblog("**IMPORTANT** There was a probably breaking attempt when sending mail to :"." \"" . $email_adh . "\"");
								$error_detected[] = _T("**IMPORTANT** There was a probably breaking attempt when sending mail to :")." \"" . $email_adh . "\"";
								break;
							default :
								dblog("A problem happened while sending password for account:"." \"" . $_POST["email_adh"] . "\"");
								$error_detected[] = _T("A problem happened while sending password for account:")." \"" . $_POST["email_adh"] . "\"";
								break;
						}
					}
				}else{
					$error_detected[] = _T("Sent mail is checked but there is no email address")." \"" . $_POST["login_adh"] . "\"";
				}

				// dynamic fields
				set_all_dynamic_fields($DB, 'adh', $adherent['id_adh'], $adherent['dyn']);

				// deadline
				$date_fin = get_echeance($DB, $adherent['id_adh']);
				if ($date_fin!="")
					$date_fin_update = $DB->DBDate($date_fin[2].'-'.$date_fin[1].'-'.$date_fin[0]);
				else
					$date_fin_update = "NULL";
				$requete = "UPDATE ".PREFIX_DB."adherents
						SET date_echeance=".$date_fin_update."
						WHERE id_adh=" . $adherent['id_adh'];
				$DB->Execute($requete);
	
				if (!isset($_POST['id_adh']))
					header('location: ajouter_contribution.php?id_adh='.$adherent['id_adh']);
				elseif (!isset($_POST['del_photo']) && (count($error_detected)==0))
					header('location: voir_adherent.php?id_adh='.$adherent['id_adh']);
			}
	}
	else
	{
		if ($adherent["id_adh"] == "")
		{
			// initialiser la structure adhérent à vide (nouvelle fiche)
			$adherent["id_statut"] = "4";
			$adherent["titre_adh"] = "1";
			$adherent["date_crea_adh"] =date("d/m/Y");
			//annoying
			//$adherent["url_adh"] = "http://";
			$adherent["url_adh"] = "";
			$adherent["mdp_adh"] = makeRandomPassword(7);
			$adherent["pref_lang"] = PREF_LANG;
			$adherent["activite_adh"] = "1";
		}
		else
		{
			// initialize adherent structure with database values
			$sql =  "SELECT * ".
				"FROM ".PREFIX_DB."adherents ".
				"WHERE id_adh=".$adherent["id_adh"];
			$result = &$DB->Execute($sql);
			if ($result->EOF)
				header("location: index.php");
			else
			{
				#annoying
				// url_adh is a specific case
				//if ($result->fields['url_adh']=='')
				//	$result->fields['url_adh'] = 'http://';

				// plain info
				$adherent = $result->fields;

				// reformat dates
				$adherent['date_crea_adh'] = date_db2text($adherent['date_crea_adh']);
				$adherent['ddn_adh'] = date_db2text($adherent['ddn_adh']);

				// dynamic fields
				$adherent['dyn'] = get_dynamic_fields($DB, 'adh', $adherent["id_adh"], false);

				// Correct html
				foreach($adherent as $field => $data) {
				if(is_string($adherent[$field])) { $adherent[$field] = htmlentities($data); }
				}
			}
	}
}

// picture data
if ($adherent["id_adh"]!='') 
	$picture = new picture($adherent["id_adh"]);
else
	$picture = new picture();
if ($picture->hasPicture())
	$adherent["has_picture"]=1;
else
	$adherent["has_picture"]=0;
$adherent['picture_height'] = $picture->getOptimalHeight();
$adherent['picture_width'] = $picture->getOptimalWidth();

// - declare dynamic fields for display
$disabled['dyn'] = array();
if (!isset($adherent['dyn']))
	$adherent['dyn'] = array();

$dynamic_fields = prepare_dynamic_fields_for_display($DB, 'adh', $_SESSION["admin_status"], $adherent['dyn'], $disabled['dyn'], 1);
// template variable declaration
$tpl->assign("required",$required);
$tpl->assign("disabled",$disabled);
$tpl->assign("data",$adherent);
$tpl->assign("time",time());
$tpl->assign("dynamic_fields",$dynamic_fields);
$tpl->assign("error_detected",$error_detected);
$tpl->assign("warning_detected",$warning_detected);
$tpl->assign("languages", $i18n->getList());

// pseudo random int
$tpl->assign("time",time());

// genre
$tpl->assign('radio_titres', array(
		3 => _T("Miss"),
		2 => _T("Mrs"),
		1 => _T("Mister"),
		4 => _T("Society")));

// states
$requete = "SELECT * FROM ".PREFIX_DB."statuts ORDER BY priorite_statut";
$result = &$DB->Execute($requete);
while (!$result->EOF)
{
	$statuts[$result->fields["id_statut"]] = _T($result->fields["libelle_statut"]);
	$result->MoveNext();
}
$result->Close();
$tpl->assign("statuts",$statuts);

// page generation
$content = $tpl->fetch("ajouter_adherent.tpl");
$tpl->assign("content",$content);
$tpl->display("page.tpl");
?>
