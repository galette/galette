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
								$value = $DB->DBDate(mktime(0,0,0,$array_jours[3],$array_jours[2],$array_jours[1]));
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
				$update_string .= ",".$key."=".$DB->qstr($value);
				$insert_string_fields .= ",".$key;
				$insert_string_values .= ",".$DB->qstr($value);
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
				$error_detected[] = _T("- Mandatory field empty.")." ".$key;
			elseif (isset($adherent[$key]) && !isset($disabled[$key]))
				if (trim($adherent[$key])=='')
					$error_detected[] = _T("- Mandatory field empty.")." ".$key;
		}

		if (count($error_detected)==0)
		{
			if ($adherent["id_adh"] == "")
			{
				$requete = "INSERT INTO ".PREFIX_DB."adherents
				(" . substr($insert_string_fields,1) . ")
				VALUES (" . substr($insert_string_values,1) . ")";
				$DB->Execute($requete);
				//dblog(_T("Member card added:")." ".strtoupper($_POST["nom_adh"])." ".$_POST["prenom_adh"], $requete);

				$adherent['id_adh'] = $DB->Insert_ID();
			}
			else
			{
                                $requete = "UPDATE ".PREFIX_DB."adherents
                                            SET " . substr($update_string,1) . "
                                            WHERE id_adh=" . $adherent['id_adh'];
                                $DB->Execute($requete);
                                //dblog(_T("Member card update:")." ".strtoupper($_POST["nom_adh"])." ".$_POST["prenom_adh"], $requete);

                                $date_fin = get_echeance($DB, $adherent['id_adh']);
                                if ($date_fin!="")
                                        $date_fin_update = $DB->DBDate(mktime(0,0,0,$date_fin[1],$date_fin[0],$date_fin[2]));
                                else
                                        $date_fin_update = "NULL";
                                $requete = "UPDATE ".PREFIX_DB."adherents
                                            SET date_echeance=".$date_fin_update."
                                            WHERE id_adh=" . $adherent['id_adh'];
				$DB->Execute($requete);
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

	// page genaration
	$content = $tpl->fetch("ajouter_adherent.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
			




















































/*	
































	$adherent["id_adh"] = $id_adh;
	$adherent["id_statut"] = "";
	$adherent["nom_adh"] = "";
	$adherent["prenom_adh"] = "";
	$adherent["pseudo_adh"] = "";
	$adherent["titre_adh"] = "";
	$adherent["ddn_adh"] = "";
	$adherent["adresse_adh"] = "";
	$adherent["adresse2_adh"] = "";
	$adherent["cp_adh"] = "";
	$adherent["ville_adh"] = "";
	$adherent["pays_adh"] = "";
	$adherent["tel_adh"] = "";
	$adherent["gsm_adh"] = "";
	$adherent["email_adh"] = "";
	$adherent["url_adh"] = "";
	$adherent["icq_adh"] = "";
	$adherent["msn_adh"] = "";
	$adherent["jabber_adh"] = "";
	$adherent["info_adh"] = "";
	$adherent["info_public_adh"] = "";
	$adherent["prof_adh"] = "";
	$adherent["login_adh"] = "";
	$adherent["mdp_adh"] = "";
	$adherent["date_crea_adh"] = "";
	$adherent["activite_adh"] = "";
	$adherent["bool_admin_adh"] = "";
	$adherent["bool_exempt_adh"] = "";
	$adherent["bool_display_info"] = "";
	$adherent["date_echeance"] = "";
	$adherent["pref_lang"] = "";
*/	
	
/*	

	$req = "SELECT pref_lang FROM ".PREFIX_DB."adherents
			WHERE id_adh=".$id_adh;
        $pref_lang = &$DB->Execute($req);
        $pref_lang = $pref_lang->fields[0];
	//include(WEB_ROOT."includes/lang.inc.php"); 
	// variables d'erreur (pour affichage)	    
 	$error_detected = array();
 	$warning_detected = array();
 	$confirm_detected = array();

	 //
	// DEBUT parametrage des champs
	//  On recupere de la base la longueur et les flags des champs
	//   et on initialise des valeurs par defaut
    
	// recuperation de la liste de champs de la table
	$fields = &$DB->MetaColumns(PREFIX_DB."adherents");
	while (list($champ, $proprietes) = each($fields))
	{
		$proprietes_arr = get_object_vars($proprietes);
		// on obtient name, max_length, type, not_null, has_default, primary_key,
		// auto_increment et binary		
		
		$fieldname = $proprietes_arr["name"];
				
		// on ne met jamais a jour id_adh
		if ($fieldname!="id_adh" && $fieldname!="date_echeance")
			$$fieldname= "";
			
	  $fieldlen = $fieldname."_len";
	  $fieldreq = $fieldname."_req";

	  // definissons  aussi la longueur des input text
	  $max_tmp = $proprietes_arr["max_length"];
	  if ($max_tmp == "-1")
	  	$max_tmp = 10;
	  $fieldlen = $fieldname."_len";
	  $$fieldlen=$max_tmp;

	  // et s'ils sont obligatoires (à partir de la base)
	  if ($proprietes_arr["not_null"]==1)
		  $$fieldreq = "style=\"color: #FF0000;\"";
		else
		  $$fieldreq = "";
	}
	reset($fields);

	// et les valeurs par defaut
	$id_statut = "4";
	$titre_adh = "1";

	  //
	 // FIN parametrage des champs
	// 	    	    
    
    //
   // Validation du formulaire
  //
  
  if (isset($_POST["valid"]))
  {
        if(!UniqueLogin($DB,$_POST["login_adh"])){
	  if (isset($_POST["id_adh"]) && $_POST["id_adh"] != ""){
	    // on vérifie que le login n'est pas déjà utilisé
	    $requete = "SELECT id_adh FROM ".
	      PREFIX_DB."adherents WHERE login_adh=". 
	      $DB->qstr($_POST["login_adh"], true).
	      " AND id_adh!=" . $DB->qstr($id_adh, true);
	    $result = &$DB->Execute($requete);
	    if (!$result->EOF || $_POST["login_adh"]==PREF_ADMIN_LOGIN){
	      $error_detected[] = _T("- This username is already used by another member !");
	    } 
          } else {
	    $error_detected[] = _T("Sorry, ").$_POST["login_adh"]._T(" is a username already used by another member, please select another one\n");
          }
	}
  	// verification de champs
  	$update_string = "";
  	$insert_string_fields = "";
  	$insert_string_values = "";
  
  	// recuperation de la liste de champs de la table
	  while (list($champ, $proprietes) = each($fields))
		{
			$proprietes_arr = get_object_vars($proprietes);
			// on obtient name, max_length, type, not_null, has_default, primary_key,
			// auto_increment et binary		
		
			$fieldname = $proprietes_arr["name"];
			
			// on précise les champs non modifiables
			if (
			        ($_POST["self_adherent"]==1) || //!!!faudra restreindre plus !
				($_SESSION["admin_status"]==1 && $fieldname!="id_adh"
							      && $fieldname!="date_echeance") ||
			    	($_SESSION["admin_status"]==0 && $fieldname!="date_crea_adh"
			    				      && $fieldname!="id_adh"
			    				      && $fieldname!="titre_adh"
			    				      && $fieldname!="id_statut"
			    				      && $fieldname!="nom_adh"
			    				      && $fieldname!="prenom_adh"
			    				      && $fieldname!="activite_adh"
			    				      && $fieldname!="bool_exempt_adh"
			    				      && $fieldname!="bool_admin_adh"
			    				      && $fieldname!="date_echeance"
			    				      && $fieldname!="info_adh")
			   )
			{			
				if (isset($_POST[$fieldname]))
				  $post_value=trim($_POST[$fieldname]);
				else			
					$post_value="";
					
				// on declare les variables pour la présaisie en cas d'erreur
				$$fieldname = htmlentities(stripslashes($post_value),ENT_QUOTES);
				$fieldreq = $fieldname."_req";
		
				// vérification de la présence des champs obligatoires
				if ($$fieldreq!="" && $post_value=="")
				  $error_detected[] = _T("- Mandatory field empty.");
				else
				{
					// validation des dates				
					if($proprietes_arr["type"]=="date" && $post_value!="")
					{
					  	if (ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $post_value, $array_jours) || $post_value=="")
					  	{
							if (checkdate($array_jours[2],$array_jours[1],$array_jours[3]) || $post_value=="")
								$value="'".$array_jours[3]."-".$array_jours[2]."-".$array_jours[1]."'";
							else
								$error_detected[] = _T("- Non valid date!");
						}
					  	else
					  		$error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!");
					}
 					elseif ($fieldname=="email_adh")
 					{
 						$post_value=strtolower($post_value);
						if (!is_valid_email($post_value) && $post_value!="")
					  	$error_detected[] = _T("- Non-valid E-Mail address!");
						else
		 					$value = $DB->qstr($post_value, true);
		 					
		 				if ($post_value=="" && isset($_POST["mail_confirm"]))
		 					$error_detected[] = _T("- You can't send a confirmation by email if the member hasn't got an address!");
					}
 					elseif ($fieldname=="url_adh")
 					{
 						if (!is_valid_web_url($post_value) && $post_value!="" && $post_value!="http://")
					  	$error_detected[] = _T("- Non-valid Website address! Maybe you've skipped the http:// ?");
						else
						{
							if ($post_value=="http://")
								$post_value="";
		 					$value = $DB->qstr($post_value, true);
						}
					}
 					elseif ($fieldname=="login_adh")
 					{
 						if (strlen($post_value)<4)
 							$error_detected[] = _T("- The username must be composed of at least 4 characters!");
 						else
 						{
 							// on vérifie que le login n'est pas déjà utilisé
 							$requete = "SELECT id_adh
 								    FROM ".PREFIX_DB."adherents
 								    WHERE login_adh=". $DB->qstr($post_value, true);
 							if ($id_adh!="")
 								$requete .= " AND id_adh!=" . $DB->qstr($id_adh, true);

 							$result = &$DB->Execute($requete);
							if (!$result->EOF || $post_value==PREF_ADMIN_LOGIN)
	 							$error_detected[] = _T("- This username is already used by another member !");
							else
	 							$value = $DB->qstr($post_value, true);
						}
 					}
 					elseif ($fieldname=="mdp_adh")
 					{
 						if (strlen($post_value)<4)
 							$error_detected[] = _T("- The password must be of at least 4 characters!");
 						else
 							$value = $DB->qstr($post_value, true);
 					}
 					else
 					{
 						// on se contente d'escaper le html et les caracteres speciaux
							$value = $DB->qstr($post_value, true);
					}

					// mise à jour des chaines d'insertion/update
					if ($value=="''")
						$value="NULL";
					$update_string .= ",".$fieldname."=".$value;
					$insert_string_fields .= ",".$fieldname;
					$insert_string_values .= ",".$value;		
				}
			}
		}
		reset($fields);
                                
  	// modif ou ajout
  	if ($error_detected=="")
  	{  	
 		 	if ($id_adh!="")
 		 	{
 		 		// modif
 		 		
				$requete = "UPDATE ".PREFIX_DB."adherents
 		 			    SET " . substr($update_string,1) . " 
 		 			    WHERE id_adh=" . $id_adh;
				$DB->Execute($requete);
				dblog(_T("Member card update:")." ".strtoupper($_POST["nom_adh"])." ".$_POST["prenom_adh"], $requete);

				$date_fin = get_echeance($DB, $id_adh);
				if ($date_fin!="")
					$date_fin_update = $DB->DBDate(mktime(0,0,0,$date_fin[1],$date_fin[0],$date_fin[2]));
				else
					$date_fin_update = "NULL";
				$requete = "UPDATE ".PREFIX_DB."adherents
					    SET date_echeance=".$date_fin_update."
					    WHERE id_adh=" . $id_adh;
  			}
 		 	else
 		 	{
  			// ajout
 			$insert_string_fields = substr($insert_string_fields,1);
			$insert_string_values = substr($insert_string_values,1);
  			$requete = "INSERT INTO ".PREFIX_DB."adherents
  				    (" . $insert_string_fields . ") 
  				    VALUES (" . $insert_string_values . ")";
			dblog(_T("Member card added:")." ".strtoupper($_POST["nom_adh"])." ".$_POST["prenom_adh"], $requete);
  							
  		}
			$DB->Execute($requete);
			
			// il est temps d'envoyer un mail
			if (isset($_POST["mail_confirm"]))
				if ($_POST["mail_confirm"]=="1")
					if ($email_adh!="")
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
						$mail_text .= _T("Username:")." ".custom_html_entity_decode($login_adh)."\n";
						$mail_text .= _T("Password:")." ".custom_html_entity_decode($mdp_adh)."\n";
						$mail_text .= "\n";
						$mail_text .= _T("See you soon!")."\n";
						$mail_text .= "\n";
						$mail_text .= _T("(this mail was sent automatically)")."\n";
						$mail_headers = "From: ".PREF_EMAIL_NOM." <".PREF_EMAIL.">\nContent-Type: text/plain; charset=iso-8859-15\n";
						mail ($email_adh,$mail_subject,$mail_text, $mail_headers);
					}
				
			// récupération du max pour insertion photo
			// ou passage en mode modif apres insertion
			if ($id_adh=="")
			{
				$requete = "SELECT max(id_adh)
										AS max
										FROM ".PREFIX_DB."adherents";
				$max = &$DB->Execute($requete);
				$id_adh_new = $max->fields["max"];
			}	
			else
				$id_adh_new = $id_adh;
			
			if (isset($_FILES["photo"]["tmp_name"]))
                        if ($_FILES["photo"]["tmp_name"]!="none" &&
                            $_FILES["photo"]["tmp_name"]!="") 
			{ 

				if ($_FILES['photo']['type']=="image/jpeg" || 
				    (function_exists("ImageCreateFromGif") && $_FILES['photo']['type']=="image/gif") || 
				    $_FILES['photo']['type']=="image/png" ||
				    $_FILES['photo']['type']=="image/x-png")
				{
					$tmp_name = $HTTP_POST_FILES["photo"]["tmp_name"];
						
					// extension du fichier (en fonction du type mime)
					if ($_FILES['photo']['type']=="image/jpeg")
						$ext_image = ".jpg";
					if ($_FILES['photo']['type']=="image/png" || $_FILES['photo']['type']=="image/x-png")
						$ext_image = ".png";
					if ($_FILES['photo']['type']=="image/gif")
						$ext_image = ".gif";
						
					// suppression ancienne photo
					// NB : une verification sur le type de $id_adh permet d'eviter une faille
					//      du style $id_adh = "../../../image"
					@unlink(WEB_ROOT . "photos/".$id_adh_new.".jpg");
					@unlink(WEB_ROOT . "photos/".$id_adh_new.".gif");
					@unlink(WEB_ROOT . "photos/".$id_adh_new.".jpg");
					@unlink(WEB_ROOT . "photos/tn_".$id_adh_new.".jpg");
					@unlink(WEB_ROOT . "photos/tn_".$id_adh_new.".gif");
					@unlink(WEB_ROOT . "photos/tn_".$id_adh_new.".jpg");
						
					// copie fichier temporaire			 		
					if (!@move_uploaded_file($tmp_name,WEB_ROOT . "photos/".$id_adh_new.$ext_image))
						$warning_detected[] = _T("- The photo seems not to be transferred correctly. But registration has been made.");
				 	else
						resizeimage(WEB_ROOT . "photos/".$id_adh_new.$ext_image,WEB_ROOT . "photos/tn_".$id_adh_new.$ext_image,130,130);
			 	}
			 	else
				{
					if (function_exists("imagegif"))
			 			$warning_detected[] = _T("- The transfered file isn't a valid image (GIF, PNG or JPEG). But registration has been made.");
					else
			 			$warning_detected[] = _T("- The transfered file isn't a valid image (PNG or JPEG). But registration has been made.");
				}
			}
                               
                        // Ajout des champs dynamiques
                        $requete = "SELECT id_cat, index_cat, name_cat, perm_cat, type_cat, size_cat FROM $info_cat_table";
                        if ($_SESSION["admin_status"] != 1)
                            $requete .= " WHERE perm_cat=$perm_all";
                        $res_cat = $DB->Execute($requete);
                        while (!$res_cat->EOF) {
                            $id_cat = $res_cat->fields[0];
                            $name_cat = $res_cat->fields[1];
                            $perm_cat = $res_cat->fields[2];
                            $type_cat = $res_cat->fields[3];
                            $size_cat = $res_cat->fields[4];
                            if ($_SESSION["admin_status"] != 1 && $perm_cat == $perm_admin)
                                continue;
                            $current_size = $_POST["info_field_size_$id_cat"];
                            $ins_idx =1;
                            for ($i = 0; $i < $current_size; ++$i) {
                                $field_name = "info_field_".$id_cat."_".$i;
                                $val = "";
                                if (isset($_POST[$field_name]))
                                    $val = $_POST[$field_name];
                                set_adh_info($DB, $id_adh_new, $id_cat, $ins_idx, $val);
                                if ($val != "")
                                    ++$ins_idx;
                            }
                            while($ins_idx <= $current_size) {
                                set_adh_info($DB, $id_adh_new, $id_cat, $ins_idx, "");
                                ++$ins_idx;
                            }
                            $res_cat->MoveNext();
                        }
                        $res_cat->Close();
			
			// retour à la liste ou passage à la contribution
			if (count($warning_detected)==0 && $id_adh=="")
			{
				header("location: ajouter_contribution.php?id_adh=".$id_adh_new);
				die();
			}
			elseif (count($warning_detected)==0 && !isset($_FILES["photo"]))
			{
				header("location: gestion_adherents.php");
				die();
			}
			elseif (count($warning_detected)==0 && ($_FILES["photo"]["tmp_name"]=="none" || $_FILES["photo"]["tmp_name"]==""))
			{
				header("location: gestion_adherents.php");
				die();
			}
			$id_adh=$id_adh_new;
  	    }
      
      }
  
 	// suppression photo
	if (isset($_POST["del_photo"]))
	{
 		@unlink(WEB_ROOT . "photos/" . $id_adh . ".jpg");
 		@unlink(WEB_ROOT . "photos/" . $id_adh . ".png");
 		@unlink(WEB_ROOT . "photos/" . $id_adh . ".gif");
 		@unlink(WEB_ROOT . "photos/tn_" . $id_adh . ".jpg");
 		@unlink(WEB_ROOT . "photos/tn_" . $id_adh . ".png");
 		@unlink(WEB_ROOT . "photos/tn_" . $id_adh . ".gif");
 	} 	
	
	  //	
	 // Pré-remplissage des champs
	//  avec des valeurs issues de la base
	//  -> donc uniquement si l'enregistrement existe et que le formulaire
	//     n'a pas déja été posté avec des erreurs (pour pouvoir corriger)
	
	if (!isset($_POST["valid"]) || (isset($_POST["valid"]) && count($error_detected)==0))
	if ($id_adh != "")


	{
		// recup des données
		$requete = "SELECT * 
								FROM ".PREFIX_DB."adherents 
			  				WHERE id_adh=$id_adh";
		$result = &$DB->Execute($requete);
        	if ($result->EOF)
	                header("location: index.php");
			                                                                                                                    
																	    
			
		// recuperation de la liste de champs de la table
	  //$fields = &$DB->MetaColumns(PREFIX_DB."cotisations");
	  while (list($champ, $proprietes) = each($fields))
		{
			//echo $proprietes_arr["name"]." -- (".$result->fields[$proprietes_arr["name"]].")<br>";


			$val="";
			$proprietes_arr = get_object_vars($proprietes);
			// on obtient name, max_length, type, not_null, has_default, primary_key,
			// auto_increment et binary		
		
		  // déclaration des variables correspondant aux champs
		  // et reformatage des dates.
			
			// on doit faire cette verif pour une enventuelle valeur "NULL"
			// non renvoyée -> ex: pas de societe membre
			// sinon on obtient un warning
			if (isset($result->fields[$proprietes_arr["name"]]))
				$val = $result->fields[$proprietes_arr["name"]];

			if($proprietes_arr["type"]=="date" && $val!="")
			{
			  list($a,$m,$j)=split("-",$val);
			  $val="$j/$m/$a";
			}
		  	$$proprietes_arr["name"]=htmlentities(stripslashes(addslashes($val)), ENT_QUOTES);
		}
		reset($fields);
	}
	else
	{
		// initialisation des champs
			
	}

	// la date de creation de fiche, ici vide si nouvelle fiche
	if ($date_crea_adh=="")
		$date_crea_adh = date("d/m/Y");
	if ($url_adh=="")
		$url_adh = "http://";
	if ($mdp_adh=="")
		$mdp_adh = makeRandomPassword(7);

	// variable pour la desactivation de champs		
	if ($_SESSION["admin_status"]==0)
		$disabled_field = "disabled";
	else
		$disabled_field = "";

	// récupération de l'images
	$image_adh = "";
	if(file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".jpg"))
		$image_adh = WEB_ROOT . "photos/tn_" . $id_adh . ".jpg";
	elseif (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".gif"))
		$image_adh = WEB_ROOT . "photos/tn_" . $id_adh . ".gif";
	elseif (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".png"))
		$image_adh = WEB_ROOT . "photos/tn_" . $id_adh . ".png";

	if (function_exists("ImageCreateFromString"))
	{
		if ($image_adh != "")
			$imagedata = getimagesize($image_adh);
		else
			$imagedata = getimagesize(WEB_ROOT . "photos/default.png");
	}
	else
		$imagedata = array("130","");
*/

/*
	$tpl->assign("id_adh",$logs);
	$tpl->assign("disabled_field",$disabled_field);
	
	$tpl->assign("imagedata",$imagedata);
	
	$tpl->assign("titre_adh",$titre_adh);
	$tpl->assign("titre_adh_req",$titre_adh_req);
	$tpl->assign("nom_adh",$nom_adh);
	$tpl->assign("nom_adh_req",$nom_adh_req);
	$tpl->assign("nom_adh_len",$nom_adh_len);
	$tpl->assign("prenom_adh",$prenom_adh);
	$tpl->assign("prenom_adh_req",$prenom_adh_req);
	$tpl->assign("prenom_adh_len",$prenom_adh_len);
	$tpl->assign("pseudo_adh",$pseudo_adh);
	$tpl->assign("pseudo_adh_req",$pseudo_adh_req);
	$tpl->assign("pseudo_adh_len",$pseudo_adh_len);
	$tpl->assign("ddn_adh",$ddn_adh);
	$tpl->assign("ddn_adh_req",$ddn_adh_req);
	$tpl->assign("ddn_adh_len",$ddn_adh_len);
	$tpl->assign("prof_adh",$prof_adh);
	$tpl->assign("prof_adh_req",$prof_adh_req);
	$tpl->assign("prof_adh_len",$prof_adh_len);
	$tpl->assign("bool_display_info",$bool_display_info);
	$tpl->assign("pref_lang",$pref_lang);
        $tpl->assign("logs",$logs);
	$tpl->assign("nb_lines",count($logs));
	$tpl->assign("nb_pages",$nbpages);
	$tpl->assign("page",$page);
*/


/*
	
		        $file = substr(substr($file,5),0,-4);
?>
                                    <OPTION value="<? echo $file; ?>" <? isSelected($pref_lang,$file) ?> style="padding-left: 30px; background-image: url(lang/<? echo $file.".gif"; ?>); background-repeat: no-repeat"><? echo ucfirst(_T($file)); ?></OPTION>
<?
				}
			}
			closedir($dir_handle);
?>
                                </SELECT>
                                </TD>
							</TR>
<?
	if ($_SESSION["admin_status"]!=0)
	{
?>
							<TR> 
								<TH colspan="4" id="header">&nbsp;</TH> 
							</TR>
							<TR>
								<TH <? echo $activite_adh_req ?> id="libelle"><? echo _T("Account:"); ?></TH> 
								<TD>
								  <SELECT name="activite_adh">
								  	<OPTION value="1"<? isSelected($activite_adh,"1") ?>><? echo _T("Active"); ?></OPTION>
								  	<OPTION value="0"<? isSelected($activite_adh,"0") ?>><? echo _T("Inactive"); ?></OPTION>
									</SELECT>
								</TD>
								<TH id="header" colspan="2">&nbsp;</TH>
							</TR>
							<TR> 
								<TH <? echo $id_statut_req ?> id="libelle"><? echo _T("Status:"); ?></TH> 
								<TD>
									<SELECT name="id_statut">
									<?
										$requete = "SELECT *
		 									    FROM ".PREFIX_DB."statuts
		 									    ORDER BY priorite_statut";
										$result = &$DB->Execute($requete);
										while (!$result->EOF)
										{									
									?>
										<OPTION value="<? echo $result->fields["id_statut"] ?>"<? isSelected($id_statut,$result->fields["id_statut"]) ?>><? echo gettext($result->fields["libelle_statut"]); ?></OPTION>
									<?
											$result->MoveNext();
										}
										$result->Close();
									?>
									</SELECT>
								</TD>
								<TH id="header" colspan="2">&nbsp;</TH>
							</TR>
							<TR> 
								<TH id="libelle"><? echo _T("Galette Admin:"); ?></TH> 
								<TD><input type="checkbox" name="bool_admin_adh" value="1"<? isChecked($bool_admin_adh,"1") ?>></TD> 
								<TH id="header" colspan="2">&nbsp;</TH>
						  	</TR> 
							<TR> 
								<TH id="libelle"><? echo _T("Freed of dues:"); ?></TH> 
								<TD><INPUT type="checkbox" name="bool_exempt_adh" value="1"<? isChecked($bool_exempt_adh,"1") ?>></TD> 
								<TH id="header" colspan="2">&nbsp;</TH>
						  	</TR>
<?
	}
?>
							<TR> 
								<TH colspan="4" id="header">&nbsp;</TH> 
							</TR>
							<TR> 
								<TH id="libelle" <? echo $adresse_adh_req ?>><? echo _T("Address:"); ?></TH> 
								<TD colspan="3">
									<INPUT type="text" name="adresse_adh" value="<? echo $adresse_adh; ?>" maxlength="<? echo $adresse_adh_len; ?>" size="63"><BR>
									<INPUT type="text" name="adresse2_adh" value="<? echo $adresse2_adh; ?>" maxlength="<? echo $adresse2_adh_len; ?>" size="63">
								</TD> 
						  </TR> 
							<TR> 
								<TH id="libelle" <? echo $cp_adh_req ?>><? echo _T("Zip Code:"); ?></TH> 
								<TD><INPUT type="text" name="cp_adh" value="<? echo $cp_adh; ?>" maxlength="<? echo $cp_adh_len; ?>"></TD> 
								<TH id="libelle" <? echo $ville_adh_req ?>><? echo _T("City:"); ?></TH> 
								<TD><INPUT type="text" name="ville_adh" value="<? echo $ville_adh; ?>" maxlength="<? echo $ville_adh_len; ?>"></TD> 
						  </TR> 
							<TR> 
								<TH id="libelle" <? echo $pays_adh_req ?>><? echo _T("Country:"); ?></TH> 
								<TD><INPUT type="text" name="pays_adh" value="<? echo $pays_adh; ?>" maxlength="<? echo $pays_adh_len; ?>"></TD> 
								<TH id="libelle" <? echo $tel_adh_req ?>><? echo _T("Phone:"); ?></TH> 
								<TD><INPUT type="text" name="tel_adh" value="<? echo $tel_adh; ?>" maxlength="<? echo $tel_adh_len; ?>"></TD> 
						  </TR> 
							<TR> 
								<TH id="libelle" <? echo $gsm_adh_req ?>><? echo _T("Mobile phone:"); ?></TH> 
								<TD><INPUT type="text" name="gsm_adh" value="<? echo $gsm_adh; ?>" maxlength="<? echo $gsm_adh_len; ?>"></TD> 
								<TH id="libelle" <? echo $email_adh_req ?>><? echo _T("E-Mail:"); ?></TH> 
								<TD><INPUT type="text" name="email_adh" value="<? echo $email_adh; ?>" maxlength="<? echo $email_adh_len; ?>" size="30"></TD> 
						  </TR> 
							<TR> 
								<TH id="libelle" <? echo $url_adh_req ?>><? echo _T("Website:"); ?></TH> 
								<TD><INPUT type="text" name="url_adh" value="<? echo $url_adh; ?>" maxlength="<? echo $url_adh_len; ?>" size="30"></TD> 
								<TH id="libelle" <? echo $icq_adh_req ?>><? echo _T("ICQ:"); ?></TH> 
								<TD><INPUT type="text" name="icq_adh" value="<? echo $icq_adh; ?>" maxlength="<? echo $icq_adh_len; ?>"></TD> 
						  </TR> 
							<TR> 
								<TH id="libelle" <? echo $jabber_adh_req ?>><? echo _T("Jabber:"); ?></TH> 
								<TD><INPUT type="text" name="jabber_adh" value="<? echo $jabber_adh; ?>" maxlength="<? echo $jabber_adh_len; ?>" size="30"></TD> 
								<TH id="libelle" <? echo $msn_adh_req ?>><? echo _T("MSN:"); ?></TH> 
								<TD><INPUT type="text" name="msn_adh" value="<? echo $msn_adh; ?>" maxlength="<? echo $msn_adh_len; ?>" size="30"></TD> 
						  </TR> 
              <TR> 
                <TH id="libelle" <? echo $gpgid_req ?>><? echo _T("Id GNUpg (GPG):"); ?></TH> 
                <TD><INPUT type="text" name="gpgid" value="<? echo $gpgid; ?>" maxlength="<? echo $gpgid_len; ?>" size="8"></TD> 
                <TH id="libelle" <? echo $fingerprint_req ?>><? echo _T("fingerprint:"); ?></TH> 
                <TD><INPUT type="text" name="fingerprint" value="<? echo $fingerprint; ?>" maxlength="<? echo $fingerprint_len; ?>" size="30"></TD> 
              </TR> 
							<TR> 
								<TH colspan="4" id="header">&nbsp;</TH> 
							</TR>
							<TR> 
								<TH id="libelle" <? echo $login_adh_req ?>><? echo _T("Username:"); ?><BR>&nbsp;</TH> 
								<TD><INPUT type="text" name="login_adh" value="<? echo $login_adh; ?>" maxlength="<? echo $login_adh_len; ?>"><BR><DIV class="exemple"><? echo _T("(at least 4 characters)"); ?></DIV></TD> 
								<TH id="libelle" <? echo $mdp_adh_req ?>><? echo _T("Password:"); ?><BR>&nbsp;</TH> 
								<TD><INPUT type="text" name="mdp_adh" value="<? echo $mdp_adh; ?>" maxlength="<? echo $mdp_adh_len; ?>"><BR><DIV class="exemple"><? echo _T("(at least 4 characters)"); ?></DIV></TD> 
						</TR>
<?
	if ($_SESSION["admin_status"]!=0)
	{
?>
						<TR> 
								<TH id="libelle"><? echo _T("Send a mail:"); ?><BR>&nbsp;</TH> 
								<TD colspan="3"><INPUT type="checkbox" name="mail_confirm" value="1" <? if ($id_adh=="") echo "CHECKED"; ?>><BR><DIV class="exemple"><? echo _T("(the member will receive his username and password by email, if he has an address.)"); ?></DIV></TD> 
						  </TR> 
							<TR> 
								<TH id="libelle"><? echo _T("Creation date:"); ?><BR>&nbsp;</TH> 
								<TD colspan="3"><INPUT type="text" name="date_crea_adh" value="<? echo $date_crea_adh; ?>" maxlength="10"><BR><DIV class="exemple"><? echo _T("(dd/mm/yyyy format)"); ?></DIV></TD> 
						  </TR> 
							<TR> 
								<TH id="libelle" <? echo $info_adh_req ?>><? echo _T("Other informations (admin):"); ?></TH> 
								<TD colspan="3"><TEXTAREA name="info_adh" cols="61" rows="6"><? echo $info_adh; ?></TEXTAREA><BR><DIV class="exemple"><? echo _T("This comment is only displayed for admins."); ?></DIV></TD> 
						  </TR> 
<?
	}
?>
							<TR> 
								<TH id="libelle" <? echo $info_public_adh_req ?>><? echo _T("Other informations:"); ?></TH> 
								<TD colspan="3">
									<TEXTAREA name="info_public_adh" cols="61" rows="6"><? echo $info_public_adh; ?></TEXTAREA>
<?
	if ($_SESSION["admin_status"]!=0)
	{
?>	
									<BR><DIV class="exemple"><? echo _T("This comment is reserved to the member."); ?></DIV>
<?
	}
?>	
								</TD> 
						  </TR> 
<?
    $requete = "SELECT id_cat, index_cat, name_cat, perm_cat, type_cat, size_cat FROM $info_cat_table";
    if ($_SESSION["admin_status"] != 1)
       $requete .= " WHERE perm_cat=$perm_all";
    $requete .= " ORDER BY index_cat";
    $res_cat = $DB->Execute($requete);
    while (!$res_cat->EOF)
    {
        $id_cat = $res_cat->fields[0];
        $rank_cat = $res_cat->fields[1];
        $name_cat = $res_cat->fields[2];
        $perm_cat = $res_cat->fields[3];
        $type_cat = $res_cat->fields[4];
        $size_cat = $res_cat->fields[5];
    
        if ($type_cat == $category_separator) {
            for ($i = 0; $i < $size_cat; ++$i) {
?>                                                
                                                    <TR><TH colspan="4" id="header">&nbsp;</TH></TR> 
<?
            }
        } else {
            $cond = "id_cat=$id_cat";
            if (is_numeric($id_adh))
                $cond .= " and id_adh=$id_adh";
	    else
	    	$cond .= " and 1=2";
	    // Cette condition est stupide
	    // Je l'ai rajoutee pour eviter d'avoir des valeurs a la creation de nouvelles fiches
	    // TODO : recoder proprement
	    
            $res_info = $DB->Execute("SELECT val_info, index_info FROM ".PREFIX_DB."adh_info WHERE $cond ORDER BY index_info");
            $current_size = $size_cat;
            if ($size_cat == 0)
                $current_size = $res_info->RecordCount() + 1;
            for ($i = 0; $i < $current_size; ++$i) {
?> 
                                                <TR>
<?
                if ($i == 0) {
?> 
                                                    <TH id="libelle" rowspan="<?php echo $current_size; ?>" <?php echo $info_public_adh_req ?> >
                                                        <INPUT type="hidden" name="info_field_size_<?php echo $id_cat; ?>" value="<?php echo $current_size; ?>" >
                                                        <?php echo $name_cat."&nbsp;:"; ?> 
                                                    </TH> 
<?
                }
                $field_name = "info_field_".$id_cat."_".$i;
                $val = $res_info->EOF ? "" : $res_info->fields[0];
?> 
                                                    <TD colspan="3">
<?
                if ($type_cat == $category_text) {
?> 
                                                        <TEXTAREA name="<?php echo $field_name; ?>" cols="61" rows="6"><?php echo $val; ?></TEXTAREA>
<?
                } elseif ($type_cat == $category_field) {
?> 
                                                        <INPUT type="text" name="<?php echo $field_name; ?>" value="<? echo $val; ?>" size="63">
<?
                }
?> 
                                                    </TD> 
                                                </TR>
<?
                $res_info->MoveNext();
            }
            $res_info->Close();
        }
        $res_cat->MoveNext();
    }
    $res_cat->Close();
?> 
							<TR> 
								<TH align="center" colspan="4"><BR><INPUT type="submit" name="valid" value="<? echo _T("Save"); ?>"></TH> 
						  </TR> 
                                                        </TABLE> 
						</DIV>
						<BR> 
						<? echo _T("NB : The mandatory fields are in"); ?> <FONT style="color: #FF0000"><? echo _T("red"); ?></FONT>. 
						</BLOCKQUOTE> 
						<INPUT type="hidden" name="id_adh" value="<? echo $id_adh ?>"> 
						</FORM> 
<?
	*/
?>
