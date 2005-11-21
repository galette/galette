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
	$field_type_text = 1;		// Multiline text
	$field_type_line = 2;		// Single line text
	$field_type_choice = 3;		// Fixed choices as combo-box

	$field_type_names = array($field_type_separator => _T('separator'),
				  $field_type_text => _T('free text'),
				  $field_type_line => _T('single line'),
				  $field_type_choice => _T('choice'));

	$field_properties = array(
		$field_type_separator => array('no_data' => true,
					  'with_width' => false,
					  'with_height' => false,
					  'with_size' => false,
					  'multi_valued' => false,
					  'fixed_values' => false),
		$field_type_text => array('no_data' => false,
					  'with_width' => true,
					  'with_height' => true,
					  'with_size' => false,
					  'multi_valued' => false,
					  'fixed_values' => false),
		$field_type_line => array('no_data' => false,
					  'with_width' => true,
					  'with_height' => false,
					  'with_size' => true,
					  'multi_valued' => true,
					  'fixed_values' => false),
		$field_type_choice => array('no_data' => false,
					    'with_width' => false,
					    'with_height' => false,
					    'with_size' => false,
					    'multi_valued' => false,
					    'fixed_values' => true)
	);


	$perm_all = 0;
	$perm_admin = 1;

	$perm_names = array($perm_all => _T('all'), $perm_admin => _T('admin'));

	$field_pos_middle = 0;
	$field_pos_left = 1;
	$field_pos_right = 2;
	$field_positions = array($field_pos_middle => _T('middle'),
				 $field_pos_left => _T('left'),
				 $field_pos_right => _T('right'));

	$all_forms = array(
		'adh' => _T("Members"),
		'contrib' => _T("Contributions"),
		'trans' => _T("Transactions")
	);

	$fields_table = PREFIX_DB."dynamic_fields";
	$field_types_table = PREFIX_DB."field_types";

	// Return the table where fixed values are stored
	function fixed_values_table_name($field_id) {
		return PREFIX_DB."field_contents_$field_id";
	}

	// Returns an array of fixed valued for a field of type 'choice'.
	function get_fixed_values($DB, $field_id) {
		$contents_table = fixed_values_table_name($field_id);
		$query = "SELECT val FROM $contents_table ORDER BY id";
		$fixed_values = array();
		$result = $DB->Execute($query);
		if ($result != false) {
			while(!$result->EOF) {
				$fixed_values[] = $result->fields[0];
				$result->MoveNext();
			}
		}
		return $fixed_values;
	}

	// Set dynamic fields for a given entry
	// $form_name: Form name in $all_forms
	// $item_id: Key to find entry values.
	// $field_id: Id assign to the field on creation.
	// $val_index: For multi-valued fields, it is the rank of this particular value.
	// $field_val: The value itself.
	function set_dynamic_field($DB, $form_name, $item_id, $field_id, $val_index, $field_val) {
		global $fields_table;
		$ret = false;
		$quoted_form_name = $DB->qstr($form_name, get_magic_quotes_gpc());
		$DB->StartTrans();
		$query = "SELECT COUNT(*) FROM $fields_table
			  WHERE item_id=$item_id AND field_form=$quoted_form_name AND
			  	field_id=$field_id AND val_index=$val_index";
		$count = $DB->GetOne($query);
		if (isset($count)) {
		    if ($field_val == "")
			$query = "DELETE FROM $fields_table
				  WHERE item_id=$item_id AND field_form=$quoted_form_name AND
				        field_id=$field_id AND val_index=$val_index";
		    else {
			$value = $DB->qstr($field_val, get_magic_quotes_gpc());
			if ($count > 0)
			    $query = "UPDATE $fields_table SET field_val=$value
			    	      WHERE item_id=$item_id AND field_form=$quoted_form_name AND
					    field_id=$field_id AND val_index=$val_index";
			else
			    $query = "INSERT INTO $fields_table (item_id, field_form, field_id, val_index, field_val)
				      VALUES ($item_id, $quoted_form_name, $field_id, $val_index, $value)";
		    }
		    $result = $DB->Execute($query);
		    $ret = ($result != false);
		}
		$DB->CompleteTrans();
		return $ret;
	}

	// Set all dynamic fields for a given entry
	// $form_name: Form name in $all_forms
	// $item_id: Key to find entry values.
	// $all_values: Values as returned by extract_posted_dynamic_fields.
	function set_all_dynamic_fields($DB, $form_name, $item_id, $all_values) {
		$ret = true;
		while (list($field_id, $contents)=each($all_values))
			while (list($val_index, $field_val)=each($contents))
				if (!set_dynamic_field($DB, $form_name, $item_id, $field_id, $val_index, $field_val))
					$ret = false;
		return $ret;
	}

	// Get dynamic fields for one entry
	//
	// It returns an 2d-array with field id as first key and value index as second key.
	// $form_name: Form name in $all_forms
	// $item_id: Key to find entry values.
	// $quote: If true, values are quoted for HTML output.
	function get_dynamic_fields($DB, $form_name, $item_id, $quote) {
		global $field_properties, $fields_table, $field_types_table;
		$quoted_form_name = $DB->qstr($form_name, get_magic_quotes_gpc());
		$DB->StartTrans();
		$query =  "SELECT field_id, val_index, field_val ".
			  "FROM $fields_table ".
			  "WHERE item_id=$item_id AND field_form=$quoted_form_name";
		$result = $DB->Execute($query);
		if ($result == false)
			return false;
		$dyn_fields = array();
		while (!$result->EOF)
		{
			$field_id = $result->fields['field_id'];
			$value = $result->fields['field_val'];
			if ($quote) {
				$field_type = $DB->GetOne("SELECT field_type
							   FROM $field_types_table
							   WHERE field_id=$field_id");
				if ($field_properties[$field_type]['fixed_values']) {
					$choices = get_fixed_values($DB, $field_id);
					$value = $choices[$value];
				}
				$value = htmlentities($value, ENT_QUOTES);
			}
			$dyn_fields[$field_id][$result->fields['val_index']] = $value;
			$result->MoveNext();
		}
		$result->Close();
		$DB->CompleteTrans();
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
					list ($field_id, $val_index) = explode ('_', substr($key,11));
					if (is_numeric($field_id) && is_numeric($val_index))
					$dyn_fields[$field_id][$val_index] = $value;
				}
			}
		}
		return $dyn_fields;
	}

	// Returns an array of all kind of fields to display.
	// $form_name: Form name in $all_forms
	// $admin_status: Must be true for an admin or false otherwise.
	// $all_values: Values as returned by extract_posted_dynamic_fields.
	// $disabled: Array that will be filled with fields that are discarded as key.
	// $edit: Must be true if prepared for edition.
	function prepare_dynamic_fields_for_display($DB, $form_name, $admin_status, $all_values, $disabled, $edit) {
		global $field_properties, $field_types_table;
		$quoted_form_name = $DB->qstr($form_name, get_magic_quotes_gpc());
		$query = "SELECT *
			  FROM $field_types_table
			  WHERE field_form=$quoted_form_name
			  ORDER BY field_index";
		$result = &$DB->Execute($query);
		$dyn_fields = array();
		$extra = $edit ? 1 : 0;

		if (!$result)
			return false;
		while (!$result->EOF)
		{
			$field_id = $result->fields['field_id'];
			// disable admin fields when logged as member
			if ($admin_status!=1 && $result->fields['perm']==$perm_admin)
				$disabled[$field_id] = 'disabled';
			$cur_fields = &$result->fields;
			$cur_fields['field_name'] = _T($cur_fields['field_name']);
			$properties = $field_properties[$result->fields['field_type']];
			if ($properties['multi_valued']) {
				if ($cur_fields['field_repeat'] == 0) { // Infinite multi-valued field
					if (isset($all_values[$cur_fields['field_id']]))
						$nb_values = count($all_values[$cur_fields['field_id']]);
					else
						$nb_values = 0;
					if (isset($all_values))
						$cur_fields['field_repeat'] = $nb_values + $extra;
					else
						$cur_fields['field_repeat'] = 1;
				}
			} else {
				$cur_fields['field_repeat'] = 1;
				if ($properties['fixed_values'])
					$cur_fields['choices'] = get_fixed_values($DB, $field_id);
			}
			$dyn_fields[] = $cur_fields;
			$result->MoveNext();
		}
		$result->Close();
		return $dyn_fields;
	}

?>
