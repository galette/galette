<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic fields functions
 *
 * PHP version 5
 *
 * Copyright © 2004-2012 The Galette Team
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
 * @copyright 2004-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.63
 */

require_once WEB_ROOT . 'classes/dynamic_fields.class.php';

/** TODO: functions names are *not* PEAR Coding Standards compliant.
Anyways, this file needs a rewrite as an object, we won't spend
too much time on it. */

$field_type_names = array(
    DynamicFields::SEPARATOR   => _T("separator"),
    DynamicFields::TEXT        => _T("free text"),
    DynamicFields::LINE        => _T("single line"),
    DynamicFields::CHOICE      => _T("choice")
);

$field_properties = array(
    DynamicFields::SEPARATOR => array(
        'no_data'       => true,
        'with_width'    => false,
        'with_height'   => false,
        'with_size'     => false,
        'multi_valued'  => false,
        'fixed_values'  => false
    ),
    DynamicFields::TEXT => array(
        'no_data'       => false,
        'with_width'    => true,
        'with_height'   => true,
        'with_size'     => false,
        'multi_valued'  => false,
        'fixed_values'  => false
    ),
     DynamicFields::LINE => array(
        'no_data'       => false,
        'with_width'    => true,
        'with_height'   => false,
        'with_size'     => true,
        'multi_valued'  => true,
        'fixed_values'  => false
    ),
     DynamicFields::CHOICE => array(
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
* @param string          $field_id field id
*
* @return array
*/
function get_fixed_values($field_id)
{
    global $zdb, $log;

    try {
        $val_select = new Zend_Db_Select($zdb->db);

        $val_select->from(DynamicFields::getFixedValuesTableName($field_id), 'val')
            ->order('id');

        $results = $val_select->query()->fetchAll();
        $fixed_values = array();
        if ( $results ) {
            foreach ( $results as $val ) {
                $fixed_values[] = $val->val;
            }
        }
        return $fixed_values;
    } catch (Exception $e) {
        /** TODO */
        $log->log(
            'get_fixed_values | ' . $e->getMessage(),
            PEAR_LOG_WARNING
        );
        $log->log(
            'Query was: ' . $val_select->__toString() . ' ' . $e->__toString(),
            PEAR_LOG_ERR
        );
    }
}

/**
* Set dynamic fields for a given entry
*
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
    $form_name, $item_id, $field_id, $val_index, $field_val
) {
    global $zdb, $log, $fields_table;
    $ret = false;

    try {
        $zdb->db->beginTransaction();

        $select = new Zend_Db_Select($zdb->db);
        $select->from(
            $fields_table,
            array('cnt' => 'count(*)')
        )->where('item_id = ?', $item_id)
            ->where('field_form = ?', $form_name)
            ->where('field_id = ?', $field_id)
            ->where('val_index = ?', $val_index);

        $count = $select->query()->fetchColumn();

        if ( $count > 0 ) {
            if ( trim($field_val) == '' ) {
                $zdb->db->delete(
                    $fields_table,
                    $select->getPart(Zend_Db_Select::WHERE)
                );
            } else {
                $zdb->db->update(
                    $fields_table,
                    array('field_val' => $field_val),
                    $select->getPart(Zend_Db_Select::WHERE)
                );
            }
        } else {
            $values = array(
                'item_id'    => $item_id,
                'field_form' => $form_name,
                'field_id'   => $field_id,
                'val_index'  => $val_index,
                'field_val'  => $field_val
            );

            $zdb->db->insert(
                $fields_table,
                $values
            );
        }

        $zdb->db->commit();
        return true;
    } catch (Exception $e) {
        /** FIXME */
        $zdb->db->rollBack();
        $log->log(
            'An error occured storing dynamic field. Form name: ' . $form_name .
            '; item_id:' . $item_id . '; field_id: ' . $field_id .
            '; val_index: ' . $val_index . '; field_val:' . $field_val,
            PEAR_LOG_ERR
        );
        return false;
    }
}

/**
* Set all dynamic fields for a given entry
*
* @param string          $form_name  Form name in $all_forms
* @param string          $item_id    Key to find entry values
* @param array           $all_values Values as returned by
                            extract_posted_dynamic_fields.
*
* @return boolean
*/
function set_all_dynamic_fields($form_name, $item_id, $all_values)
{
    $ret = true;
    while ( list($field_id, $contents) = each($all_values) ) {
        while ( list($val_index, $field_val) = each($contents) ) {
            if ( !set_dynamic_field($form_name, $item_id, $field_id, $val_index, $field_val) ) {
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
* @param string          $form_name Form name in $all_forms
* @param string          $item_id   Key to find entry values
* @param boolean         $quote     If true, values are quoted for HTML output
*
* @return 2d-array with field id as first key and value index as second key.
*/
function get_dynamic_fields($form_name, $item_id, $quote)
{
    global $zdb, $log, $field_properties;

    try {
        $select = new Zend_Db_Select($zdb->db);

        $select->from(PREFIX_DB . DynamicFields::TABLE)
            ->where('item_id = ?', $item_id)
            ->where('field_form = ?', $form_name);

        $result = $select->query()->fetchAll();

        if ( count($result) > 0 ) {
            $dyn_fields = array();
            $types_select = new Zend_Db_Select($zdb->db);
            $types_select->from(PREFIX_DB . DynamicFields::TYPES_TABLE, 'field_type')
                ->where(DynamicFields::TYPES_PK . ' = :fieldid');
            $stmt = $zdb->db->prepare($types_select);
            foreach ($result as $f) {
                $value = $f->field_val;
                if ( $quote ) {
                    $stmt->bindValue(':fieldid', $f->field_id, PDO::PARAM_INT);
                    if ( $stmt->execute() ) {
                        $field_type = $stmt->fetch()->field_type;
                        if ($field_properties[$field_type]['fixed_values']) {
                            $choices = get_fixed_values($f->field_id);
                            $value = $choices[$value];
                        }
                    }
                }
                $dyn_fields[$f->field_id][$f->val_index] = $value;
            }
            return $dyn_fields;
        } else {
            return false;
        }
    } catch (Exception $e) {
        /** TODO */
        $log->log(
            'get_dynamic_fields | ' . $e->getMessage(),
            PEAR_LOG_WARNING
        );
        $log->log(
            'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
            PEAR_LOG_ERR
        );
    }
}

/**
* Extract posted values for dynamic fields
*
* @param array           $post     Array containing the posted values
* @param array           $disabled Array with fields that are discarded as key
*
* @return array
*/
function extract_posted_dynamic_fields($post, $disabled)
{
    if ( $post != null ) {
        $dyn_fields = array();
        while ( list($key, $value) = each($post) ) {
            // if the field is enabled, check it
            if ( !isset($disabled[$key]) ) {
                if (substr($key, 0, 11) == 'info_field_') {
                    list($field_id, $val_index) = explode('_', substr($key, 11));
                    if ( is_numeric($field_id)
                        && is_numeric($val_index)
                        && trim($value) != ''
                    ) {
                        $dyn_fields[$field_id][$val_index] = $value;
                    }
                }
            }
        }
        return $dyn_fields;
    }
}

/**
* Returns an array of all kind of fields to display.
*
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
    $form_name, $all_values, $disabled, $edit
) {
    global $zdb, $log, $field_properties, $perm_admin, $login;

    try {
        $select = new Zend_Db_Select($zdb->db);

        $select->from(PREFIX_DB . DynamicFields::TYPES_TABLE)
            ->where('field_form = ?', $form_name)
            ->order('field_index');

        $result = $select->query(Zend_DB::FETCH_ASSOC)->fetchAll();

        $dyn_fields = array();
        if ( $result ) {
            $extra = $edit ? 1 : 0;

            if ( !$login->isAdmin()
                && !$login->isStaff()
                && $result->field_perm == $perm_admin
            ) {
                $disabled[$field_id] = 'disabled';
            }

            foreach ( $result as $r ) {
                $field_id = $r['field_id'];
                $r['field_name'] = _T($r['field_name']);
                $properties = $field_properties[$r['field_type']];

                if ( $properties['multi_valued'] ) {
                    if ( $r['field_repeat'] == 0 ) { // Infinite multi-valued field
                        if ( isset($all_values[$r['field_id']]) ) {
                            $nb_values = count($all_values[$r['field_id']]);
                        } else {
                            $nb_values = 0;
                        }
                        if ( isset($all_values) ) {
                            $r['field_repeat'] = $nb_values + $extra;
                        } else {
                            $r['field_repeat'] = 1;
                        }
                    }
                } else {
                    $r['field_repeat'] = 1;
                    if ( $properties['fixed_values'] ) {
                        $r['choices'] = get_fixed_values($field_id);
                    }
                }
                $dyn_fields[] = $r;
            }
            return $dyn_fields;
        } else {
            return false;
        }
    } catch (Exception $e) {
        /** TODO */
        $log->log(
            'get_dynamic_fields | ' . $e->getMessage(),
            PEAR_LOG_WARNING
        );
        $log->log(
            'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
            PEAR_LOG_ERR
        );
    }
}
?>
