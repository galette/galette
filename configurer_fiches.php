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
        include(WEB_ROOT."includes/dynamic_fields.inc.php");

	if ($_SESSION["logged_status"]==0)
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		header("location: voir_adherent.php");

	$error_detected = array();

	$form_name = get_form_value('form', '');
	if (!isset($form_desc[$form_name]))
		$form_name = '';

	if ($form_name != '') {
		$form_not_set = false;
		$field_type_table = $form_desc[$form_name]['type_table'];
		$fields_table = $form_desc[$form_name]['data_table'];
	}

	if ($form_name == '') {

		$form_title = '';
		$form_keys = array_keys($form_desc);
		$form_name = $form_keys[0];  // Default value for selection
		$all_forms = array();
		foreach ($form_desc as $key => $val)
			$all_forms[$key] = $val['title'];
		$tpl->assign("all_forms", $all_forms);
	
	} else {

		$form_title = $form_desc[$form_name]['title'];

		if (isset($_POST["valid"])) {
			if ($_POST["field_type"] != $field_type_separator &&
			   (!isset($_POST["field_name"]) || $_POST["field_name"] == "")) {
				$error_detected[] = _T("- The field name cannot be void.");
			} else {
				$field_name = $_POST["field_name"];
				$field_perm = $_POST["field_perm"];
				$field_type = $_POST["field_type"];
				$field_repeat = $_POST["field_repeat"];
				$requete = "SELECT COUNT(*) + 1 AS idx FROM $field_type_table";
				$idx = $DB->GetOne($requete);
				$requete = "INSERT INTO $field_type_table
					    ( field_index, field_name, field_perm, field_type, field_repeat )
					    VALUES ( $idx, ".$DB->qstr($field_name, true).", $field_perm, $field_type, $field_repeat)";
				if ($DB->Execute($requete) == false)
					$error_detected[] = _T("- Database error: ").$DB->ErrorMsg();
			}
		}
		else
		{
			$action = "";
			$field_id = "";
			foreach (array("sup", "up", "down") as $varname)
			{
				if (isset($_GET[$varname]) && is_numeric($_GET[$varname]))
				{
					$action = $varname;
					$field_id = (integer)$_GET[$varname];
					break;
				}
			}
			if ($action != "") {
				$DB->StartTrans();
				$res = $DB->Execute("SELECT field_index FROM $field_type_table WHERE field_id=$field_id");
				if (!$res->EOF)
				{
					$old_rank = $res->fields[0];
					if ($action == "sup")
					{
						$DB->Execute("UPDATE $field_type_table SET field_index=field_index-1 WHERE field_index > $old_rank");
						$DB->Execute("DELETE FROM $fields_table WHERE field_id=$field_id");
						$DB->Execute("DELETE FROM $field_type_table WHERE field_id=$field_id");
					}
					elseif ($action != "")
					{
						$direction = $action == "up" ? -1: 1;
						$new_rank = $old_rank + $direction;
						$DB->Execute("UPDATE $field_type_table SET field_index=$old_rank WHERE field_index=$new_rank");
						$DB->Execute("UPDATE $field_type_table SET field_index=$new_rank WHERE field_id=$field_id");
					}
				}
				$DB->CompleteTrans();
			}
		}
	
		$request = "SELECT field_id, field_index, field_name, field_perm, field_type, field_repeat FROM $field_type_table ORDER BY field_index";
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
				case $field_type_separator:
					$dyn_fields[$count]['type'] = _T('separator');
					break;
				case $field_type_text:
					$dyn_fields[$count]['type'] = _T('free text');
					break;
				case $field_type_line:
					$dyn_fields[$count]['type'] = _T('single line');
					break;
				default:
					$dyn_fields[$count]['type'] = _T('unknown');
			}
			$dyn_fields[$count]['repeat'] = $result->fields[5];
			$result->MoveNext();
			++$count;
		}
		$result->Close();
	
		$tpl->assign("perm_all",$perm_all);
		$tpl->assign("perm_admin",$perm_admin);
		$tpl->assign("field_type_separator",$field_type_separator);
		$tpl->assign("field_type_text",$field_type_text);
		$tpl->assign("field_type_line",$field_type_line);
		
		$tpl->assign("dyn_fields",$dyn_fields);
		$tpl->assign("error_detected",$error_detected);
	
	} // $form_name == ''

	$tpl->assign("form_name", $form_name);
	$tpl->assign("form_title", $form_title);

	$content = $tpl->fetch("configurer_fiches.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
