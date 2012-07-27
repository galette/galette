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
                                     DynamicFields::extractPosted.
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
?>
