<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Configure fields for relevant tables.
 * This page allows admins to choose which fields are
 * required or not, their order and if they are visible or not.
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
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
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Availaible since 0.7dev - 2009-04-11
 */

use Analog\Analog as Analog;
use Galette\Entity\Adherent as Adherent;
use Galette\Entity\FieldsCategories as FieldsCategories;
use Galette\Entity\FieldsConfig as FieldsConfig;

require_once 'includes/galette.inc.php';

$authorized = array('members');
$current = ( isset($_GET['table']) && in_array($_GET['table'], $authorized) ) ?
    $_GET['table'] :
    'members';

if ( !$login->isLogged() ) {
    header("location: index.php");
    die();
}
if ( !$login->isAdmin() ) {
    header("location: voir_adherent.php");
    die();
}

$fc = null;

switch ( $current ) {
case 'members':
    $a = new Adherent();
    $fc = new FieldsConfig(Adherent::TABLE, $a->fields);
    break;
default:
    Analog::log(
        'Trying to configure fields on unknown table (' . $current . ')',
        Analog::WARNING
    );
    break;
}

if ( isset($_POST) && count($_POST) > 0 ) {
    $pos = 0;
    $current_cat = 0;
    $res = array();
    foreach ( $_POST['fields'] as $abs_pos=>$field ) {
        if ( $current_cat != $_POST[$field . '_category'] ) {
            //reset position when category has changed
            $pos = 0;
            //set new current category
            $current_cat = $_POST[$field . '_category'];
        }

        $required = null;
        if ( isset($_POST[$field . '_required']) ) {
            $required = $_POST[$field . '_required'];
        } else {
            $required = false;
        }

        $res[$current_cat][] = array(
            'field_id'  =>  $field,
            'label'     =>  $_POST[$field . '_label'],
            'category'  =>  $_POST[$field . '_category'],
            'visible'   =>  $_POST[$field . '_visible'],
            'required'  =>  $required
        );
        $pos++;
    }
    //okay, we've got the new array, we send it to the
    //Object that will store it in the database
    $success = $fc->setFields($res);
    if ( $success === true ) {
        $success_detected[] = _T("Fields configuration has been successfully stored");
    } else {
        $error_detected[] = _T("An error occured while storing fields configuration :(");
    }
}

$tpl->assign('page_title', _T("Fields configuration"));
$tpl->assign('time', time());
$tpl->assign('categories', FieldsCategories::getList());
$tpl->assign('categorized_fields', $fc->getCategorizedFields());
$tpl->assign('non_required', $fc->getNonRequired());
$tpl->assign('current', $current);
$tpl->assign('require_dialog', true);
$tpl->assign('success_detected', $success_detected);
$tpl->assign('error_detected', $error_detected);
//$tpl->assign('require_sorter', true);
$content = $tpl->fetch('config_fields.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
