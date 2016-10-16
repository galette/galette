<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Configuration des fiches
 *
 * PHP version 5
 *
 * Copyright © 2004-2014 The Galette Team
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
 * @copyright 2004-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

use Galette\Entity\DynamicFields;
use Galette\DynamicFieldsTypes\DynamicFieldType;
use Analog\Analog;
use Zend\Db\Sql\Expression;
use Zend\Db\Adapter\Adapter;

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
                    $select = $zdb->select(DynamicFieldType::TABLE);
                    $select->columns(
                        array(
                            'idx' => new Expression('COUNT(*) + 1')
                        )
                    );
                    $select->where(array('field_form' => $form_name));
                    $results = $zdb->execute($select);
                    $result = $results->current();
                    $idx = $result->idx;
                } catch (Exception $e) {
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

                        $insert = $zdb->insert(DynamicFieldType::TABLE);
                        $insert->values($values);
                        $zdb->execute($insert);

                        if ($field_type != DynamicFields::SEPARATOR
                            && count($error_detected) == 0
                        ) {
                            if ( $zdb->isPostgres() ) {
                                $field_id = $zdb->driver->getLastGeneratedValue(
                                    PREFIX_DB . 'field_types_id_seq'
                                );
                            } else {
                                $field_id = $zdb->driver->getLastGeneratedValue();
                            }

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
                $zdb->connection->beginTransaction();
                $select = $zdb->select(DynamicFieldType::TABLE);
                $select->columns(
                    array('field_type', 'field_index', 'field_name')
                )->where(
                    array(
                        DynamicFieldType::PK    => $field_id,
                        'field_form'            => $form_name
                    )
                );

                $results = $zdb->execute($select);
                $result = $results->current();

                if ( $result !== false ) {
                    $old_rank = $result->field_index;
                    $query_list = array();

                    if ( $action == 'del' ) {
                        $update = $zdb->update(DynamicFieldType::TABLE);
                        $update->set(
                            array(
                                'field_index' => new Expression('field_index-1')
                            )
                        )->where
                            ->greaterThan('field_index', $old_rank)
                            ->equalTo('field_form', $form_name);
                        $zdb->execute($update);

                        $delete = $zdb->delete(DynamicFields::TABLE);
                        $delete->where(
                            array(
                                'field_id'      => $field_id,
                                'field_form'    => $form_name
                            )
                        );
                        $zdb->execute($delete);

                        $delete = $zdb->delete(DynamicFieldType::TABLE);
                        $delete->where(
                            array(
                                'field_id'      => $field_id,
                                'field_form'    => $form_name
                            )
                        );
                        $zdb->execute($delete);

                        $df = $dyn_fields->getFieldType($result->field_type);
                        if ( $df->hasFixedValues() ) {
                            $contents_table = DynamicFields::getFixedValuesTableName(
                                $field_id
                            );
                            $zdb->db->query(
                                'DROP TABLE IF EXISTS ' . $contents_table,
                                Adapter::QUERY_MODE_EXECUTE
                            );
                        }
                        deleteDynamicTranslation(
                            $result->field_name,
                            $error_detected
                        );
                    } else {
                        $direction = $action == "up" ? -1: 1;
                        $new_rank = $old_rank + $direction;
                        $update = $zdb->update(DynamicFieldType::TABLE);
                        $update->set(
                            array(
                                'field_index' => $old_rank
                            )
                        )->where(
                            array(
                                'field_index'   => $new_rank,
                                'field_form'    => $form_name
                            )
                        );
                        $zdb->execute($update);

                        $update = $zdb->update(DynamicFieldType::TABLE);
                        $update->set(
                            array(
                                'field_index' => $new_rank
                            )
                        )->where(
                            array(
                                'field_id'      => $field_id,
                                'field_form'    => $form_name
                            )
                        );
                        $zdb->execute($update);
                    }
                }
                $zdb->connection->commit();
            } catch(Exception $e) {
                $zdb->connection->rollBack();
                Analog::log(
                    'Unable to change field ' . $field_id . ' rank | ' .
                    $e->getMessage(),
                    Analog::ERROR
                );
            }
        }
    }

    $select = $zdb->select(DynamicFieldType::TABLE);
    $select
        ->where(array('field_form' => $form_name))
        ->order('field_index');

    $results = $zdb->execute($select);

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
    $tpl->assign('adhesion_form_url', $adhesion_form_url);
    $tpl->display('page.tpl');
}
