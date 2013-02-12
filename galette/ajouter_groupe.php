<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Add a new group or modify existing one
 *
 * PHP version 5
 *
 * Copyright © 2011-2013 The Galette Team
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
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7-dev - 2011-10-26
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
}

$group = new Galette\Entity\Group();

$id = get_numeric_form_value(Galette\Entity\Group::PK, '');
if ( $id ) {
    $group->load($id);
}

if ( isset($_POST['group_name']) ) {
    $group->setName($_POST['group_name']);
    if ( $_POST['parent_group'] !== '') {
        $group->setParentGroup((int)$_POST['parent_group']);
    }
    $new = false;
    if ( $group->getId() == '' ) {
        $new = true;
    }
    $store = $group->store();
    if ( $store === true ) {

    } else {
        //something went wrong :'(
        $error_detected[] = _T("An error occured while storing the group.");
    }


    if ( count($error_detected) == 0 ) {
        header('location: gestion_groupes.php');
        die();
    }
}
 
// template variable declaration
$title = _T("Group");
if ( $group->getId() != '' ) {
    $title .= ' (' . _T("modification") . ')';
} else {
    $title .= ' (' . _T("creation") . ')';
}

$tpl->assign('page_title', $title);
$tpl->assign('group', $group);
$tpl->assign('groups', Galette\Repository\Groups::getSimpleList());

$tpl->assign('require_dialog', true);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('warning_detected', $warning_detected);
$tpl->assign('languages', $i18n->getList());
// page generation
$content = $tpl->fetch('group.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
