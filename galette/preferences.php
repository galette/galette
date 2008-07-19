<?php

// Copyright © 2004 Frédéric Jaqcuot
// Copyright © 2007-2008 Johan Cwiklinski
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
 * Choix des préférences de galette
 *
 * Les préférences se répartissent selon les groupes suivants:
 * - Association: Paramètres généraux de l'association tel
 *   que l'adresse, le logo etc...
 * - Galette: Préférences d'utilisation du logiciel
 * - Courriel: Adresse d'expéditeur et de réponse des courriers
 * - Etiquettes : Définition du format des étiquettes
 * - Cartes de membres : Choix du format des cartes
 * - Compte d'administration : Paramètres d'accès
 * 
 * @package    Galette
 *
 * @author     Frédéric Jaqcuot
 * @copyright  2004 Frédéric Jaqcuot
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.62
 */

require_once('includes/galette.inc.php');

if( !$login->isLogged() ){
	header("location: index.php");
	die();
}
if( !$login->isAdmin() ){
	header("location: voir_adherent.php");
	die();
}

// initialize warnings
$error_detected = array();
$warning_detected = array();
$confirm_detected = array();

// flagging required fields
$required = array(
	'pref_nom' => 1,
	'pref_lang' => 1,
	'pref_numrows' => 1,
	'pref_log' => 1,
	'pref_etiq_marges_v' => 1,
	'pref_etiq_marges_h' => 1,
	'pref_etiq_hspace' => 1,
	'pref_etiq_vspace' => 1,
	'pref_etiq_hsize' => 1,
	'pref_etiq_vsize' => 1,
	'pref_etiq_cols' => 1,
	'pref_etiq_rows' => 1,
	'pref_etiq_corps' => 1,
	'pref_card_abrev' => 1,
	'pref_card_strip' => 1,
	'pref_card_marges_v' => 1,
	'pref_card_marges_h' => 1,
	'pref_card_hspace' => 1,
	'pref_card_vspace' => 1,
	'pref_admin_login' => 1
);

// Validation
if (isset($_POST['valid']) && $_POST['valid'] == "1"){
	// verification de champs
	$insert_values = array();

	// obtain fields
	$requete = "SELECT nom_pref FROM ".PREFIX_DB."preferences";
	$result=$DB->Execute($requete);
	while (!$result->EOF){
		$fieldname = $result->fields['nom_pref'];

		if (isset($_POST[$fieldname]))
			$value=trim($_POST[$fieldname]);
		else
			$value="";

		// now, check validity
		if ($value != '')
			switch ($fieldname){
				case 'pref_email':
					if (!is_valid_email($value))
						$error_detected[] = _T("- Non-valid E-Mail address!");
						break;
				case 'pref_admin_login':
					if (strlen($value)<4)
						$error_detected[] = _T("- The username must be composed of at least 4 characters!");
					else{
						//check if login is already taken
						$requete2 = "SELECT id_adh FROM ".PREFIX_DB."adherents WHERE login_adh=". $DB->qstr($value, get_magic_quotes_gpc());
						$result2 = &$DB->Execute($requete2);
						if (!$result2->EOF)
							$error_detected[] = _T("- This username is already used by another member !");
					}
					break;
				case 'pref_numrows':
					if (!is_numeric($value) || $value <0)
						$error_detected[] = "<li>"._T("- The numbers and measures have to be integers!")."</li>";
					break;
				case 'pref_etiq_marges_h':
				case 'pref_etiq_marges_v':
				case 'pref_etiq_hspace':
				case 'pref_etiq_vspace':
				case 'pref_etiq_hsize':
				case 'pref_etiq_vsize':
				case 'pref_etiq_cols':
				case 'pref_etiq_rows':
				case 'pref_etiq_corps':
				case 'pref_card_marges_v':
				case 'pref_card_marges_h':
				case 'pref_card_hspace':
				case 'pref_card_vspace':
					// prevent division by zero
					if ($fieldname=='pref_numrows' && $value=='0')
						$value = '10';
						if (!is_numeric($value) || $value <0)
							$error_detected[] = _T("- The numbers and measures have to be integers!");
					break;
				case 'pref_card_tcol':
					// Set strip text color to white
					if (! eregi("#([0-9A-F]{6})", $value))
						$value = '#FFFFFF';
					break;
				case 'pref_card_scol':
				case 'pref_card_bcol':
				case 'pref_card_hcol':
					// Set strip background colors to black
					if (! eregi("#([0-9A-F]{6})", $value))
						$value = '#000000';
					break;
				case 'pref_admin_pass':
					if (strlen($value)<4)
						$error_detected[] = _T("- The password must be of at least 4 characters!");
					break;
				case 'pref_membership_ext':
					if (!is_numeric($value) || $value < 0)
						$error_detected[] = _T("- Invalid number of months of membership extension.");
					break;
				case 'pref_beg_membership':
					$beg_membership = split("/",$value);
					if (count($beg_membership) != 2)
						$error_detected[] = _T("- Invalid format of beginning of membership.");
					else {
						$now = getdate();
						if (!checkdate($beg_membership[1], $beg_membership[0], $now['year']))
							$error_detected[] = _T("- Invalid date for beginning of membership.");
					}
					break;
			}

			// fill up pref structure (after $value's modifications)
			$pref[$fieldname] = htmlentities(stripslashes($value),ENT_QUOTES);

			$insert_values[$fieldname] = $value;
			$result->MoveNext();
	}
	$result->Close();

	// missing relations
	if (isset($insert_values['pref_mail_method'])){
		if ($insert_values['pref_mail_method']==2 || $insert_values['pref_mail_method']==1){
			if ($insert_values['pref_mail_method']==2){
				if (!isset($insert_values['pref_mail_smtp']) || $insert_values['pref_mail_smtp']=='')
					$error_detected[] = _T("- You must indicate the SMTP server you want to use!");
			}
			if (!isset($insert_values['pref_email_nom']) || $insert_values['pref_email_nom']=='')
				$error_detected[] = _T("- You must indicate a sender name for emails!");
			if (!isset($insert_values['pref_email']) || $insert_values['pref_email']=='')
				$error_detected[] = _T("- You must indicate an email address Galette should use to send emails!");
		}
	}

	if (isset($insert_values['pref_beg_membership']) && $insert_values['pref_beg_membership'] != '' && isset($insert_values['pref_membership_ext']) && $insert_values['pref_membership_ext'] != ''){
		$error_detected[] = _T("- Default membership extention and beginning of membership are mutually exclusive.");
	}

	// missing required fields?
	while (list($key,$val) = each($required)){
		if (!isset($pref[$key]))
			$error_detected[] = _T("- Mandatory field empty.")." ".$key;
		elseif (isset($pref[$key]))
			if (trim($pref[$key])=='')
				$error_detected[] = _T("- Mandatory field empty.")." ".$key;
	}

	// Check (and crypt) passwords
	if(strcmp($insert_values['pref_admin_pass'],$_POST['pref_admin_pass_check']) != 0) {
		$error_detected[] = _T("Passwords mismatch");
	} else {
		$insert_values['pref_admin_pass'] = md5($insert_values['pref_admin_pass']);
	}

	if (count($error_detected)==0){
		// update preferences
		while (list($champ,$valeur)=each($insert_values)){
			if(($champ == "pref_admin_pass" && $_POST['pref_admin_pass']!= '') | ($champ != "pref_admin_pass")) {
				$valeur = stripslashes($valeur);
				$requete = "UPDATE ".PREFIX_DB."preferences SET val_pref=".$DB->qstr($valeur)." WHERE nom_pref=".$DB->qstr($champ).";\n";
				$DB->Execute($requete);
			}
		}

		// picture upload
		if (isset($_FILES['logo']) )
			if ($_FILES['logo']['tmp_name'] !='' ) {
				$pic =& new picture(0);
				if (is_uploaded_file($_FILES['logo']['tmp_name']))
					if (! $pic->store(0, $_FILES['logo']['tmp_name'], $_FILES['logo']['name'])) {
						$error_detected[] = _T("- Only .jpg, .gif and .png files are allowed.");
					} else {
						$_SESSION["customLogoFormat"] = $pic->FORMAT;
						$_SESSION["customLogo"] = true;
					}
			}

			if (isset($_POST['del_logo']))
				if (!picture::delete(0))
					$error_detected[] = _T("Delete failed");
				else
					$_SESSION["customLogo"] = false;

		// Card logo upload
		if (isset($_FILES['card_logo']) )
			if ($_FILES['card_logo']['tmp_name'] !='' ) {
				$cardpic =& new picture(999999);
				if (is_uploaded_file($_FILES['card_logo']['tmp_name']))
					if (! $cardpic->store(999999, $_FILES['card_logo']['tmp_name'], $_FILES['card_logo']['name'])) {
						$error_detected[] = _T("- Only .jpg, .gif and .png files are allowed.");
					} else {
						$_SESSION["customCardLogoFormat"] = $cardpic->FORMAT;
						$_SESSION["customCardLogo"] = true;
					}
			}

		if (isset($_POST['del_card_logo']))
			if (!picture::delete(999999))
	    $error_detected[] = _T("Delete failed");
	  else
	    $_SESSION["customCardLogo"] = false;

   }
} else {
     // collect data
     $requete = "SELECT *
			    FROM ".PREFIX_DB."preferences";
     $result = &$DB->Execute($requete);
     if ($result->EOF)
       header("location: index.php");
     else  {
		 while (!$result->EOF) {
		     $pref[$result->fields['nom_pref']] = htmlentities(stripslashes(addslashes($result->fields['val_pref'])), ENT_QUOTES);
		     $result->MoveNext();
		 }
     }
     $result->Close();
}

// Card logo data
$cardlogo = new picture(999999);
if ($cardlogo->hasPicture())
	$pref["has_card_logo"]=1;
else
	$pref["has_card_logo"]=0;

$pref['card_logo_height'] = $cardlogo->getOptimalHeight();
$pref['card_logo_width'] = $cardlogo->getOptimalWidth();

// logo data
$picture = new picture(0);
if ($picture->hasPicture())
	$pref['has_logo']=1;
else
	$pref['has_logo']=0;

$pref['picture_height'] = $picture->getOptimalHeight();
$pref['picture_width'] = $picture->getOptimalWidth();
$tpl->assign('time', time());
$tpl->assign('pref', $pref);
$tpl->assign('pref_numrows_options', array(
	10 => '10',
	20 => '20',
	50 => '50',
	100 => '100',
	0 => _T('All'))
);

$tpl->assign('required', $required);
$tpl->assign('languages', $i18n->getList());
$tpl->assign('error_detected', $error_detected);
$tpl->assign('warning_detected', $warning_detected);
$tpl->assign('color_picker', true);
// page generation
$content = $tpl->fetch('preferences.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
