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
        include(WEB_ROOT."includes/dynamic_fields.inc.php");

	if ($_SESSION["logged_status"]==0)
		header("location: index.php");
	if ($_SESSION["admin_status"]==0)
		header("location: voir_adherent.php");


	function missing_contrib_amount($DB, $trans_id, $error_detected) {
		if (is_numeric($trans_id)) {
			$total_amount = db_get_one(&$DB, "SELECT trans_amount
							  FROM ".PREFIX_DB."transactions
							  WHERE trans_id=$trans_id", &$error_detected);
			$current_amount = $DB->GetOne("SELECT SUM(montant_cotis)
							FROM ".PREFIX_DB."cotisations
							WHERE trans_id=$trans_id");
			return $total_amount - $current_amount;
		}
		return 0;
	}

	// new or edit
	$contribution['id_cotis'] = get_numeric_form_value("id_cotis", '');
	$contribution['id_type_cotis'] = get_numeric_form_value("id_type_cotis", '');
	$contribution['id_adh'] = get_numeric_form_value("id_adh", '');
	$contribution['trans_id'] = get_numeric_form_value("trans_id", '');
	$adh_selected = isset($contribution['id_adh']);
	$tpl->assign("adh_selected", $adh_selected);

	$type_selected = $contribution['id_cotis']!='' || get_form_value("type_selected", 0);
	$tpl->assign("type_selected", $type_selected);

	$cotis_extension = 0;
	if (isset($contribution['id_type_cotis'])) {
		$request = "SELECT cotis_extension
			    FROM ".PREFIX_DB."types_cotisation
			    WHERE id_type_cotis = ".$contribution['id_type_cotis'];
		$cotis_extension = &$DB->GetOne($request);
	}

	// initialize warning
 	$error_detected = array();

	// flagging required fields
	$required = array(
			'montant_cotis' => 1,
			'date_debut_cotis' => 1,
			'date_fin_cotis' => $cotis_extension,
			'id_type_cotis' => 1,
			'id_adh' => 1);

	// Validation
	$contribution['dyn'] = array();
	if (isset($_POST["valid"]))
	{
		$contribution['dyn'] = extract_posted_dynamic_fields($DB, $_POST, array());

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
			else if ($key == 'date_enreg')
				$value = $DB->DBDate(time());
			else if ($key == 'date_fin_cotis' && isset($_POST['duree_mois_cotis']) &&
				 isset($_POST['date_debut_cotis'])) {
				$nmonths = trim($_POST['duree_mois_cotis']);
				if (!is_numeric($nmonths) && $nmonths >= 0) {
					$error_detected[] = _T("- The duration must be an integer!");
					$value="01/01/0001"; // To avoid error msg about date format
				} else if (ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $_POST['date_debut_cotis'], $debut))
					$value = date("d/m/Y", mktime(0, 0, 0, $debut[2] + $nmonths, $debut[1], $debut[3]));
			} else
				$value = '';

			// fill up the contribution structure
			$contribution[$key] = htmlentities(stripslashes($value),ENT_QUOTES);

			// now, check validity
			if ($value != "")
			switch ($key)
			{
				case 'id_adh':
 					if (!is_numeric($value))
						$error_detected[] = _T("- Select a valid member name!");
					break;
				// date
				case 'date_debut_cotis':
				case 'date_fin_cotis':
					if (ereg("^[0-9]{2}/[0-9]{2}/[0-9]{4}$", $value, $array_jours))
					{
						$value = date_text2db($DB, $value);
						if ($value == "")
							$error_detected[] = _T("- Non valid date!")." ($key)";
					}
					else
						$error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!")." ($key)";
					break;
 				case 'montant_cotis':
 					$us_value = strtr($value, ",", ".");
 					if (!is_numeric($us_value))
						$error_detected[] = _T("- The amount must be an integer!");
 					break;
			}

			// dates already quoted
			if (strncmp($key, "date_", 5) != 0)
				$value = $DB->qstr($value);
			if ($key != 'date_fin_cotis' || $cotis_extension) {
				$update_string .= ", ".$key."=".$value;
				if ($key != 'id_cotis') {
					$insert_string_fields .= ", ".$key;
					$insert_string_values .= ", ".$value;
				}
			}
		}

		// missing required fields?
		while (list($key,$val) = each($required))
		{
			if ($val == 0)
				continue;
			if (!isset($contribution[$key]) && !isset($disabled[$key]))
				$error_detected[] = _T("- Mandatory field empty.")." ($key)";
			elseif (isset($contribution[$key]) && !isset($disabled[$key]))
				if (trim($contribution[$key])=='')
					$error_detected[] = _T("- Mandatory field empty.")." ($key)";
		}

		$missing_amount = 0;
		if (count($error_detected) == 0)
		{
			// missing relations
			// Check that membership fees does not overlap
			if ($cotis_extension) {
				$table_cotis = PREFIX_DB."cotisations";
				$table_type_cotis = PREFIX_DB."types_cotisation";
				$id_adh = $contribution['id_adh'];
				$date_debut = date_text2db($DB, $contribution['date_debut_cotis']);
				$date_fin = date_text2db($DB, $contribution['date_fin_cotis']);
				$requete = "SELECT date_debut_cotis, date_fin_cotis
					    FROM $table_cotis, $table_type_cotis
					    WHERE $table_cotis.id_adh = $id_adh AND
						  $table_cotis.id_type_cotis = $table_type_cotis.id_type_cotis AND
					          cotis_extension = '1' ";
				if ($contribution["id_cotis"] != "")
					$requete .= "AND id_cotis != ".$contribution["id_cotis"]." ";
				$requete .= "AND ((date_debut_cotis >= ".$date_debut." AND date_debut_cotis < ".$date_fin.")
					          OR (date_fin_cotis > ".$date_debut." AND date_fin_cotis <= ".$date_fin."))";
				$result = $DB->Execute($requete);
				if (!$result)
					print "$requete: ".$DB->ErrorMsg();
				if (!$result->EOF)
					$error_detected[] = _T("- Membership period overlaps period starting at ").date_db2text($result->fields['date_debut_cotis']);
				$result->Close();
			}

			// If there is a transaction for this contribution, check that the sum of all contributioncoming from that transaction doesn't
			// exceed the transaction amount itself.

			if (count($error_detected) == 0 && $contribution['trans_id']) {
				$missing_amount = missing_contrib_amount(&$DB, $contribution['trans_id'], &$error_detected);
				if ($missing_amount < $contribution['montant_cotis'])
					$error_detected[] = _T("-  Sum of all contributions exceed corresponding transaction amount.");
				else
					$missing_amount -= $contribution['montant_cotis'];
			}
		}

		if (count($error_detected)==0)
		{
			if ($contribution["id_cotis"] == "")
			{
				$requete = "INSERT INTO ".PREFIX_DB."cotisations
				(" . substr($insert_string_fields,1) . ")
				VALUES (" . substr($insert_string_values,1) . ")";
				if (!$DB->Execute($requete))
					print "$requete: ".$DB->ErrorMsg();
				$contribution['id_cotis'] = get_last_auto_increment($DB, PREFIX_DB."cotisations", "id_cotis");

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

			// dynamic fields
			set_all_dynamic_fields($DB, 'contrib', $contribution['id_cotis'], $contribution['dyn']);

			// update deadline
			if ($cotis_extension) {
				$date_fin = get_echeance($DB, $contribution['id_adh']);
				if ($date_fin!="")
					$date_fin_update = date_text2db($DB, implode("/", $date_fin));
				else
					$date_fin_update = "NULL";
				$requete = "UPDATE ".PREFIX_DB."adherents
						SET date_echeance=".$date_fin_update."
						WHERE id_adh=" . $contribution['id_adh'];
				$DB->Execute($requete);
			}

			if ($missing_amount > 0) {
				$url = 'ajouter_contribution.php?trans_id='.$contribution['trans_id'].'&id_adh='.$contribution['id_adh'];
			} else
				$url = 'gestion_contributions.php?id_adh='.$contribution['id_adh'];
			header ('location: '.$url);
		}

		if (!isset($contribution['duree_mois_cotis']) || $contribution['duree_mois_cotis'] == "") {
			// On error restore entered value or default to display the form again
			if (isset($_POST['duree_mois_cotis']) && $_POST['duree_mois_cotis'] != "")
				$contribution['duree_mois_cotis'] = $_POST['duree_mois_cotis'];
			else
				$contribution['duree_mois_cotis'] = PREF_MEMBERSHIP_EXT;
		}

	}
	else
	{
		if ($contribution["id_cotis"] == "")
		{
			// initialiser la structure contribution à vide (nouvelle contribution)
			$contribution['duree_mois_cotis']=PREF_MEMBERSHIP_EXT;
			if ($cotis_extension && isset($contribution["id_adh"])) {
				$curend = get_echeance($DB, $contribution["id_adh"]);
				if ($curend == "")
					$beg_cotis = time();
				else {
					$beg_cotis = mktime(0, 0, 0, $curend[1], $curend[0], $curend[2]);
					if ($beg_cotis < time())
						$beg_cotis = time(); // Member didn't renew on time
				}
			} else
				$beg_cotis = time();
			$contribution['date_debut_cotis'] = date("d/m/Y", $beg_cotis);
			// End date is the date of next period after this one
			$contribution['date_fin_cotis'] = beg_membership_after($beg_cotis);
			if (is_numeric($contribution['trans_id']))
				$contribution['montant_cotis'] = missing_contrib_amount(&$DB, $contribution['trans_id'], &$error_detected);
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
				$contribution['date_debut_cotis'] = date_db2text($contribution['date_debut_cotis']);
				$contribution['date_fin_cotis'] = date_db2text($contribution['date_fin_cotis']);
				$contribution['duree_mois_cotis'] = distance_months($contribution['date_debut_cotis'], $contribution['date_fin_cotis']);
				$request = "SELECT cotis_extension
					    FROM ".PREFIX_DB."types_cotisation
					    WHERE id_type_cotis = ".$contribution['id_type_cotis'];
				$cotis_extension = &$DB->GetOne($request);
			}

			// dynamic fields
			$contribution['dyn'] = get_dynamic_fields($DB, 'contrib', $contribution["id_cotis"], false);

		}

	}

	// template variable declaration
	$tpl->assign("required",$required);
	$tpl->assign("data",$contribution);
	$tpl->assign("error_detected",$error_detected);

	// contribution types
	$requete = "SELECT DISTINCT cotis_extension
		    FROM ".PREFIX_DB."types_cotisation";
        $exval = &$DB->GetOne($requete);
	$requete = "SELECT id_type_cotis, libelle_type_cotis
		   FROM ".PREFIX_DB."types_cotisation ";
	if ($type_selected == 1)
		$requete .= "WHERE cotis_extension IS ".($cotis_extension ? "NOT " : "")." NULL ";
	$requete .= "ORDER BY libelle_type_cotis";
	$result = &$DB->Execute($requete);
	if (!$result)
		print $DB->ErrorMsg();
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
	if ($result->EOF)
		$adh_options = array('' => _T("You must first register a member"));
	else while (!$result->EOF)
	{
		$adh_options[$result->fields[0]] = htmlentities(stripslashes(strtoupper($result->fields[1])." ".$result->fields[2]),ENT_QUOTES);
		$result->MoveNext();
	}
	$result->Close();
	$tpl->assign("adh_options",$adh_options);

	$tpl->assign("pref_membership_ext", $cotis_extension ? PREF_MEMBERSHIP_EXT : "");
	$tpl->assign("cotis_extension", $cotis_extension);

	// - declare dynamic fields for display
	$dynamic_fields = prepare_dynamic_fields_for_display($DB, 'contrib', $_SESSION["admin_status"], $contribution['dyn'], array(), 1);
	$tpl->assign("dynamic_fields",$dynamic_fields);

	// page generation
	$content = $tpl->fetch("ajouter_contribution.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
