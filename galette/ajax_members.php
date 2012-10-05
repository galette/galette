<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members list
 * Make possible to search and select a member
 *
 * This page can be loaded directly, or via ajax.
 * Via ajax, we do not have a full html page, but only
 * that will be displayed using javascript on another page
 *
 * PHP version 5
 *
 * Copyright © 2011-2012 The Galette Team
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
 * @category  Plugins
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id: owners.php 556 2009-03-13 06:48:49Z trashy $
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-08-28
 */

use Galette\Filters\MembersList as MembersList;
use Galette\Common\KLogger as KLogger;

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() || !$login->isAdmin() && !$login->isStaff() ) {
    $log->log(
        'Trying to display ajax_members.php without appropriate permissions',
        KLogger::INFO
    );
    die();
}

// check for ajax mode
$ajax = ( isset($_POST['ajax']) && $_POST['ajax'] == 'true' ) ? true : false;
$multiple = ( isset($_POST['multiple']) && $_POST['multiple'] == 'false' ) ? false : true;

$session = &$_SESSION['galette'][PREFIX_DB . '_' . NAME_DB];
if ( isset($session['ajax_members_filters']['members']) ) {
    $filters = unserialize($session['ajax_members_filters']['members']);
} else {
    $filters = new MembersList();
}

if (isset($_GET['page'])) {
    $filters->current_page = (int)$_GET['page'];
}

if (isset($_POST['page'])) {
    $filters->current_page = (int)$_POST['page'];
}

//numbers of rows to display
if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
    $filters->show = $_GET['nbshow'];
}

$members = new Galette\Repository\Members();
$members_list = $members->getMembersList(true);

//assign pagination variables to the template and add pagination links
$filters->setSmartyPagination($tpl);

$session['ajax_members_filters']['members'] = serialize($filters);

$selected_members = null;
$unreachables_members = null;
if ( !isset($_POST['from']) ) {
    $mailing = unserialize($session['mailing']);
    if ( !isset($_POST['members']) ) {
        $selected_members = $mailing->recipients;
        $unreachables_members = $mailing->unreachables;
    } else {
        $selected_members = Galette\Repository\Members::getArrayList($_POST['members']);
    }
} else {
    switch ( $_POST['from'] ) {
    case 'groups':
        if ( !isset($_POST['gid']) ) {
            $log->log(
                'Trying to list group members with no group id provided',
                KLogger::ERR
            );
            throw new Exception('A group id is required.');
            exit(0);
        }
        if ( !isset($_POST['members']) ) {
            $group = new Galette\Entity\Group((int)$_POST['gid']);
            $selected_members = array();
            if ( !isset($_POST['mode']) || $_POST['mode'] == 'members' ) {
                $selected_members = $group->getMembers();
            } else if ( $_POST['mode'] == 'managers' ) {
                $selected_members = $group->getManagers();
            } else {
                $log->log(
                    'Trying to list group members with unknown mode',
                    KLogger::ERR
                );
                throw new Exception('Unknown mode.');
                exit(0);
            }
        } else {
            $selected_members = Galette\Repository\Members::getArrayList($_POST['members']);
        }
        break;
    }
}

$tpl->assign('ajax', $ajax);
$tpl->assign('multiple', $multiple);
$tpl->assign('members_list', $members_list);
$tpl->assign('selected_members', $selected_members);
$tpl->assign('unreachables_members', $unreachables_members);
if ( isset($_POST['gid']) ) {
    $tpl->assign('the_id', $_POST['gid']);
}
$tpl->assign('filters', $filters);

if ( $ajax ) {
    $tpl->assign('mode', 'ajax');
    $tpl->display('ajax_members.tpl');
} else {
    $content = $tpl->fetch('ajax_members.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
?>
