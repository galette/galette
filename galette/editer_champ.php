<?php
/* editer_champ.php
 * - Edition of optional form fields.
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

	include(WEB_ROOT."includes/functions.inc.php"); 
        include(WEB_ROOT."includes/i18n.inc.php");
	include(WEB_ROOT."includes/smarty.inc.php");
        include(WEB_ROOT."includes/dynamic_fields.inc.php");

	$error_detected = array();

	$form_name = get_form_value('form', '');
	if (!isset($all_forms[$form_name]))
		header("location: configurer_fiches.php");

	$field_id = get_numeric_form_value("id", '');
	if ($field_id == '')
		header("location: configurer_fiches.php?form=$form_name");
	$field_type = $DB->GetOne("SELECT field_type FROM $field_types_table WHERE field_id=$field_id");
	$properties = $field_properties[$field_type];
	
	$data = array('id' => $field_id);

	if (isset($_POST["valid"])) {
		$field_name = $_POST["field_name"];
		$field_perm = get_numeric_posted_value("field_perm", '');
		$field_pos = get_numeric_posted_value("field_pos", 0);
		$field_required = get_numeric_posted_value("field_required", '0');
		$field_width = get_numeric_posted_value("field_width", 'NULL');
		$field_height = get_numeric_posted_value("field_height", 'NULL');
		$field_size = get_numeric_posted_value("field_size", 'NULL');
		$field_repeat = get_numeric_posted_value("field_repeat", 'NULL');
		$fixed_values = get_form_value("fixed_values", '');

		if ($field_id != '' && $field_perm != '') {
			$quoted_form_name = $DB->qstr($form_name, get_magic_quotes_gpc());
			$quoted_field_name = $DB->qstr($field_name, get_magic_quotes_gpc());
			$DB->StartTrans();
			$query = "SELECT COUNT(field_id)
				  FROM $field_types_table
				  WHERE NOT field_id=$field_id AND field_form=$quoted_form_name AND
				  	field_name=$quoted_field_name";
			$duplicate = $DB->GetOne($query);
			if ($duplicate != 0)
				$error_detected[] = _T("- Field name already used.");
			$query = "SELECT field_name
				  FROM $field_types_table
				  WHERE field_id=$field_id";
			$old_field_name = db_get_one(&$DB, $query, &$error_detected);
			if ($old_field_name && $field_name != $old_field_name) {
				add_dynamic_translation(&$DB, $field_name, &$error_detected);
				delete_dynamic_translation(&$DB, $old_field_name, &$error_detected);
			}
			if (count($error_detected)==0) {
				$query = "UPDATE $field_types_table
					  SET field_name=$quoted_field_name,
					      field_perm=$field_perm,
					      field_pos=$field_pos,
					      field_required=".db_boolean($field_required).",
					      field_width=$field_width,
					      field_height=$field_height,
					      field_size=$field_size,
					      field_repeat=$field_repeat
					  WHERE field_id=$field_id";
				db_execute(&$DB, $query, &$error_detected);
			}
			$DB->CompleteTrans();
			if ($properties['fixed_values']) {
				$values = array();
				$max_length = 0;
				foreach (explode("\n", $fixed_values) as $val) {
					$val = trim($val);
					$len = strlen($val);
					if ($len > 0) {
						$values[] = $val;
						if ($len > $max_length)
							$max_length = $len;
					}
				}
				$contents_table = fixed_values_table_name($field_id);
				$DB->Execute("DROP TABLE $contents_table");
				db_execute(&$DB, "CREATE TABLE $contents_table (
						  id INTEGER NOT NULL,
						  val varchar($max_length) NOT NULL)", &$error_detected);
				if (count($error_detected) == 0) {
					for ($i = 0; $i < count($values); $i++) {
						$val = $DB->qstr($values[$i], get_magic_quotes_gpc());
						db_execute(&$DB, "INSERT INTO $contents_table VALUES ($i, $val)", &$error_detected);
					}
				}
			} 
		}
		if (count($error_detected)==0)
			header("location: configurer_fiches.php?form=$form_name");
	}
	elseif (isset($_POST["cancel"])) {
		header("location: configurer_fiches.php?form=$form_name");
	} else {
		$query = "SELECT *
			  FROM $field_types_table
			  WHERE field_id=$field_id";
		$result = db_execute(&$DB, $query, &$error_detected);
		if ($result != false) {
			$field_name = $result->fields['field_name'];
			$field_type = $result->fields['field_name'];
			$field_perm = $result->fields['field_perm'];
			$field_pos = $result->fields['field_pos'];
			$field_required = $result->fields['field_required'];
			$field_width = $result->fields['field_width'];
			$field_height = $result->fields['field_height'];
			$field_repeat = $result->fields['field_repeat'];
			$field_size = $result->fields['field_size'];
			$result->Close();
			$fixed_values = '';
			if ($properties['fixed_values']) {
				foreach (get_fixed_values(&$DB, $field_id) as $val)
					$fixed_values .= "$val\n";
			}
		} // $result != false
	}

	$data['id'] = $field_id;
	$data['name'] = htmlentities($field_name, ENT_QUOTES);
	$data['perm'] = $field_perm;
	$data['pos'] = $field_pos;
	$data['required'] = ($field_required == '1');
	$data['width'] = $field_width;
	$data['height'] = $field_height;
	$data['repeat'] = $field_repeat;
	$data['size'] = $field_size;
	$data['fixed_values'] = $fixed_values;

	$tpl->assign("form_name", $form_name);
	$tpl->assign("properties", $properties);
	$tpl->assign("data", $data);
	$tpl->assign("error_detected",$error_detected);

	$tpl->assign("perm_all",$perm_all);
	$tpl->assign("perm_admin",$perm_admin);
	$tpl->assign("perm_names", $perm_names);

	$tpl->assign("field_positions", $field_positions);
	
	$content = $tpl->fetch("editer_champ.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
