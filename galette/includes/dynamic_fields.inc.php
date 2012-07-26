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

use Galette\Common\KLogger as KLogger;
use Galette\Entity\DynamicFields as DynamicFields;

$dyn_fields = new DynamicFields();

$field_type_names = $dyn_fields->getFieldsTypesNames();

$all_forms = $dyn_fields->getFormsNames();

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
    global $zdb, $log;
    $ret = false;

    try {
        $zdb->db->beginTransaction();

        $select = new Zend_Db_Select($zdb->db);
        $select->from(
            PREFIX_DB . DynamicFields::TABLE,
            array('cnt' => 'count(*)')
        )->where('item_id = ?', $item_id)
            ->where('field_form = ?', $form_name)
            ->where('field_id = ?', $field_id)
            ->where('val_index = ?', $val_index);

        $count = $select->query()->fetchColumn();

        if ( $count > 0 ) {
            //cleanup WHERE array so it can be sent back to update
            //and delete methods
            $where = array();
            $owhere = $select->getPart(Zend_Db_Select::WHERE);
            foreach ( $owhere as $c ) {
                $where[] = preg_replace('/^AND /', '', $c);
            }

            if ( trim($field_val) == '' ) {
                $zdb->db->delete(
                    PREFIX_DB . DynamicFields::TABLE,
                    $where
                );
            } else {
                $zdb->db->update(
                    PREFIX_DB . DynamicFields::TABLE,
                    array('field_val' => $field_val),
                    $where
                );
            }
        } else {
            if ( $field_val !== '' ) {
                $values = array(
                    'item_id'    => $item_id,
                    'field_form' => $form_name,
                    'field_id'   => $field_id,
                    'val_index'  => $val_index,
                    'field_val'  => $field_val
                );

                $zdb->db->insert(
                    PREFIX_DB . DynamicFields::TABLE,
                    $values
                );
            }
        }

        $zdb->db->commit();
        return true;
    } catch (Exception $e) {
        $zdb->db->rollBack();
        $log->log(
            'An error occured storing dynamic field. Form name: ' . $form_name .
            '; item_id:' . $item_id . '; field_id: ' . $field_id .
            '; val_index: ' . $val_index . '; field_val:' . $field_val .
            ' | Error was: ' . $e->getMessage(),
            KLogger::ERR
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
    global $zdb, $log, $field_properties, $dyn_fields;

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
                            $choices = $dyn_fields->getFixedValues($f->field_id);
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
            KLogger::WARN
        );
        $log->log(
            'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
            KLogger::ERR
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
    global $zdb, $log, $field_properties, $login, $dyn_fields;

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
                && $result->field_perm == DynamicFields::PERM_ADM
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
                        $r['choices'] = $dyn_fields->getFixedValues($field_id);
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
            KLogger::WARN
        );
        $log->log(
            'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
            KLogger::ERR
        );
    }
}
?>
