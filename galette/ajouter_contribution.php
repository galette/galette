<? 
 
/* ajouter_contribution.php
 * - Saisie d'une contributions
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
	
	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		header("location: voir_adherent.php");
		
	// new or edit
	$contribution['id_cotis'] = '';
	if (isset($_GET["id_cotis"]))
		if (is_numeric($_GET["id_cotis"]))
			$contribution['id_cotis'] = $_GET["id_cotis"];
	if (isset($_POST["id_cotis"]))
		if (is_numeric($_POST["id_cotis"]))
			$contribution['id_cotis'] = $_POST["id_cotis"];
			
	if (isset($_GET["id_adh"]))
		if (is_numeric($_GET["id_adh"]))
			$contribution['id_adh'] = $_GET["id_adh"];			

	// initialize warning
 	$error_detected = array();

	// flagging required fields
	$required = array(
			'montant_cotis' => 1,
			'duree_mois_cotis' => 1,
			'date_cotis' => 1,
			'id_type_cotis' => 1,
			'id_adh' => 1);

	// Validation
	if (isset($_POST["valid"]))
	{
		$update_string = '';
		$insert_string_fields = '';
		$insert_string_values = '';
	
		// checking posted values
		$fields = &$DB->MetaColumns(PREFIX_DB."cotisations");
	        while (list($key, $properties) = each($fields))
		{
			$key = strtolower($key);
			if (isset($_POST[$key]))
				$value = trim($_POST[$key]);
			else
				$value = '';
	
			// fill up the contribution structure
			$contribution[$key] = htmlentities(stripslashes($value),ENT_QUOTES);

			// now, check validity
			if ($value != "")
			switch ($key)
			{
				// date
				case 'date_cotis':
					if (ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $value, $array_jours))
					{
						if (checkdate($array_jours[2],$array_jours[1],$array_jours[3]))
							$value = $DB->DBDate(mktime(0,0,0,$array_jours[2],$array_jours[1],$array_jours[3]));
						else
							$error_detected[] = _T("- Non valid date!")." ($key)";
					}
					else
						$error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!")." ($key)";
					break;
				case 'duree_mois_cotis':
 					if (!is_numeric($value))
						$error_detected[] = _T("- The duration must be an integer!");
					break;
 				case 'montant_cotis':
 					$us_value = strtr($value, ",", ".");
 					if (!is_numeric($us_value))
						$error_detected[] = _T("- The amount must be an integer!");
 					break;
			}

			// dates already quoted
			if ($key != 'date_cotis' || $value=='')
				$value = $DB->qstr($value);
		
			$update_string .= ", ".$key."=".$value;
			$insert_string_fields .= ", ".$key;
			$insert_string_values .= ", ".$value;
		}
		
		// missing relations
		// none here yet
		
		// missing required fields?
		while (list($key,$val) = each($required))
		{
			if (!isset($contribution[$key]) && !isset($disabled[$key]))
				$error_detected[] = _T("- Mandatory field empty.")." ($key)";
			elseif (isset($contribution[$key]) && !isset($disabled[$key]))
				if (trim($contribution[$key])=='')
					$error_detected[] = _T("- Mandatory field empty.")." ($key)";
		}
		
		if (count($error_detected)==0)
		{
			if ($contribution["id_cotis"] == "")
			{
				$requete = "INSERT INTO ".PREFIX_DB."cotisations
				(" . substr($insert_string_fields,1) . ")
				VALUES (" . substr($insert_string_values,1) . ")";
				$DB->Execute($requete);
				
				// to allow the string to be extracted for translation
				$foo = _T("Contribution added");

				// logging
				dblog('Contribution added','',$requete);
			}
			else
			{
                                $requete = "UPDATE ".PREFIX_DB."cotisations
                                            SET " . substr($update_string,1) . "
                                            WHERE id_cotis=" . $contribution['id_cotis'];
                                $DB->Execute($requete);

				// to allow the string to be extracted for translation
				$foo = _T("Contribution updated");

				// logging
                                dblog('Contribution updated','',$requete);
			}

			// update deadline
			$date_fin = get_echeance($DB, $contribution['id_adh']);
			if ($date_fin!="")
				$date_fin_update = $DB->DBDate(mktime(0,0,0,$date_fin[1],$date_fin[0],$date_fin[2]));
			else
				$date_fin_update = "NULL";
			$requete = "UPDATE ".PREFIX_DB."adherents
					SET date_echeance=".$date_fin_update."
					WHERE id_adh=" . $contribution['id_adh'];
			$DB->Execute($requete);
			
			header ('location: gestion_contributions.php?id_adh='.$contribution['id_adh']);
		}
	}
	else
	{
		if ($contribution["id_cotis"] == "")
		{
			// initialiser la structure contribution à vide (nouvelle contribution)
			$contribution['duree_mois_cotis']=12;
			$contribution['date_cotis'] = date("d/m/Y");
		}
		else
		{
			// initialize coontribution structure with database values
			$sql =  "SELECT * ".
				"FROM ".PREFIX_DB."cotisations ".
				"WHERE id_cotis=".$contribution["id_cotis"];
			$result = &$DB->Execute($sql);
			if ($result->EOF)
				header("location: index.php");
			else
			{
				// plain info
				$contribution = $result->fields;

				// reformat dates
				if ($contribution['date_cotis'] != '')
				{
					list($a,$m,$j)=split("-",$contribution['date_cotis']);
					$contribution['date_cotis']="$j/$m/$a";
				}
			}	
		}
	}

	// template variable declaration
	$tpl->assign("required",$required);
	$tpl->assign("contribution",$contribution);
	$tpl->assign("error_detected",$error_detected);

	// contribution types
	$requete = "SELECT id_type_cotis, libelle_type_cotis
			FROM ".PREFIX_DB."types_cotisation
			ORDER BY libelle_type_cotis";
	$result = &$DB->Execute($requete);
	while (!$result->EOF)
	{
		$type_cotis_options[$result->fields[0]] = htmlentities(stripslashes(_T($result->fields[1])),ENT_QUOTES);
		$result->MoveNext();
	}
	$result->Close();
	$tpl->assign("type_cotis_options",$type_cotis_options);

	// members
	$requete = "SELECT id_adh, nom_adh, prenom_adh
			FROM ".PREFIX_DB."adherents
			ORDER BY nom_adh, prenom_adh";
	$result = &$DB->Execute($requete);
	while (!$result->EOF)
	{
		$adh_options[$result->fields[0]] = htmlentities(stripslashes(strtoupper($result->fields[1])." ".$result->fields[2]),ENT_QUOTES);
		$result->MoveNext();
	}
	$result->Close();
	$tpl->assign("adh_options",$adh_options);

	// page generation
	$content = $tpl->fetch("ajouter_contribution.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
