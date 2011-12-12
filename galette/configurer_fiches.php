<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Configuration des fiches
 *
 * PHP version 5
 *
 * Copyright © 2004-2011 The Galette Team
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
 * @copyright 2004-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

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

require_once WEB_ROOT . 'classes/dynamic_fields.class.php';
require_once WEB_ROOT . 'includes/dynamic_fields.inc.php';

$form_name = ( isset($_GET['form']) ) ? $_GET['form'] : 'adh';
if ( isset($_POST['form']) && trim($_POST['form']) != '' ) {
    $form_name = $_POST['form'];
}
if ( !isset($all_forms[$form_name]) ) {
    $form_name = '';
}

$form_not_set = ($form_name == '');

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
            $field_pos = $_POST['field_pos'];

            try {
                $select = new Zend_Db_Select($zdb->db);
                $select->from(
                    PREFIX_DB . DynamicFields::TYPES_TABLE,
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
                        'field_required' => $field_required,
                        'field_pos'      => $field_pos
                    );
                    $zdb->db->insert(
                        PREFIX_DB . DynamicFields::TYPES_TABLE,
                        $values
                    );

                    if ($field_type != DynamicFields::SEPARATOR
                        && count($error_detected) == 0
                    ) {
                        $field_id = $zdb->db->lastInsertId(
                            PREFIX_DB . DynamicFields::TYPES_TABLE,
                            'id'
                        );
                        header(
                            'location: editer_champ.php?form=' . $form_name .
                            '&id=' . $field_id
                        );
                    }
                    if ( $field_name != '' ) {
                        addDynamicTranslation($field_name, $error_detected);
                    }
                } catch (Exception $e) {
                    /** FIXME */
                    $log->log(
                        'An error occured adding new dynamic field. | ' .
                        $e->getMessage(),
                        PEAR_LOG_ERR
                    );
                }
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
                    PREFIX_DB . DynamicFields::TYPES_TABLE,
                    array('field_type', 'field_index', 'field_name')
                )->where(DynamicFields::TYPES_PK . ' = ?', $field_id)
                    ->where('field_form = ?', $form_name);
                $res = $select->query()->fetch();
                if ( $res !== false ) {
                    $old_rank = $res->field_index;
                    $query_list = array();

                    if ( $action == 'del' ) {
                        $up = $zdb->db->update(
                            PREFIX_DB . DynamicFields::TYPES_TABLE,
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
                            PREFIX_DB . DynamicFields::TYPES_TABLE,
                            array(
                                'field_id = ?'   => $field_id,
                                'field_form = ?' => $form_name
                            )
                        );

                        $ftype = $res->field_type;
                        if ($field_properties[$ftype]['fixed_values']) {
                            $contents_table = fixed_values_table_name($field_id);
                            $zdb->db->getConnection()->exec('DROP TABLE ' . $contents_table);
                        }
                        deleteDynamicTranslation($res->field_name, $error_detected);
                    } else {
                        $direction = $action == "up" ? -1: 1;
                        $new_rank = $old_rank + $direction;
                        $zdb->db->update(
                            PREFIX_DB . DynamicFields::TYPES_TABLE,
                            array(
                                'field_index' => $old_rank
                            ),
                            array(
                                'field_index = ?' => $new_rank,
                                'field_form = ?'  => $form_name
                            )
                        );

                        $zdb->db->update(
                            PREFIX_DB . DynamicFields::TYPES_TABLE,
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
                //this one does not seems to work :'(
                $zdb->db->rollBack();
                $log->log(
                    'Unable to change field ' . $field_id . ' rank | ' .
                    $e->getMessage(),
                    PEAR_LOG_ERR
                );
            }
        }
    }

    $select = new Zend_Db_Select($zdb->db);
    $select->from(PREFIX_DB . DynamicFields::TYPES_TABLE)
        ->where('field_form = ?', $form_name)
        ->order('field_index');

    $results = $select->query()->fetchAll();

    if ( $results ) {
        $count = 0;
        $dyn_fields = array();
        foreach ( $results as $r ) {
            $dyn_fields[$count]['id'] = $r->field_id;
            $dyn_fields[$count]['index'] = $r->field_index;
            $dyn_fields[$count]['name'] = $r->field_name;
            $dyn_fields[$count]['perm'] = $perm_names[$r->field_perm];
            $dyn_fields[$count]['type'] = $r->field_type;
            $dyn_fields[$count]['type_name'] = $field_type_names[$r->field_type];
            $dyn_fields[$count]['required'] = ($r->field_required == '1');
            $dyn_fields[$count]['pos'] = $field_positions[$r->field_pos];
            ++$count;
        }
    } // $result != false

    $tpl->assign('perm_names', $perm_names);
    $tpl->assign('field_type_names', $field_type_names);

    $tpl->assign('dyn_fields', $dyn_fields);
} // $form_name == ''

$tpl->assign('require_tabs', true);
$tpl->assign('all_forms', $all_forms);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('form_name', $form_name);
//$tpl->assign('form_title', $form_title);
$title = _T("Profile configuration");
if ( $form_title != '' ) {
    $title .= ' (' . $form_title . ')';
}
$tpl->assign('page_title', $title);
$tpl->assign('perm_names', $perm_names);
$tpl->assign('field_type_names', $field_type_names);
$tpl->assign('field_positions', $field_positions);
if ( isset($_GET['ajax']) && $_GET['ajax'] == 'true' ) {
    $tpl->display('configurer_fiche_content.tpl');
} else {
    $content = $tpl->fetch('configurer_fiches.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
?>
