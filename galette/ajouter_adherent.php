<?php

// Copyright © 2004 Frédéric Jaqcuot
// Copyright © 2007-2009 Johan Cwiklinski
//
// This file is part of Galette (http://galette.tuxfamily.org).
//
// Galette is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Galette is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Galette. If not, see <http://www.gnu.org/licenses/>.

/** 
 * Saisie d'un adhérent
 *
 * Permet de saisir un nouvel adhérent ou d'en modifier un existant
 * 
 * @package    Galette
 *
 * @author     Frédéric Jaqcuot
 * @copyright  2004 Frédéric Jaqcuot
 * @copyright  2007-2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.62
 */

require_once('includes/galette.inc.php');

if ( !$login->isLogged() )
{
	header("location: index.php");
	die();
}

require_once('classes/adherent.class.php');
require_once('classes/status.class.php');
require_once('classes/mailing.class.php');
include("includes/dynamic_fields.inc.php");
include(WEB_ROOT . 'classes/texts.class.php');

// new or edit
$adherent['id_adh'] = '';
if ( $login->isAdmin() )
{
	$adherent["id_adh"] = get_numeric_form_value("id_adh", "");
	$id = get_numeric_form_value("id_adh", "");
	$member = new Adherent();
	if($id)
		$member->load($adherent["id_adh"]);
	
	// disable some fields
	$disabled = array(
		'id_adh' => 'disabled="disabled"',
		'date_echeance' => 'disabled="disabled"'
	);
	if( $preferences->pref_mail_method == Mailing::METHOD_DISABLED ){
		$disabled['send_mail'] = 'disabled="disabled"';
	}

}
else
{
	$adherent["id_adh"] = $login->id;
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
require_once(WEB_ROOT . 'classes/required.class.php');
	
$requires = new Required();
$required = $requires->getRequired();

// password required if we create a new member
if ($adherent["id_adh"]=='')
	$required['mdp_adh'] = 1;
else
	unset($required['mdp_adh']);

// flagging required fields invisible to members
if ( $login->isAdmin() )
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
			$adherent[$key] = stripslashes($value);

			// now, check validity
			if ($value != "")
				switch ($key)
				{
					// dates
					case 'date_crea_adh':
					case 'ddn_adh':
						if (preg_match("@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@", $value, $array_jours))
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
								if (!$result->EOF || $value == $preferences->pref_admin_login)
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
	if (isset($adherent["mail_confirm"])) {
		if( $preferences->pref_mail_method > Mailing::METHOD_DISABLED )
			if (!isset($adherent["email_adh"]) || $adherent['email_adh'] == '')
				$error_detected[] = _T("- You can't send a confirmation by email if the member hasn't got an address!");
	}

	// missing required fields?
	while (list($key,$val) = each($required)) {
		if (!isset($disabled[$key]) && (!isset($adherent[$key]) || trim($adherent[$key])==''))
			$error_detected[] = _T("- Mandatory field empty.")." ($key)";
	}

	if (count($error_detected)==0) {
		if ($adherent["id_adh"] == "") {
			$requete = "INSERT INTO ".PREFIX_DB."adherents
			(" . substr($insert_string_fields,1) . ")
			VALUES (" . substr($insert_string_values,1) . ")";
			if (!$DB->Execute($requete))
				print substr($insert_string_values,1).": ".$DB->ErrorMsg();
			$adherent['id_adh'] = get_last_auto_increment($DB, PREFIX_DB."adherents", "id_adh");
			// to allow the string to be extracted for translation
			$foo = _T("Member card added");

			//Send email to admin if preference checked
			if ($preferences->pref_bool_mailadh) {
				$texts = new texts();
				$mtxt = $texts->getTexts('newadh', $preferences->pref_lang);
				$mtxt->tsubject = str_replace("{NAME_ADH}", custom_html_entity_decode($adherent['nom_adh']), $mtxt->tsubject);
				$mtxt->tsubject = str_replace("{SURNAME_ADH}", custom_html_entity_decode($adherent['prenom_adh']), $mtxt->tsubject);
				$mtxt->tbody = str_replace("{NAME_ADH}", custom_html_entity_decode($adherent['nom_adh']), $mtxt->tbody);
				$mtxt->tbody = str_replace("{SURNAME_ADH}", custom_html_entity_decode($adherent['prenom_adh']), $mtxt->tbody);
				$mtxt->tbody = str_replace("{LOGIN}", custom_html_entity_decode($adherent['login_adh']), $mtxt->tbody);
				$mail_result = custom_mail($preferences->pref_email_newadh, $mtxt->tsubject,$mtxt->tbody);
				unset ($texts);
				if( $mail_result != 1) {
					$hist->add("A problem happened while sending email to admin for account:"." \"" . $_POST["email_adh"] . "\"");
					$error_detected[] = _T("A problem happened while sending email to admin for account:")." \"" . $_POST["email_adh"] . "\"";
				}
			}
	
			// logging
			//nom_adh and prenom_adh is not sent when form is used by a simple user
			//$hist->add('Member card updated:',strtoupper($_POST["nom_adh"]).' '.$_POST["prenom_adh"], $requete);
			$hist->add('Member card added:',strtoupper($_POST["login_adh"]),$requete);
		} else {
			$requete = "UPDATE ".PREFIX_DB."adherents
				SET " . substr($update_string,1) . "
				WHERE id_adh=" . $adherent['id_adh'];
			$DB->Execute($requete);

			// to allow the string to be extracted for translation
			$foo = _T("Member card updated:");

			// logging
			//nom_adh and prenom_adh is not sent when form is used by a simple user
			//$hist->add('Member card updated:',strtoupper($_POST["nom_adh"]).' '.$_POST["prenom_adh"], $requete);
			$hist->add('Member card updated:',strtoupper($_POST["login_adh"]), $requete);
		}

		// picture upload
		if (isset($_FILES['photo']))
			if ($_FILES['photo']['tmp_name'] !='')
				if (is_uploaded_file($_FILES['photo']['tmp_name']))
					if (!$member->picture->store($adherent['id_adh'], $_FILES['photo']))
						$error_detected[] = _T("- Only .jpg, .gif and .png files are allowed.");
        
		if (isset($_POST['del_photo']))
			if (!$member->picture->delete($adherent['id_adh']))
				$error_detected[] = _T("Delete failed");

		if (isset($_POST["mail_confirm"])){
			if ($_POST["mail_confirm"]=="1" && $preferences->pref_mail_method > Mailing::METHOD_DISABLED) {
				if (isset($adherent['email_adh']) && $adherent['email_adh']!="") {
					// Get email text in database
					$texts = new texts();
					$mtxt = $texts->getTexts("sub", $preferences->pref_lang);
					// Replace Tokens
					$mtxt->tbody = str_replace("{NAME}", $preferences->pref_nom, $mtxt->tbody);
					$mtxt->tbody = str_replace("{LOGIN_URI}", "http://".$_SERVER["SERVER_NAME"].dirname($_SERVER["REQUEST_URI"]), $mtxt->tbody);
					$mtxt->tbody = str_replace("{LOGIN}", custom_html_entity_decode($adherent['login_adh']), $mtxt->tbody);
					$mtxt->tbody = str_replace("{PASSWORD}", custom_html_entity_decode($adherent['mdp_adh']), $mtxt->tbody);
					$mail_result = custom_mail($adherent['email_adh'], $mtxt->tsubject, $mtxt->tbody);
					//TODO: duplicate piece of code with mailing_adherent
					unset ($texts);
					if( $mail_result == 1) {
						$hist->add("Send subscription mail to :".$_POST["email_adh"], $requete);
						$warning_detected[] = _T("Password sent. Login:")." \"" . $adherent['login_adh'] . "\"";
						//$password_sent = true;
					}else{
						switch ($mail_result) {
							case 2 :
								$hist->add("Email sent is disabled in the preferences. Ask galette admin.");
								$error_detected[] = _T("Email sent is disabled in the preferences. Ask galette admin");
								break;
							case 3 :
								$hist->add("A problem happened while sending password for account:"." \"" . $_POST["email_adh"] . "\"");
								$error_detected[] = _T("A problem happened while sending password for account:")." \"" . $_POST["email_adh"] . "\"";
								break;
							case 4 :
								$hist->add("The mail server filled in the preferences cannot be reached. Ask Galette admin");
								$error_detected[] = _T("The mail server filled in the preferences cannot be reached. Ask Galette admin");
								break;
							case 5 :
								$hist->add("**IMPORTANT** There was a probably breaking attempt when sending mail to :"." \"" . $email_adh . "\"");
								$error_detected[] = _T("**IMPORTANT** There was a probably breaking attempt when sending mail to :")." \"" . $email_adh . "\"";
								break;
							default :
								$hist->add("A problem happened while sending password for account:"." \"" . $_POST["email_adh"] . "\"");
								$error_detected[] = _T("A problem happened while sending password for account:")." \"" . $_POST["email_adh"] . "\"";
								break;
						}
					}
				} else {
					$error_detected[] = _T("Sent mail is checked but there is no email address")." \"" . $_POST["login_adh"] . "\"";
				}
			} elseif($_POST["mail_confirm"] == '1' && $preferences->pref_mail_method == Mailing::METHOD_DISABLED){
				//if mail has been disabled in the preferences, we should not be here ; we do not throw an error, just a simple warning that will be show later
				$_SESSION['galette']['mail_warning'] = _T("You asked Galette to send a confirmation mail to the member, but mail has been disabled in the preferences.");
			}
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

// - declare dynamic fields for display
$disabled['dyn'] = array();
if (!isset($adherent['dyn']))
	$adherent['dyn'] = array();

$dynamic_fields = prepare_dynamic_fields_for_display($DB, 'adh', $adherent['dyn'], $disabled['dyn'], 1);
// template variable declaration
$tpl->assign("required",$required);
$tpl->assign("disabled",$disabled);
$tpl->assign("data",$adherent);
$tpl->assign('member', $member);
$tpl->assign("dynamic_fields",$dynamic_fields);
$tpl->assign("error_detected",$error_detected);
$tpl->assign("warning_detected",$warning_detected);
$tpl->assign("languages", $i18n->getList());
$tpl->assign('require_calendar', true);

// pseudo random int
$tpl->assign("time",time());

// genre
$tpl->assign('radio_titres', Politeness::getList());

//Status
$statuts = Status::getList();
$tpl->assign("statuts",$statuts);

// page generation
$content = $tpl->fetch("ajouter_adherent.tpl");
$tpl->assign("content",$content);
$tpl->display("page.tpl");
?>
