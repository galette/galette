<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Edit form optionnal labels
 *
 * PHP version 5
 *
 * Copyright © 2004-2013 The Galette Team
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
 * @copyright 2004-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

use Analog\Analog as Analog;
use Galette\Entity\DynamicFields as DynamicFields;
use Galette\DynamicFieldsTypes\DynamicFieldType as DynamicFieldType;

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

$dyn_fields = new DynamicFields();
$all_forms = $dyn_fields->getFormsNames();

$form_name = get_form_value('form', '');
if ( !isset($all_forms[$form_name]) ) {
    header('location: configurer_fiches.php');
    die();
}

$field_id = get_numeric_form_value("id", '');
if ( $field_id == '' ) {
    header('location: configurer_fiches.php?form=' . $form_name);
    die();
}

$df = $dyn_fields->loadFieldType($field_id);
if ( $df === false ) {
    $error_detected[] = _T("Unable to retrieve field informations.");
}

$data = array('id' => $field_id);

if ( isset($_POST['valid']) ) {
    $field_name = $_POST['field_name'];
    $field_perm = get_numeric_posted_value('field_perm', '');
    $field_required = get_numeric_posted_value('field_required', '0');
    $field_width = get_numeric_posted_value('field_width', null);
    $field_height = get_numeric_posted_value('field_height', null);
    $field_size = get_numeric_posted_value('field_size', null);
    $field_repeat = get_numeric_posted_value(
        'field_repeat',
        new Zend_Db_Expr('NULL')
    );
    $fixed_values = get_form_value('fixed_values', '');

    if ( $field_id != '' && $field_perm != '' ) {
        $duplicated = $dyn_fields->isDuplicate(
            $zdb,
            $form_name,
            $field_name,
            $field_id
        );

        if ( $duplicated ) {
            $error_detected[] = _T("- Field name already used.");
        } else {
            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                PREFIX_DB . DynamicFieldType::TABLE,
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
                    'field_required' => $field_required,
                    'field_width'    => $field_width,
                    'field_height'   => $field_height,
                    'field_size'     => $field_size,
                    'field_repeat'   => $field_repeat
                );
                $zdb->db->update(
                    PREFIX_DB . DynamicFieldType::TABLE,
                    $values,
                    'field_id = ' . $field_id
                );
            } catch (Exception $e) {
                /** FIXME */
                Analog::log(
                    'An error occured storing field | ' . $e->getMessage(),
                    Analog::ERROR
                );
                $error_detected[] = _T("An error occured storing the field.");
            }
        }

        if ( $df->hasFixedValues() ) {
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
            $contents_table = DynamicFields::getFixedValuesTableName($field_id);

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
                Analog::log(
                    'Unable to manage fields values table ' .
                    $contents_table . ' | ' . $e->getMessage(),
                    Analog::ERROR
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
                    Analog::log(
                        'Unable to store field ' . $field_id . ' values',
                        Analog::ERROR
                    );
                }
            }
        }
    }
    if ( count($error_detected) == 0 ) {
        header('location: configurer_fiches.php?form=' . $form_name);
        die();
    }
} elseif ( isset($_POST['cancel']) ) {
    header('location: configurer_fiches.php?form=' . $form_name);
    die();
}

//We load values here, making sure all changes are stored in database
$df->load();

$tpl->assign('page_title', _T("Edit field"));
$tpl->assign('form_name', $form_name);
$tpl->assign('df', $df);
$tpl->assign('error_detected', $error_detected);

$tpl->assign('perm_all', DynamicFields::PERM_ALL);
$tpl->assign('perm_staff', DynamicFields::PERM_STAFF);
$tpl->assign('perm_admin', DynamicFields::PERM_ADM);
$tpl->assign('perm_names', $dyn_fields->getPermsNames());

$content = $tpl->fetch('editer_champ.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
