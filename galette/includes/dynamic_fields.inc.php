<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic fields functions
 *
 * PHP version 5
 *
 * Copyright © 2004-2010 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Main
 * @package   Galette
 *
 * @author    Laurent Pelecq <laurent.pelecq@soleil.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.63
 */

/** TODO: functions names are *not* PEAR Coding Standards compliant.
Anyways, this file needs a rewrite as an object, we won't spend
too much time on it. */

$field_type_separator = 0;  // Field separator
$field_type_text = 1;       // Multiline text
$field_type_line = 2;       // Single line text
$field_type_choice = 3;     // Fixed choices as combo-box

$field_type_names = array(
    $field_type_separator   => _T("separator"),
    $field_type_text        => _T("free text"),
    $field_type_line        => _T("single line"),
    $field_type_choice      => _T("choice")
);

$field_properties = array(
    $field_type_separator => array(
        'no_data'       => true,
        'with_width'    => false,
        'with_height'   => false,
        'with_size'     => false,
        'multi_valued'  => false,
        'fixed_values'  => false
    ),
    $field_type_text => array(
        'no_data'       => false,
        'with_width'    => true,
        'with_height'   => true,
        'with_size'     => false,
        'multi_valued'  => false,
        'fixed_values'  => false
    ),
    $field_type_line => array(
        'no_data'       => false,
        'with_width'    => true,
        'with_height'   => false,
        'with_size'     => true,
        'multi_valued'  => true,
        'fixed_values'  => false
    ),
    $field_type_choice => array(
        'no_data'       => false,
        'with_width'    => false,
        'with_height'   => false,
        'with_size'     => false,
        'multi_valued'  => false,
        'fixed_values'  => true
    )
);


$perm_all = 0;
$perm_admin = 1;

$perm_names = array($perm_all => _T("all"), $perm_admin => _T("admin"));

$field_pos_middle = 0;
$field_pos_left = 1;
$field_pos_right = 2;
$field_positions = array(
    $field_pos_middle   => _T("middle"),
    $field_pos_left     => _T("left"),
    $field_pos_right    => _T("right")
);

$all_forms = array(
    'adh'       => _T("Members"),
    'contrib'   => _T("Contributions"),
    'trans'     => _T("Transactions")
);

$fields_table = PREFIX_DB . 'dynamic_fields';
$field_types_table = PREFIX_DB . 'field_types';

/**
* Return the table where fixed values are stored
*
* @param string $field_id The field identifier
*
* @return string
*/
function fixed_values_table_name($field_id)
{
    return PREFIX_DB . 'field_contents_' . $field_id;
}

/**
* Returns an array of fixed valued for a field of type 'choice'.
*
* @param AdoDBConnection $DB       AdoDB databasxe connection
* @param string          $field_id field id
*
* @return array
*/
function get_fixed_values($DB, $field_id)
{
    $contents_table = fixed_values_table_name($field_id);
    $query = 'SELECT val FROM ' . $contents_table . ' ORDER BY id';
    $fixed_values = array();
    $result = $DB->Execute($query);
    if ( $result != false ) {
        while ( !$result->EOF ) {
            $fixed_values[] = $result->fields[0];
            $result->MoveNext();
        }
    }
    return $fixed_values;
}

/**
* Set dynamic fields for a given entry
*
* @param AdoDBConnection $DB        AdoDB databasxe connection
* @param string          $form_name Form name in $all_forms
* @param string          $item_id   Key to find entry values
* @param string          $field_id  Id assign to the field on creation
* @param string          $val_index For multi-valued fields, it is the rank
                            of this particular value
* @param string          $field_val The value itself
*
* @return boolean
*/
function set_dynamic_field(
    $DB, $form_name, $item_id, $field_id, $val_index, $field_val
) {
    global $fields_table;
    $ret = false;
    $quoted_form_name = $DB->qstr($form_name, get_magic_quotes_gpc());
    $DB->StartTrans();
    $query = 'SELECT COUNT(*) FROM ' . $fields_table .
        ' WHERE item_id=' . $item_id .
        ' AND field_form=' . $quoted_form_name .
        ' AND field_id=' . $field_id .
        ' AND val_index=' . $val_index;
    $count = $DB->GetOne($query);
    if ( isset($count) ) {
        if ( $field_val == '' ) {
            $query = 'DELETE FROM '. $fields_table .
                ' WHERE item_id=' . $item_id .
                ' AND field_form=' . $quoted_form_name .
                ' AND field_id=' . $field_id .
                ' AND val_index=' . $val_index;
        } else {
            $value = $DB->qstr($field_val, get_magic_quotes_gpc());
            if ( $count > 0 ) {
                $query = 'UPDATE ' . $fields_table .
                    ' SET field_val=' . $value .
                    ' WHERE item_id=' . $item_id .
                    ' AND field_form=' . $quoted_form_name .
                    ' AND field_id=' . $field_id .
                    ' AND val_index=' . $val_index;
            } else {
                $query = 'INSERT INTO ' . $fields_table .
                    ' (item_id, field_form, field_id, val_index, field_val) VALUES (' .
                    $item_id . ', ' . $quoted_form_name . ', ' . $field_id .
                    ', ' . $val_index . ', ' . $value . ')';
            }
        }
        $result = $DB->Execute($query);
        $ret = ($result != false);
    }
    $DB->CompleteTrans();
    return $ret;
}

/**
* Set all dynamic fields for a given entry
*
* @param AdoDBConnection $DB         AdoDB databasxe connection
* @param string          $form_name  Form name in $all_forms
* @param string          $item_id    Key to find entry values
* @param array           $all_values Values as returned by
                            extract_posted_dynamic_fields.
*
* @return boolean
*/
function set_all_dynamic_fields($DB, $form_name, $item_id, $all_values)
{
    $ret = true;
    while ( list($field_id, $contents) = each($all_values) ) {
        while ( list($val_index, $field_val) = each($contents) ) {
            if ( !set_dynamic_field($DB, $form_name, $item_id, $field_id, $val_index, $field_val) ) {
                $ret = false;
            }
        }
    }
    return $ret;
}

/**
* Get dynamic fields for one entry
* It returns an 2d-array with field id as first key and value index as second key.
*
* @param AdoDBConnection $DB        AdoDB databasxe connection
* @param string          $form_name Form name in $all_forms
* @param string          $item_id   Key to find entry values
* @param boolean         $quote     If true, values are quoted for HTML output
*
* @return 2d-array with field id as first key and value index as second key.
*/
function get_dynamic_fields($DB, $form_name, $item_id, $quote)
{
    global $field_properties, $fields_table, $field_types_table;
    $quoted_form_name = $DB->qstr($form_name, get_magic_quotes_gpc());
    $DB->StartTrans();
    $query =  'SELECT field_id, val_index, field_val FROM ' . $fields_table .
        ' WHERE item_id=' . $item_id . ' AND field_form=' . $quoted_form_name;
    $result = $DB->Execute($query);
    if ( $result == false ) {
        return false;
    }
    $dyn_fields = array();
    while ( !$result->EOF ) {
        $field_id = $result->fields['field_id'];
        $value = $result->fields['field_val'];
        if ( $quote ) {
            $field_type = $DB->GetOne(
                'SELECT field_type FROM ' . $field_types_table .
                ' WHERE field_id=' . $field_id
            );
            if ($field_properties[$field_type]['fixed_values']) {
                $choices = get_fixed_values($DB, $field_id);
                $value = $choices[$value];
            }
        }
        $dyn_fields[$field_id][$result->fields['val_index']] = $value;
        $result->MoveNext();
    }
    $result->Close();
    $DB->CompleteTrans();
    return $dyn_fields;
}

/**
* Extract posted values for dynamic fields
*
* @param AdoDBConnection $DB       AdoDB databasxe connection
* @param array           $post     Array containing the posted values
* @param array           $disabled Array with fields that are discarded as key
*
* @return array
*/
function extract_posted_dynamic_fields($DB, $post, $disabled)
{
    $dyn_fields = array();
    while ( list($key, $value) = each($post) ) {
        // if the field is enabled, check it
        if ( !isset($disabled[$key]) ) {
            if (substr($key, 0, 11) == 'info_field_') {
                list($field_id, $val_index) = explode('_', substr($key, 11));
                if ( is_numeric($field_id) && is_numeric($val_index) ) {
                    $dyn_fields[$field_id][$val_index] = $value;
                }
            }
        }
    }
    return $dyn_fields;
}

/**
* Returns an array of all kind of fields to display.
*
* @param AdoDBConnection $DB         AdoDB databasxe connection
* @param string          $form_name  Form name in $all_forms
* @param array           $all_values Values as returned by
                            extract_posted_dynamic_fields
* @param array           $disabled   Array that will be filled with fields
                            that are discarded as key
* @param boolean         $edit       Must be true if prepared for edition
*
* @return array
*/
function prepare_dynamic_fields_for_display(
    $DB, $form_name, $all_values, $disabled, $edit
) {
    global $field_properties, $field_types_table, $perm_admin, $login;
    $quoted_form_name = $DB->qstr($form_name, get_magic_quotes_gpc());
    $query = 'SELECT * FROM ' . $field_types_table . ' WHERE field_form=' .
        $quoted_form_name . ' ORDER BY field_index';
    $result = &$DB->Execute($query);
    $dyn_fields = array();
    $extra = $edit ? 1 : 0;

    if ( !$result ) {
        return false;
    }
    while ( !$result->EOF ) {
        $field_id = $result->fields['field_id'];
        // disable admin fields when logged as member
        if ( !$login->isAdmin() && $result->fields['field_perm'] == $perm_admin ) {
            $disabled[$field_id] = 'disabled';
        }
        $cur_fields = &$result->fields;
        $cur_fields['field_name'] = _T($cur_fields['field_name']);
        $properties = $field_properties[$result->fields['field_type']];
        if ( $properties['multi_valued'] ) {
            if ( $cur_fields['field_repeat'] == 0 ) { // Infinite multi-valued field
                if ( isset($all_values[$cur_fields['field_id']]) ) {
                    $nb_values = count($all_values[$cur_fields['field_id']]);
                } else {
                    $nb_values = 0;
                }
                if ( isset($all_values) ) {
                    $cur_fields['field_repeat'] = $nb_values + $extra;
                } else {
                    $cur_fields['field_repeat'] = 1;
                }
            }
        } else {
            $cur_fields['field_repeat'] = 1;
            if ( $properties['fixed_values'] ) {
                $cur_fields['choices'] = get_fixed_values($DB, $field_id);
            }
        }
        $dyn_fields[] = $cur_fields;
        $result->MoveNext();
    }
    $result->Close();
    return $dyn_fields;
}

?>
