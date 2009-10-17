<?php 

// Copyright © 2004 Laurent Pelecq
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
 * Configuration des fiches
 *
 * @package Galette
 * 
 * @author     Laurent Pelecq <laurent.pelecq@soleil.org>
 * @copyright  2004 Laurent Pelecq
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.62
 */

/** @ignore */
require_once('includes/galette.inc.php');

if( !$login->isLogged() )
{
	header("location: index.php");
	die();
}
if( !$login->isAdmin() )
{
	header("location: voir_adherent.php");
	die();
}
	
include(WEB_ROOT."includes/dynamic_fields.inc.php");

$error_detected = array();

$form_name = ( isset($_GET['form']) )?$_GET['form']:'adh';
if( isset($_POST['form']) && trim($_POST['form']) != '' ) $form_name = $_POST['form'];

	if (!isset($all_forms[$form_name]))
		$form_name = '';

	$form_not_set = ($form_name == '');
	
	if ($form_name == '') {

		$form_title = '';

	} else {

		$form_title = $all_forms[$form_name];

		$quoted_form_name = $DB->qstr($form_name, get_magic_quotes_gpc());

		if (isset($_POST["valid"])) {
			if ($_POST["field_type"] != $field_type_separator &&
			   (!isset($_POST["field_name"]) || $_POST["field_name"] == "")) {
				$error_detected[] = _T("- The name field cannot be void.");
			} else {
				$field_name = $_POST["field_name"];
				$field_perm = $_POST["field_perm"];
				$field_type = $_POST["field_type"];
				$field_required = $_POST["field_required"];
				$field_pos = $_POST["field_pos"];
				$query = "SELECT COUNT(*) + 1 AS idx
					  FROM $field_types_table
					  WHERE field_form=$quoted_form_name";
				$idx = db_get_one($DB, $query, $error_detected);
				if ($idx != false) {
					$DB->StartTrans();
					$quoted_field_name = $DB->qstr($field_name, get_magic_quotes_gpc());
					$query = "INSERT INTO $field_types_table
						    (field_index, field_form, field_name, field_perm, field_type, field_required, field_pos)
						  VALUES ($idx, $quoted_form_name, $quoted_field_name, $field_perm, $field_type, $field_required, $field_pos)";
					db_execute($DB, $query, $error_detected);
					if ($field_type != $field_type_separator && count($error_detected) == 0) {
						$field_id = get_last_auto_increment($DB, $field_types_table, "field_id", $error_detected);
						header("location: editer_champ.php?form=$form_name&id=$field_id");
					}
					if ($field_name != '')
						add_dynamic_translation($DB, $field_name, $error_detected);
					$DB->CompleteTrans();
				}
			}
		}
		else
		{
			$action = "";
			$field_id = "";
			foreach (array("del", "up", "down") as $varname)
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
				$query = "SELECT field_type, field_index FROM $field_types_table
					  WHERE field_id=$field_id AND field_form=$quoted_form_name";
				$res = db_execute($DB, $query, $error_detected);
				if ($res != false && !$res->EOF)
				{
					$old_rank = $res->fields['field_index'];
					$query_list = array();
					if ($action == "del")
					{
						$query_list[] = "UPDATE $field_types_table
								 SET field_index=field_index-1
								 WHERE field_index > $old_rank AND
								       field_form=$quoted_form_name";
						$query_list[] = "DELETE FROM $fields_table
								 WHERE field_id=$field_id AND
								       field_form=$quoted_form_name";
						$query_list[] = "DELETE FROM $field_types_table
								 WHERE field_id=$field_id AND
								       field_form=$quoted_form_name";
						if ($field_properties[$res->fields['field_type']]['fixed_values']) {
							$contents_table = fixed_values_table_name($field_id);
							$query_list[] = "DROP TABLE $contents_table";
						}
						$query = "SELECT field_name
							  FROM $field_types_table
							  WHERE field_id=$field_id";
						$field_name = db_get_one($DB, $query, $error_detected);
						delete_dynamic_translation($DB, $field_name, $error_detected);
					}
					elseif ($action != "")
					{
						$direction = $action == "up" ? -1: 1;
						$new_rank = $old_rank + $direction;
						$query_list[] = "UPDATE $field_types_table
								 SET field_index=$old_rank
								 WHERE field_index=$new_rank AND
								       field_form=$quoted_form_name";
						$query_list[] = "UPDATE $field_types_table
								 SET field_index=$new_rank
								 WHERE field_id=$field_id AND
								       field_form=$quoted_form_name";
					}
					foreach($query_list as $query)
						db_execute($DB, $query, $error_detected);
				}
				$DB->CompleteTrans();
			}
		}
	
		$query = "SELECT *
			  FROM $field_types_table
			  WHERE field_form=$quoted_form_name
			  ORDER BY field_index";
		$result = db_execute($DB, $query, $error_detected);
		if ($result != false) {
			$count = 0;
			$dyn_fields = array();
			while (!$result->EOF)
			{
				$dyn_fields[$count]['id'] = $result->fields['field_id'];
				$dyn_fields[$count]['index'] = $result->fields['field_index'];
				$dyn_fields[$count]['name'] = $result->fields['field_name'];
				$dyn_fields[$count]['perm'] = $perm_names[$result->fields['field_perm']];
				$dyn_fields[$count]['type'] = $field_type_names[$result->fields['field_type']];
				$dyn_fields[$count]['required'] = ($result->fields['field_required'] == '1');
				$dyn_fields[$count]['pos'] = $field_positions[$result->fields['field_pos']];
				$result->MoveNext();
				++$count;
			}
			$result->Close();
		} // $result != false

		$tpl->assign("perm_names", $perm_names);
		$tpl->assign("field_type_names", $field_type_names);
		
		$tpl->assign("dyn_fields",$dyn_fields);
	
	} // $form_name == ''

$tpl->assign("all_forms", $all_forms);
$tpl->assign("error_detected",$error_detected);
$tpl->assign("form_name", $form_name);
$tpl->assign("form_title", $form_title);
$tpl->assign("perm_names", $perm_names);
$tpl->assign("field_type_names", $field_type_names);
$tpl->assign("field_positions", $field_positions);
$content = $tpl->fetch("configurer_fiches.tpl");
$tpl->assign("content",$content);
$tpl->display("page.tpl");
?>
