<?php

/* ajouter_transaction.php
 * -Entering a transaction
 * Copyright (c) 2004 Laurent Pelecq <laurent.pelecq@soleil.org>
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

require_once('includes/galette.inc.php');

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

include(WEB_ROOT."includes/dynamic_fields.inc.php");

// new or edit
$transaction['trans_id'] = get_numeric_form_value("trans_id", '');
$transaction['trans_amount'] = get_numeric_form_value("trans_amount", '');
$transaction['trans_date'] = get_form_value("trans_date", '');
$transaction['trans_desc'] = get_form_value("trans_desc", '');
$transaction['id_adh'] = get_numeric_form_value("id_adh", '');

// initialize warning
$error_detected = array();

// flagging required fields
$required = array('trans_amount' => 1,
		  'trans_date' => 1,
		  'trans_desc' => 1);

function current_contrib_amount($DB, $trans_id, $error_detected) {
	if (is_numeric($trans_id)) {
		$current_amount = $DB->GetOne("SELECT SUM(montant_cotis)
						FROM ".PREFIX_DB."cotisations
						WHERE trans_id=$trans_id");
		return $current_amount;
	}
	return 0;
}

// Validation
$transaction['dyn'] = array();
if (isset($_POST["valid"]))
{
	$transaction['dyn'] = extract_posted_dynamic_fields($DB, $_POST, array());

	$update_string = '';
	$insert_string_fields = '';
	$insert_string_values = '';

	// checking posted values
	$fields = &$DB->MetaColumns(PREFIX_DB."transactions");
	while (list($key, $properties) = each($fields))
	{
		$key = strtolower($key);
		if (isset($_POST[$key]))
			$value = trim($_POST[$key]);
		else
			$value = '';

		// fill up the transaction structure
		$transaction[$key] = htmlentities(stripslashes($value),ENT_QUOTES);

		// now, check validity
		if ($value != "") {
			switch ($key)
			{
			case 'trans_desc':
				if ($value == '')
					$error_detected[] = _T("- Empty transaction description!");
				$value = $DB->qstr($value,get_magic_quotes_gpc());
				break;
			case 'trans_date':
				if (ereg("^[0-9]{2}/[0-9]{2}/[0-9]{4}$", $value, $result)) {
					$value = date_text2db($DB, $value);
					if ($value == "")
						$error_detected[] = _T("- Non valid date!")." ($key)";
				} else
					$error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!")." ($key)";
				break;
			}
		}

		if ($key != 'trans_id' && ($key != 'id_adh' || $value > 0)) {
			$update_string .= ", ".$key."=".$value;
			$insert_string_fields .= ", ".$key;
			$insert_string_values .= ", ".$value;
		}
	}

	// missing required fields?
	while (list($key,$val) = each($required))
	{
		if ($val == 0)
			continue;
		if (!isset($transaction[$key]) && !isset($disabled[$key]))
			$error_detected[] = _T("- Mandatory field empty.")." ($key)";
		elseif (isset($transaction[$key]) && !isset($disabled[$key]))
			if (trim($transaction[$key])=='')
				$error_detected[] = _T("- Mandatory field empty.")." ($key)";
	}

	$contrib_amount = 0;
	if ($transaction['trans_id'] != "")
		$contrib_amount = current_contrib_amount($DB, $transaction['trans_id'], $error_detected);
	$missing_amount = $transaction['trans_amount'] - $contrib_amount;

	if (count($error_detected) == 0)
	{
		// missing relations
		// Check that membership fees does not overlap
		if ($transaction['trans_amount'] <= 0)
			$error_detected[] = _T("- Transaction amount must be positive.");
		else if ($contrib_amount > $transaction['trans_amount'])
			$error_detected[] = _T("-  Sum of all contributions exceed corresponding transaction amount.");
		if ($transaction['id_adh'] <= 0)
			$error_detected[] = _T("- No originator selected (register a non-member first if necessary).");
	}

	if (count($error_detected) == 0)
	{
		if ($transaction["trans_id"] == "")
		{
			$requete = "INSERT INTO ".PREFIX_DB."transactions
			(" . substr($insert_string_fields,1) . ")
			VALUES (" . substr($insert_string_values,1) . ")";
			if (!$DB->Execute($requete))
				print "$requete: ".$DB->ErrorMsg();
			$transaction['trans_id'] = get_last_auto_increment($DB, PREFIX_DB."transactions", "trans_id");

			// to allow the string to be extracted for translation
			$foo = _T("transaction added");

			// logging
			dblog('transaction added','',$requete);
		}
		else
		{
			$requete = "UPDATE ".PREFIX_DB."transactions
				    SET " . substr($update_string,1) . "
				    WHERE trans_id=" . $transaction['trans_id'];
			$DB->Execute($requete);

			// to allow the string to be extracted for translation
			$foo = _T("transaction updated");

			// logging
			dblog('transaction updated','',$requete);
		}

		// dynamic fields
		set_all_dynamic_fields($DB, 'trans', $transaction['trans_id'], $transaction['dyn']);

		if ($missing_amount > 0) {
			$url = 'ajouter_contribution.php?trans_id='.$transaction['trans_id'];
			if (isset($transaction['id_adh']))
				$url .= '&id_adh='.$transaction['id_adh'];
		} else
			$url = 'gestion_transactions.php';
		header('location: '.$url);
	}

}
else
{
	if ($transaction['trans_id'] == "")
	{
		// initialiser la structure transaction à vide (nouvelle transaction)
		$transaction['trans_date'] = date("d/m/Y", time());
	}
	else
	{
		// initialize coontribution structure with database values
		$sql =  "SELECT * ".
			"FROM ".PREFIX_DB."transactions ".
			"WHERE trans_id=".$transaction["trans_id"];
		$result = &$DB->Execute($sql);
		if ($result->EOF)
			header("location: index.php");
		else
		{
			// plain info
			$transaction = $result->fields;

			// reformat dates
			$transaction['trans_date'] = date_db2text($transaction['trans_date']);
		}

		// dynamic fields
		$transaction['dyn'] = get_dynamic_fields($DB, 'trans', $transaction["trans_id"], false);

	}

}

// template variable declaration
$tpl->assign("required",$required);
$tpl->assign("data",$transaction);
$tpl->assign("error_detected",$error_detected);

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

// - declare dynamic fields for display
$dynamic_fields = prepare_dynamic_fields_for_display($DB, 'trans', $_SESSION["admin_status"], $transaction['dyn'], array(), 1);
$tpl->assign("dynamic_fields",$dynamic_fields);

// page generation
$content = $tpl->fetch("ajouter_transaction.tpl");
$tpl->assign("content",$content);
$tpl->display("page.tpl");
?>
