<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Groups managment
 *
 * PHP version 5
 *
 * Copyright © 2011-2014 The Galette Team
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
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

use Analog\Analog;
use Galette\Entity\Group;
use Galette\Entity\Adherent;
use Galette\Repository\Members;
use Galette\Repository\Groups;

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() && !$login->isGroupManager() ) {
    header('location: voir_adherent.php');
    die();
}

$groups = new Groups();

$group = new Group();
$error_detected = array();
$success_detected = array();

$id = get_numeric_form_value(Group::PK, null);
if ( $id !== null ) {
    if ( $login->isGroupManager($id) ) {
        $group->load($id);
    } else {
        Analog::log(
            'Trying to display group ' . $id . ' without appropriate permissions',
            Analog::INFO
        );
        die();
    }
}

if ( isset($_POST['pdf']) ) {
    $qstring = 'groups_list.php';
    if ( isset($_POST['id_group']) ) {
        $qstring .= '?gid=' . $_POST['id_group'];
    }
    header('location: '.$qstring);
    die();
}

if ( isset($_POST['delete']) || isset($_POST['delete_cascade']) ) {
    //delete groups
    $cascade = isset($_POST['delete_cascade']);
    $del = $group->remove($cascade);
    if ( $del !== true ) {
        if ( $group->isEmpty() === false ) {
            $error_detected[] = _T("Group is not empty, it cannot be deleted. Use cascade delete instead.");
        } else {
            $error_detected[] = _T("An error occured trying to remove group :/");
        }
    } else {
        $success_detected[] = str_replace(
            '%groupname',
            $group->getName(),
            _T("Group %groupname has been successfully deleted.")
        );
        //reinstanciate group
        $id = null;
        $group = new Group();
    }
} else if ( isset($_POST['group_name']) ) {
    $group->setName($_POST['group_name']);
    try {
        if ( $_POST['parent_group'] !== '') {
                $group->setParentGroup((int)$_POST['parent_group']);
        } else if ( $_POST['parent_group'] === '' && $group->getId() != null ) {
            $group->detach();
        }
    } catch ( Exception $e ) {
        $error_detected[] = $e->getMessage();
    }

    $new = false;
    if ( $group->getId() == '' ) {
        $new = true;
    }

    $managers_id = array();
    if ( isset($_POST['managers']) ) {
        $managers_id = $_POST['managers'];
    }
    $m = new Members();
    $managers = $m->getArrayList($managers_id);

    $members_id = array();
    if ( isset($_POST['members']) ) {
        $members_id = $_POST['members'];
    }
    $members = $m->getArrayList($members_id);

    $group->setManagers($managers);
    $group->setMembers($members);

    if ( count($error_detected) == 0 ) {
        $store = $group->store();
        if ( $store === true ) {
            $success_detected[] = preg_replace(
                '/%groupname/',
                $group->getName(),
                _T("Group `%groupname` has been successfully saved.")
            );
        } else {
            //something went wrong :'(
            $error_detected[] = _T("An error occured while storing the group.");
        }
    }
}

if ( isset($_GET['new']) ) {
    $group = new Group();
    $group->setName($_GET['group_name']);
    if ( !$login->isSuperAdmin() ) {
        $group->setManagers(new Adherent($login->id));
    }
    $group->store();
    $id = $group->getId();
}

$groups_root = $groups->getList(false);
$groups_list = $groups->getList();

if ( count($error_detected) > 0 ) {
    $tpl->assign('error_detected', $error_detected);
}

if ( count($success_detected) > 0 ) {
    $tpl->assign('success_detected', $success_detected);
}

$tpl->assign('require_dialog', true);
$tpl->assign('require_tabs', true);
$tpl->assign('require_tree', true);
$tpl->assign('page_title', _T("Groups"));
$tpl->assign('groups_root', $groups_root);
$tpl->assign('groups', $groups_list);

if ( $id === null && count($groups_root) > 0 ) {
    reset($groups_root);
    $group = current($groups_root);
    if ( !$login->isGroupManager($group->getId()) ) {
        foreach ( $groups_list as $g ) {
            if ( $login->isGroupManager($g->getId()) ) {
                $group = $g;
                break;
            }
        }
    }
}

$tpl->assign('group', $group);
$content = $tpl->fetch('gestion_groupes.tpl');
$tpl->assign('content', $content);
$tpl->assign('adhesion_form_url', $adhesion_form_url);
$tpl->display('page.tpl');
