<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups managment
 *
 * PHP version 5
 *
 * Copyright © 2011 The Galette Team
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
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

require_once 'includes/galette.inc.php';
require_once 'classes/groups.class.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
}

$groups = new Groups();

$groups_list = $groups->getList();

//delete groups
if (isset($_GET['sup']) || isset($_POST['delete'])) {
    if ( isset($_GET['sup']) ) {
        $groups->removeGroups($_GET['sup']);
    } else if ( isset($_POST['groups_sel']) ) {
        $groups->removeGroups($_POST['group_sel']);
    }
}

$tpl->assign('require_dialog', true);
$tpl->assign('page_title', _T("Groups"));
$tpl->assign('groups', $groups_list);
$content = $tpl->fetch('gestion_groupes.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
