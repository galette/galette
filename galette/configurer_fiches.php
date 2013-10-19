<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Configuration des fiches
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

use Galette\Entity\DynamicFields as DynamicFields;
use Galette\DynamicFieldsTypes\DynamicFieldType as DynamicFieldType;
use Analog\Analog as Analog;


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

$form_name = ( isset($_GET['form']) ) ? $_GET['form'] : 'adh';
if ( isset($_POST['form']) && trim($_POST['form']) != '' ) {
    $form_name = $_POST['form'];
}
if ( !isset($all_forms[$form_name]) ) {
    $form_name = '';
}

$field_type_names = $dyn_fields->getFieldsTypesNames();

if ( $form_name == '' ) {
    $form_title = '';
} else {
    $form_title = $all_forms[$form_name];

    if ( isset($_POST['valid']) ) {
        if ($_POST['field_type'] != DynamicFields::SEPARATOR
            && (!isset($_POST['field_name']) || $_POST['field_name'] == '')
        ) {
            $error_detected[] = _T("- The name field cannot be void.");
        } else {
            $field_name = $_POST['field_name'];
            $field_perm = $_POST['field_perm'];
            $field_type = $_POST['field_type'];
            $field_required = $_POST['field_required'];

            $duplicated = $dyn_fields->isDuplicate(
                $zdb,
                $form_name,
                $field_name
            );

            if ( !$duplicated ) {
                try {
                    $select = new Zend_Db_Select($zdb->db);
                    $select->from(
                        PREFIX_DB . DynamicFieldType::TABLE,
                        'COUNT(*) + 1 AS idx'
                    )->where('field_form = ?', $form_name);
                    $str = $select->__toString();
                    $idx = $select->query()->fetchColumn();
                } catch (Exception $e) {
                    /** FIXME */
                    throw $e;
                }

                if ($idx !== false) {
                    try {
                        $values = array(
                            'field_index'    => $idx,
                            'field_form'     => $form_name,
                            'field_name'     => $field_name,
                            'field_perm'     => $field_perm,
                            'field_type'     => $field_type,
                            'field_required' => $field_required
                        );
                        $zdb->db->insert(
                            PREFIX_DB . DynamicFieldType::TABLE,
                            $values
                        );

                        if ($field_type != DynamicFields::SEPARATOR
                            && count($error_detected) == 0
                        ) {
                            $field_id = $zdb->db->lastInsertId(
                                PREFIX_DB . DynamicFieldType::TABLE,
                                'id'
                            );
                            header(
                                'location: editer_champ.php?form=' . $form_name .
                                '&id=' . $field_id
                            );
                            die();
                        }
                        if ( $field_name != '' ) {
                            addDynamicTranslation($field_name, $error_detected);
                        }
                    } catch (Exception $e) {
                        /** FIXME */
                        Analog::log(
                            'An error occured adding new dynamic field. | ' .
                            $e->getMessage(),
                            Analog::ERROR
                        );
                    }
                }
            } else {
                $error_detected[] = _T("- Field name already used.");
            }
        }
    } else {
        $action = '';
        $field_id = '';
        foreach ( array('del', 'up', 'down') as $varname ) {
            if ( isset($_GET[$varname]) && is_numeric($_GET[$varname]) ) {
                $action = $varname;
                $field_id = (integer)$_GET[$varname];
                break;
            }
        }
        if ( $action !== '' ) {
            try {
                $zdb->db->beginTransaction();
                $select = new Zend_Db_Select($zdb->db);
                $select->from(
                    PREFIX_DB . DynamicFieldType::TABLE,
                    array('field_type', 'field_index', 'field_name')
                )->where(DynamicFieldType::PK . ' = ?', $field_id)
                    ->where('field_form = ?', $form_name);
                $res = $select->query()->fetch();
                if ( $res !== false ) {
                    $old_rank = $res->field_index;
                    $query_list = array();

                    if ( $action == 'del' ) {
                        $up = $zdb->db->update(
                            PREFIX_DB . DynamicFieldType::TABLE,
                            array(
                                'field_index' => new Zend_Db_Expr('field_index-1')
                            ),
                            array(
                                'field_index > ?' => $old_rank,
                                'field_form = ?'  => $form_name
                            )
                        );

                        $del1 = $zdb->db->delete(
                            PREFIX_DB . DynamicFields::TABLE,
                            array(
                                'field_id = ?'   => $field_id,
                                'field_form = ?' => $form_name
                            )
                        );

                        $del2 = $zdb->db->delete(
                            PREFIX_DB . DynamicFieldType::TABLE,
                            array(
                                'field_id = ?'   => $field_id,
                                'field_form = ?' => $form_name
                            )
                        );

                        $df = $dyn_fields->getFieldType($res->field_type);
                        if ( $df->hasFixedValues() ) {
                            $contents_table = DynamicFields::getFixedValuesTableName($field_id);
                            $zdb->db->getConnection()->exec(
                                'DROP TABLE IF EXISTS ' . $contents_table
                            );
                        }
                        deleteDynamicTranslation($res->field_name, $error_detected);
                    } else {
                        $direction = $action == "up" ? -1: 1;
                        $new_rank = $old_rank + $direction;
                        $zdb->db->update(
                            PREFIX_DB . DynamicFieldType::TABLE,
                            array(
                                'field_index' => $old_rank
                            ),
                            array(
                                'field_index = ?' => $new_rank,
                                'field_form = ?'  => $form_name
                            )
                        );

                        $zdb->db->update(
                            PREFIX_DB . DynamicFieldType::TABLE,
                            array(
                                'field_index' => $new_rank
                            ),
                            array(
                                'field_id = ?'   => $field_id,
                                'field_form = ?' => $form_name
                            )
                        );
                    }
                }
                $zdb->db->commit();
            } catch(Exception $e) {
                /** FIXME */
                //this one does not seem to work :'(
                $zdb->db->rollBack();
                Analog::log(
                    'Unable to change field ' . $field_id . ' rank | ' .
                    $e->getMessage(),
                    Analog::ERROR
                );
            }
        }
    }

    $select = new Zend_Db_Select($zdb->db);
    $select->from(PREFIX_DB . DynamicFieldType::TABLE)
        ->where('field_form = ?', $form_name)
        ->order('field_index');

    $results = $select->query()->fetchAll();

    $dfields = array();
    if ( $results ) {
        $count = 0;
        foreach ( $results as $r ) {
            $dfields[$count]['id'] = $r->field_id;
            $dfields[$count]['index'] = $r->field_index;
            $dfields[$count]['name'] = $r->field_name;
            $dfields[$count]['perm'] =  $dyn_fields->getPermName($r->field_perm);
            $dfields[$count]['type'] = $r->field_type;
            $dfields[$count]['type_name'] = $field_type_names[$r->field_type];
            $dfields[$count]['required'] = ($r->field_required == '1');
            ++$count;
        }
    } // $result != false

    $tpl->assign('dyn_fields', $dfields);
} // $form_name == ''

//UI configuration
$tpl->assign('require_tabs', true);
$tpl->assign('require_dialog', true);

//Populate template with data
$tpl->assign('all_forms', $all_forms);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('form_name', $form_name);
$title = _T("Profile configuration");
$tpl->assign('page_title', $title);
$tpl->assign('perm_names', $dyn_fields->getPermsNames());
$tpl->assign('field_type_names', $field_type_names);

//Render directly template if we called from ajax,
//render in a full page otherwise
if ( isset($_GET['ajax']) && $_GET['ajax'] == 'true' ) {
    $tpl->display('configurer_fiche_content.tpl');
} else {
    $content = $tpl->fetch('configurer_fiches.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
