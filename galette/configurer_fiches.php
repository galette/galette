<? 
/* configurer_fiches.php
 * - Configuration des fiches adhérents
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
 
	include("includes/config.inc.php");
	include(WEB_ROOT."includes/database.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php");
	include(WEB_ROOT."includes/functions.inc.php"); 
        include(WEB_ROOT."includes/i18n.inc.php");
	include(WEB_ROOT."includes/smarty.inc.php");
        include(WEB_ROOT."includes/categories.inc.php");

	if ($_SESSION["logged_status"]==0)
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		header("location: voir_adherent.php");

	$error_detected = array();
    
	if (isset($_POST["valid"]))
	{
		if ($_POST["perm_cat"] != $category_separator &&
		   (!isset($_POST["name_cat"]) || $_POST["name_cat"] == "")) {
			$error_detected[] = _T("- The field Name cannot be void.");
		} else {
			$name_cat = $_POST["name_cat"];
			$perm_cat = $_POST["perm_cat"];
			$type_cat = $_POST["type_cat"];
			$size_cat = $_POST["size_cat"];
			$requete = "SELECT COUNT(*) + 1 AS idx 
				    FROM $info_cat_table";
			$result = $DB->Execute($requete);
			$requete = "INSERT INTO $info_cat_table
				    ( index_cat, name_cat, perm_cat, type_cat, size_cat )
				    VALUES ('".$result->fields["idx"]."',".$DB->qstr($name_cat, true).", $perm_cat, $type_cat, $size_cat)";
			$DB->Execute($requete);
		}
	}
	else
	{
		$action = "";
		$id_cat = "";
		foreach (array("sup", "up", "down") as $varname)
		{
			if (isset($_GET[$varname]) && is_numeric($_GET[$varname]))
			{
				$action = $varname;
				$id_cat = (integer)$_GET[$varname];
				break;
			}
		}
		$DB->StartTrans();
		$res = $DB->Execute("SELECT index_cat FROM $info_cat_table WHERE id_cat=$id_cat");
		if (!$res->EOF)
		{
			$old_rank = $res->fields[0];
			if ($action == "sup")
			{
				$DB->Execute("UPDATE $info_cat_table SET index_cat=index_cat-1 WHERE index_cat > $old_rank");
				$DB->Execute("DELETE FROM $info_adh_table WHERE id_cat=$id_cat");
				$DB->Execute("DELETE FROM $info_cat_table WHERE id_cat=$id_cat");
			}
			elseif ($action != "")
			{
				$direction = $action == "up" ? -1: 1;
				$new_rank = $old_rank + $direction;
				$DB->Execute("UPDATE $info_cat_table SET index_cat=$old_rank WHERE index_cat=$new_rank");
				$DB->Execute("UPDATE $info_cat_table SET index_cat=$new_rank WHERE id_cat=$id_cat");
			}
		}
		$DB->CompleteTrans();
	}

	$request = "SELECT id_cat, index_cat, name_cat, perm_cat, type_cat, size_cat FROM $info_cat_table ORDER BY index_cat";
	$result = $DB->Execute($request);
	$count = 0;
	while (!$result->EOF)
	{
		$dyn_fields[$count]['id'] = $result->fields[0];
		$dyn_fields[$count]['index'] = $result->fields[1];
		$dyn_fields[$count]['name'] = $result->fields[2];
		switch($result->fields[3])
		{
			case $perm_all:
				$dyn_fields[$count]['perm'] = _T('all');
				break;
			case $perm_admin:
				$dyn_fields[$count]['perm'] = _T('admin');
				break;
			default:
				$dyn_fields[$count]['perm'] = _T('unknown');
		}
		switch($result->fields[4])
		{
			case $category_separator:
				$dyn_fields[$count]['type_name'] = _T('separator');
				break;
			case $category_text:
				$dyn_fields[$count]['type_name'] = _T('free text');
				break;
			case $category_field:
				$dyn_fields[$count]['type_name'] = _T('field');
				break;
			default:
				$dyn_fields[$count]['type_name'] = _T('unknown');
		}
		$dyn_fields[$count]['size'] = $result->fields[5];
		$result->MoveNext();
		++$count;
	}
	$result->Close();

	$tpl->assign("perm_all",$perm_all);
	$tpl->assign("perm_admin",$perm_admin);
	$tpl->assign("category_separator",$category_separator);
	$tpl->assign("category_text",$category_text);
	$tpl->assign("category_field",$category_field);
	
	$tpl->assign("dyn_fields",$dyn_fields);
	$tpl->assign("error_detected",$error_detected);
	$content = $tpl->fetch("configurer_fiches.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
