<? // -*- Mode: PHP; tab-width: 2; indent-tabs-mode: nil; c-basic-offset: 4 -*-
/* ajouter_adherent.php
 * - Saisie d'un adhérent
 * Copyright (c) 2004 Frédéric Jaqcuot
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
	include(WEB_ROOT."includes/functions.inc.php"); 
        include(WEB_ROOT."includes/i18n.inc.php");
	include(WEB_ROOT."includes/smarty.inc.php");
        include(WEB_ROOT."includes/categories.inc.php");
        
	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");

	// new or edit
	$adherent["id_adh"] = "";
	if ($_SESSION["admin_status"]==1)
	{
		if (isset($_GET["id_adh"]))
			if (is_numeric($_GET["id_adh"]))
				$adherent["id_adh"] = $_GET["id_adh"];
		if (isset($_POST["id_adh"]))
			if (is_numeric($_POST["id_adh"]))
				$adherent["id_adh"] = $_POST["id_adh"];
		// disable some fields
		$disabled = array(
				'id_adh' => 'disabled',
				'date_echeance' => 'disabled'
			);
	}
	else
	{
		$adherent["id_adh"] = $_SESSION["logged_id_adh"];
		// disable some fields
		$disabled = array(
				'titre_adh' => 'disabled',
				'id_adh' => 'disabled',
				'nom_adh' => 'disabled',
				'prenom_adh' => 'disabled',
				'date_crea_adh' => 'disabled',
				'id_statut' => 'disabled',
				'activite_adh' => 'disabled',
				'bool_exempt_adh' => 'disabled',
				'bool_admin_adh' => 'disabled',
				'date_echeance' => 'disabled',
				'info_adh' => 'disabled'
			);
	}
	
	// - declare dynamic fields for display
	$requete = "SELECT * ".
			"FROM ".PREFIX_DB."info_categories ".
			"ORDER BY index_cat";
	$result = &$DB->Execute($requete);
	while (!$result->EOF)
	{
		// disable admin fields when logged as member
		if ($_SESSION["admin_status"]!=1 && $result->fields['perm']==$perm_admin)
			$disabled['dyn'][$result->fields['id_cat']] = 'disabled';	
		$dynamic_fields[] = $result->fields;
		$result->MoveNext();
	}
	$result->Close();
	// TODO : admin dynamic fields disabled for member

	// initialize warnings
	$error_detected = array();
	$warning_detected = array();
	$confirm_detected = array();

	// flagging required fields
	$required = array(
			'titre_adh' => 1,
			'nom_adh' => 1,
			'login_adh' => 1,
			'mdp_adh' => 1,
			'adresse_adh' => 1,
			'cp_adh' => 1,
			'ville_adh' => 1);
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
										
		// fill up the adherent structure with posted values
		// for dynamic fields
		while (list($key,$value) = each($_POST))
		{
			// if the field is enabled, check it
			if (!isset($disabled[$key]))
			{
				if (substr($key,0,11)=='info_field_')
				{
					// initialize adherent structure with dynamic fields posted values
					list ($id_cat, $index_info) = explode ('_', substr($key,11));
					if (is_numeric($id_cat) && is_numeric($index_info))
					$adherent['dyn'][$id_cat][$index_info] = $value;
				}
			}
		}
		
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
					case 'ddn_adh':
					case 'date_crea_adh':
						if (ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $value, $array_jours))
						{
							if (checkdate($array_jours[2],$array_jours[1],$array_jours[3]))
								$value = $DB->DBDate(mktime(0,0,0,$array_jours[2],$array_jours[1],$array_jours[3]));
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
						if (strlen($value)<4)
							$error_detected[] = _T("- The username must be composed of at least 4 characters!");
						else
						{
							//check if login is already taken
							$requete = "SELECT id_adh
									FROM ".PREFIX_DB."adherents
									WHERE login_adh=". $DB->qstr($value, true);
							if ($adherent['id_adh'] != '')
								$requete .= " AND id_adh!=" . $DB->qstr($adherent['id_adh'], true);
							$result = &$DB->Execute($requete);
							if (!$result->EOF || $value==PREF_ADMIN_LOGIN)
								$error_detected[] = _T("- This username is already used by another member !");
						}
						break;
					case 'mdp_adh':
						if (strlen($value)<4)
							$error_detected[] = _T("- The password must be of at least 4 characters!");
						break;
				}
				
				// dates already quoted
				if (($key!='ddn_adh' && $key!='date_crea_adh') || $value=='')
					$value = $DB->qstr($value);
				
				$update_string .= ", ".$key."=".$value;
				$insert_string_fields .= ", ".$key;
				$insert_string_values .= ", ".$value;
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
			if (!isset($adherent[$key]) && !isset($disabled[$key]))
				$error_detected[] = _T("- Mandatory field empty.")." ($key)";
			elseif (isset($adherent[$key]) && !isset($disabled[$key]))
				if (trim($adherent[$key])=='')
					$error_detected[] = _T("- Mandatory field empty.")." ($key)";
		}

		if (count($error_detected)==0)
		{
			if ($adherent["id_adh"] == "")
			{
				$requete = "INSERT INTO ".PREFIX_DB."adherents
				(" . substr($insert_string_fields,1) . ")
				VALUES (" . substr($insert_string_values,1) . ")";
				$DB->Execute($requete);
				$adherent['id_adh'] = $DB->Insert_ID();

				// to allow the string to be extracted for translation
				$foo = _T("Member card added");

				// logging
				dblog('Member card added',strtoupper($_POST['nom_adh']).' '.$_POST["prenom_adh"], $requete);
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
                                dblog('Member card updated:',strtoupper($_POST["nom_adh"]).' '.$_POST["prenom_adh"], $requete);
			}
			
                        if (isset($_POST["mail_confirm"]))
                                if ($_POST["mail_confirm"]=="1")
                                        if ($adherent['email_adh']!="")
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
                                                custom_mail ($adherent['email_adh'],$mail_subject,$mail_text);
                                        }

			// dynamic fields
			while (list($category,$content)=each($adherent['dyn']))
				while (list($index,$value)=each($content))
					set_adh_info ($DB, $adherent['id_adh'], $category, $index, $value);

			// deadline
			$date_fin = get_echeance($DB, $adherent['id_adh']);
			if ($date_fin!="")
				$date_fin_update = $DB->DBDate(mktime(0,0,0,$date_fin[1],$date_fin[0],$date_fin[2]));
			else
				$date_fin_update = "NULL";
			$requete = "UPDATE ".PREFIX_DB."adherents
					SET date_echeance=".$date_fin_update."
					WHERE id_adh=" . $adherent['id_adh'];
			$DB->Execute($requete);
			
			header ('location: ajouter_contribution.php?id_adh='.$adherent['id_adh']);
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
			$adherent["url_adh"] = "http://";
			$adherent["mdp_adh"] = makeRandomPassword(7);
			$adherent["pref_lang"] = PREF_LANG;
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
				// url_adh is a specific case
				if ($result->fields['url_adh']=='')
					$result->fields['url_adh'] = 'http://';
															
				// plain info
				$adherent = $result->fields;

				// reformat dates
				if ($adherent['ddn_adh'] != '')
				{
					list($a,$m,$j)=split("-",$adherent['ddn_adh']);
					$adherent['ddn_adh']="$j/$m/$a";
				}
				if ($adherent['date_crea_adh'] != '')
				{
					list($a,$m,$j)=split("-",$adherent['date_crea_adh']);
					$adherent['date_crea_adh']="$j/$m/$a";
				}

				// dynamic fields
				$sql =  "SELECT id_cat, index_info, val_info ".
					"FROM ".PREFIX_DB."adh_info ".
					"WHERE id_adh=".$adherent["id_adh"];
				$result = &$DB->Execute($sql);
				while (!$result->EOF)
				{
					$adherent['dyn'][$result->fields['id_cat']][$result->fields['index_info']] = $result->fields['val_info'];
					$result->MoveNext();
				}
			}

		}
	}

	// picture available ?
	if ($adherent["id_adh"]!="")
	{
		$sql =  "SELECT id_adh".
			"FROM ".PREFIX_DB."pictures ".
			"WHERE id_adh=".$adherent["id_adh"];
		$result = &$DB->Execute($sql);
		if (!$result->EOF)
			$adherent["has_picture"]=1;
		else
			$adherent["has_picture"]=0;
	}
	else
		$adherent["has_picture"]=0;

	// template variable declaration
	$tpl->assign("required",$required);
	$tpl->assign("disabled",$disabled);
	$tpl->assign("adherent",$adherent);
	$tpl->assign("dynamic_fields",$dynamic_fields);
	$tpl->assign("error_detected",$error_detected);
	$tpl->assign("warning_detected",$warning_detected);
	$tpl->assign("languages",drapeaux());

	// pseudo random int
	$tpl->assign("time",time());

	// genre
	$tpl->assign('radio_titres', array(
			3 => _T("Miss"),
			2 => _T("Mrs"),
			1 => _T("Mister")));

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
