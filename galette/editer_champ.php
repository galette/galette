<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Edit form optionnal labels
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
 * @since     Available since 0.62
 */

use Galette\Common\KLogger as KLogger;
/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
} else if ( !$login->isAdmin() ) {
    header('location: gestion_adherents.php');
    die();
}

require WEB_ROOT . 'includes/dynamic_fields.inc.php';

$form_name = get_form_value('form', '');
if ( !isset($all_forms[$form_name]) ) {
    header('location: configurer_fiches.php');
}

$field_id = get_numeric_form_value("id", '');
if ( $field_id == '' ) {
    header('location: configurer_fiches.php?form=' . $form_name);
}

try {
    $select = new Zend_Db_Select($zdb->db);
    $select->from(
        $field_types_table,
        'field_type'
    )->where('field_id = ?', $field_id);
    $field_type = $select->query()->fetchColumn();
    if ( $field_type !== false ) {
        $properties = $field_properties[$field_type];
    } else {
        $error_detected[] = _T("Unable to retrieve field informations.");
    }
} catch (Exception $e) {
    /** FIXME */
    $log->log(
        'Unable to retrieve field `' . $field_id . '` informations | ' .
        $e->getMessage(),
        KLogger::ERR
    );
    $error_detected[] = _T("Unable to retrieve field informations.");
}

$data = array('id' => $field_id);

if ( isset($_POST['valid']) ) {
    $field_name = $_POST['field_name'];
    $field_perm = get_numeric_posted_value('field_perm', '');
    $field_pos = get_numeric_posted_value('field_pos', 0);
    $field_required = get_numeric_posted_value('field_required', '0');
    $field_width = get_numeric_posted_value('field_width', null);
    $field_height = get_numeric_posted_value('field_height', null);
    $field_size = get_numeric_posted_value('field_size', null);
    $field_repeat = get_numeric_posted_value('field_repeat', 'false');
    $fixed_values = get_form_value('fixed_values', '');

    if ( $field_id != '' && $field_perm != '' ) {
        //let's consider fielod is duplicated, in case of future errors
        $duplicated = true;
        try {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                $field_types_table,
                'COUNT(field_id)'
            )->where('NOT field_id = ?', $field_id)
                ->where('field_form = ?', $form_name)
                ->where('field_name = ?', $field_name);
            $dup = $select->query()->fetchColumn();
            if ( !$dup > 0 ) {
                $duplicated = false;
            }
        } catch (Exception $e) {
            /** FIXME */
            $log->log(
                'An error occured checking field duplicity' . $e->getMessage(),
                KLogger::ERR
            );
        }

        if ( $duplicated ) {
            $error_detected[] = _T("- Field name already used.");
        } else {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                $field_types_table,
                'field_name'
            )->where('field_id = ?', $field_id);
            $old_field_name = $select->query()->fetchColumn();
            if ( $old_field_name && $field_name != $old_field_name ) {
                addDynamicTranslation($field_name, $error_detected);
                deleteDynamicTranslation($old_field_name, $error_detected);
            }
        }

        if ( count($error_detected) == 0 ) {
            try {
                $values = array(
                    'field_name'     => $field_name,
                    'field_perm'     => $field_perm,
                    'field_pos'      => $field_pos,
                    'field_required' => $field_required,
                    'field_width'    => $field_width,
                    'field_height'   => $field_height,
                    'field_size'     => $field_size,
                    'field_repeat'   => $field_repeat
                );
                $zdb->db->update(
                    $field_types_table,
                    $values,
                    'field_id = ' . $field_id
                );
            } catch (Exception $e) {
                /** FIXME */
                $log->log(
                    'An error occured storing field | ' . $e->getMessage(),
                    KLogger::ERR
                );
                $error_detected[] = _T("An error occured storing the field.");
            }
        }

        if ( $properties['fixed_values'] ) {
            $values = array();
            $max_length = 1;
            foreach ( explode("\n", $fixed_values) as $val ) {
                $val = trim($val);
                $len = strlen($val);
                if ( $len > 0 ) {
                    $values[] = $val;
                    if ( $len > $max_length ) {
                        $max_length = $len;
                    }
                }
            }
            $contents_table = fixed_values_table_name($field_id);

            try {
                $zdb->db->beginTransaction();
                $zdb->db->getConnection()->exec('DROP TABLE IF EXISTS ' . $contents_table);
                $zdb->db->query(
                    'CREATE TABLE ' . $contents_table .
                    ' (id INTEGER NOT NULL,val varchar(' . $max_length .
                    ') NOT NULL)'
                );
                $zdb->db->commit();
            } catch (Exception $e) {
                /** FIXME */
                $zdb->db->rollBack();
                $log->log(
                    'Unable to manage fields values table ' .
                    $contents_table . ' | ' . $e->getMessage(),
                    KLogger::ERR
                );
                $error_detected[] = _T("An error occured storing managing fields values table");
            }

            if (count($error_detected) == 0) {

                try {
                    $zdb->db->beginTransaction();
                    $stmt = $zdb->db->prepare(
                        'INSERT INTO ' . $contents_table .
                        ' (' . $zdb->db->quoteIdentifier('id') . ', ' .
                        $zdb->db->quoteIdentifier('val') . ')' .
                        ' VALUES(:id, :val)'
                    );

                    for ( $i = 0; $i < count($values); $i++ ) {
                        $stmt->bindValue(':id', $i, PDO::PARAM_INT);
                        $stmt->bindValue(':val', $values[$i], PDO::PARAM_STR);
                        $stmt->execute();
                    }
                    $zdb->db->commit();
                }catch (Exception $e) {
                    /** FIXME */
                    $zdb->db->rollBack();
                    $log->log(
                        'Unable to store field ' . $field_id . ' values',
                        KLogger::ERR
                    );
                }
            }
        }
    }
    if ( count($error_detected) == 0 ) {
        header('location: configurer_fiches.php?form=' . $form_name);
    }
} elseif ( isset($_POST['cancel']) ) {
    header('location: configurer_fiches.php?form=' . $form_name);
} else {
    try {
        $select->columns();
        $result = $select->query()->fetch();

        if ($result !== false) {
            $field_name = $result->field_name;
            $field_type = $result->field_name;
            $field_perm = $result->field_perm;
            $field_pos = $result->field_pos;
            $field_required = $result->field_required;
            $field_width = $result->field_width;
            $field_height = $result->field_height;
            $field_repeat = $result->field_repeat;
            $field_size = $result->field_size;
            $fixed_values = '';
            if ($properties['fixed_values']) {
                foreach ( get_fixed_values($field_id) as $val ) {
                    $fixed_values .= $val . "\n";
                }
            }
        } // $result != false
    } catch (Exception $e) {
        /** FIXME */
        $log->log(
            'Unable to retrieve fields types for field ' . $field_id . ' | ' .
            $e->getMessage(),
            KLogger::ERR
        );
    }
}

$data['id'] = $field_id;
$data['name'] = $field_name;
$data['perm'] = $field_perm;
$data['pos'] = $field_pos;
$data['required'] = ($field_required == '1');
$data['width'] = $field_width;
$data['height'] = $field_height;
$data['repeat'] = $field_repeat;
$data['size'] = $field_size;
$data['fixed_values'] = $fixed_values;

$tpl->assign('page_title', _T("Edit field"));
$tpl->assign('form_name', $form_name);
$tpl->assign('properties', $properties);
$tpl->assign('data', $data);
$tpl->assign('error_detected', $error_detected);

$tpl->assign('perm_all', $perm_all);
$tpl->assign('perm_admin', $perm_admin);
$tpl->assign('perm_names', $perm_names);

$tpl->assign('field_positions', $field_positions);

$content = $tpl->fetch('editer_champ.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
