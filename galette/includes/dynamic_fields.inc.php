<? 
/* categories.inc.php
 * - Categories configuration
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
 
	$field_type_separator = 0;	// Field separator
	$field_type_text = 1;	// Multiline text
	$field_type_line = 2;	// Single line text
	
	$perm_all = 0;
	$perm_admin = 1;

	$form_desc = array(
		'adh' => array( 'title' => _T("Members"),
				'data_table' => PREFIX_DB."adh_fields",
				'type_table' => PREFIX_DB."adh_field_type",
				'id_key_name' => 'id_adh' )
	);

	// Set dynamic fields for a given entry
	// $form_name: Form name in $form_desc
	// $entry_id: Key to find entry values.
	// $field_id: Id assign to the field on creation.
	// $val_index: For multi-valued fields, it is the rank of this particular value.
	// $field_val: The value itself.
	function set_dynamic_field($DB, $form_name, $entry_id, $field_id, $val_index, $field_val) {
		global $form_desc;
		$id_key_name = $form_desc[$form_name]['id_key_name'];
		$table = $form_desc[$form_name]['data_table'];
		$ret = false;
		$DB->StartTrans();
		$query = "SELECT COUNT(*) FROM $table WHERE $id_key_name=$entry_id AND field_id=$field_id AND val_index=$val_index";
		$count = $DB->GetOne($query);
		if (isset($count)) {
		    if ($field_val == "")
			$query = "DELETE FROM $table WHERE $id_key_name=$entry_id AND field_id=$field_id AND val_index=$val_index";
		    else {
			$value = $DB->qstr($field_val, true);
			if ($count > 0)
			    $query = "UPDATE $table SET field_val=$value WHERE $id_key_name=$entry_id AND field_id=$field_id AND val_index=$val_index";
			else
			    $query = "INSERT INTO $table ($id_key_name, field_id, val_index, field_val) VALUES ($entry_id, $field_id, $val_index, $value)";
		    }
		    $result = $DB->Execute($query);
		    if (!$result)
		    	print "$query: ".$DB->ErrorMsg()."<br>";
		    $ret = ($result != false);
		}
		$DB->CompleteTrans();
		return $ret;
	}

	// Set all dynamic fields for a given entry
	// $form_name: Form name in $form_desc
	// $id: Key to find values. It depends on the table and must be the only primary key in $table.
	// $all_values: Values as returned by extract_posted_dynamic_fields.
	function set_all_dynamic_fields($DB, $form_name, $id, $all_values) {
		while (list($field_id, $contents)=each($all_values))
			while (list($val_index, $field_val)=each($contents))
				set_dynamic_field($DB, $form_name, $id, $field_id, $val_index, $field_val);
	}

	// Get dynamic fields for one entry
	//
	// It returns an 2d-array with field id as first key and value index as second key.
	// $form_name: Form name in $form_desc
	// $id: Key to find values. It depends on the table and must be the only primary key in $table.
	// $quote: If true, values are quoted for HTML output.
	function get_dynamic_fields($DB, $form_name, $id, $quote) {
		global $form_desc;
		$id_key_name = $form_desc[$form_name]['id_key_name'];
		$table = $form_desc[$form_name]['data_table'];
		$query =  "SELECT field_id, val_index, field_val ".
			  "FROM $table ".
			  "WHERE $id_key_name=$id";
		$result = &$DB->Execute($query);
		$dyn_fields = array();
		while (!$result->EOF)
		{
			$value = $result->fields['field_val'];
			if ($quote)
				$value = htmlentities($value, ENT_QUOTES);
			$dyn_fields[$result->fields['field_id']][$result->fields['val_index']] = $value;
			$result->MoveNext();
		}
		$result->Close();
		return $dyn_fields;
	}

	// Extract posted values for dynamic fields
	// $post: Array containing the posted values
	// $disabled: Array with fields that are discarded as key.
	function extract_posted_dynamic_fields($DB, $post, $disabled) {
		$dyn_fields = array();
		while (list($key, $value) = each($post))
		{
			// if the field is enabled, check it
			if (!isset($disabled[$key]))
			{
				if (substr($key,0,11)=='info_field_')
				{
					list ($id, $val_index) = explode ('_', substr($key,11));
					if (is_numeric($id) && is_numeric($val_index))
					$dyn_fields[$id][$val_index] = $value;
				}
			}
		}
		return $dyn_fields;
	}

	// Returns an array of all value to display.
	// $form_name: Form name in $form_desc
	// $admin_status: Must be true for an admin or false otherwise.
	// $all_values: Values as returned by extract_posted_dynamic_fields.
	// $disabled: Array that will be filled with fields that are discarded as key.
	// $edit: Must be true if prepared for edition.
	function prepare_dynamic_fields_for_display($DB, $form_name, $admin_status, $all_values, $disabled, $edit) {
		global $form_desc;
		$id_key_name = $form_desc[$form_name]['id_key_name'];
		$type_table = $form_desc[$form_name]['type_table'];
		$query = "SELECT * ".
			 "FROM $type_table ".
			 "ORDER BY field_index";
		$result = &$DB->Execute($query);
		$dyn_fields = array();
		$extra = $edit ? 1 : 0;

		while (!$result->EOF)
		{
			// disable admin fields when logged as member
			if ($admin_status!=1 && $result->fields['perm']==$perm_admin)
				$disabled[$result->fields['field_id']] = 'disabled';
			$cur_fields = &$result->fields;
			if ($cur_fields['field_repeat'] == 0) { // Infinite multi-valued field
				$nb_values = count($all_values[$cur_fields['field_id']]);
				if (isset($all_values))
					$cur_fields['field_repeat'] = $nb_values + $extra;
				else
					$cur_fields['field_repeat'] = 1;
			}
			$dyn_fields[] = $cur_fields;
			$result->MoveNext();
		}
		$result->Close();
		return $dyn_fields;
	}

?>
