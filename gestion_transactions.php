<?

/* gestion_transactions.php
 * - Récapitulatif des transactions
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
		$id_adh = $_SESSION["logged_id_adh"];
	else
		$id_adh = get_numeric_form_value("id_adh", '');

        $numrows = get_numeric_form_value("nbshow", PREF_NUMROWS);

        $page = get_numeric_form_value("page", 1);

	// Tri
	if (isset($_GET["tri"]))
	{
		if ($_SESSION["sort_by"]==$_GET["tri"])
			$_SESSION["sort_direction"]=($_SESSION["sort_direction"]+1)%2;
		else
		{
			$_SESSION["sort_by"]=$_GET["tri"];
			$_SESSION["sort_direction"]=0;
		}
	}

	if ($_SESSION["admin_status"] == 1) {
		$trans_id = get_numeric_form_value('sup', '');
		if ($trans_id != '') {
			$DB->StartTrans();
			$query = "DELETE FROM ".PREFIX_DB."cotisations
				  WHERE trans_id=".$trans_id;
			if (db_execute(&$DB, $query, &$error_detected))
				dblog("Transactions deleted", "", $query);
			$query = "DELETE FROM ".PREFIX_DB."transactions
				  WHERE trans_id=".$trans_id;
			if (db_execute(&$DB, $query, &$error_detected))
				dblog("Transaction deleted", "", $query);
			$DB->CompleteTrans();
		}
	}

	$trans_date_format = &$DB->SQLDate('d/m/Y',PREFIX_DB.'transactions.trans_date');
	$trans_table = PREFIX_DB."transactions";
	$member_table = PREFIX_DB."adherents";
	$query = "SELECT $trans_date_format AS trans_date,
			 $trans_table.trans_id,
			 $trans_table.trans_desc,
			 $trans_table.id_adh,
			 $trans_table.trans_amount,
			 $member_table.nom_adh,
			 $member_table.prenom_adh
			 FROM $trans_table,$member_table
			 WHERE $trans_table.id_adh=$member_table.id_adh";
	$nquery = "SELECT COUNT(trans_id) FROM $trans_table";

	// Filter
	if (is_numeric($id_adh)) {
		$query .= " AND $trans_table.id_adh=$id_adh";
		$nquery .= " WHERE id_adh=$id_adh";
	}

	// phase de tri

	if ($_SESSION["sort_direction"]=="0")
		$sort_direction_txt="ASC";
	else
		$sort_direction_txt="DESC";

	$query .= " ORDER BY ";

	// tri par adherent
	if ($_SESSION["sort_by"]=="1")
		$query .= "nom_adh ".$sort_direction_txt.", prenom_adh ".$sort_direction_txt.",";
	else if ($_SESSION["sort_by"]=="2")
		$query .= "trans_amount ".$sort_direction_txt.",";
	$query .= " ".PREFIX_DB."transactions.trans_date ".$sort_direction_txt;

	if ($numrows == 0)
		$result = $DB->Execute($query);
	else
		$result = $DB->SelectLimit($query, $numrows, ($page-1)*$numrows);

	$nb_transactions = $DB->GetOne($nquery);
	$transactions = array();

	if ($numrows==0)
		$nbpages = 1;
	else if ($nb_transactions%$numrows==0)
		$nbpages = intval($nb_transactions/$numrows);
	else
		$nbpages = intval($nb_transactions/$numrows)+1;
	if ($nbpages==0)
		$nbpages = 1;

	if ($result) {
		while(!$result->EOF)
		{
			$data = array('trans_id' => $result->fields['trans_id'],
				      'trans_date' => $result->fields['trans_date'],
				      'trans_desc' => $result->fields['trans_desc'],
				      'trans_amount' => $result->fields['trans_amount'],
				      'id_adh' => $result->fields['id_adh'],
				      'lastname' => htmlentities(strtoupper($result->fields['nom_adh']),ENT_QUOTES),
				      'firstname' => htmlentities($result->fields['prenom_adh'], ENT_QUOTES));
			$transactions[] = $data;
			$result->MoveNext();
		}
		$result->Close();
	} else print $DB->ErrorMsg()." ".$query;

	$tpl->assign("transactions", $transactions);
	$tpl->assign("nb_transactions", $nb_transactions);
	$tpl->assign("nb_pages", $nbpages);
	$tpl->assign("page", $page);
        $tpl->assign('nbshow_options', array(
			10 => "10",
			20 => "20",
			50 => "50",
			100 => "100",
			0 => _T("All")));
	$tpl->assign("numrows",$numrows);
	$content = $tpl->fetch("gestion_transactions.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
