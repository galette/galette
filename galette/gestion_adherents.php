<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members managment window
 *
 * Show members list and offers ordering possibilities :
 * - by status
 * - by member status
 * - by fee status
 * - by account status
 * - by informations content
 *
 * PHP version 5
 *
 * Copyright © 2003-2011 The Galette Team
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
 * @author    Frédéric Jaqcuot <nobody@exemple.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Disponible depuis la Release 0.62
 */

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
} elseif ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
}

require_once 'classes/varslist.class.php';

if ( isset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['varslist'])  ) {
    $varslist = unserialize($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['varslist']);
} else {
    $varslist = new VarsList();
}

require_once 'classes/members.class.php';

$error_detected = array();
// Set caller page ref for cards error reporting
$_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['caller'] = 'gestion_adherents.php';

if (   isset($_POST['cards'])
    || isset($_POST['labels'])
    || isset($_POST['mailing'])
    || isset($_POST['attendance_sheet'])
) {
    if (isset($_POST['member_sel'])) {
        $varslist->selected = $_POST['member_sel'];
        $_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['varslist'] = serialize($varslist);

        if (isset($_POST['cards'])) {
            $qstring = 'carte_adherent.php';
        }
        if (isset($_POST['labels'])) {
            $qstring = 'etiquettes_adherents.php';
        }
        if (isset($_POST['mailing'])) {
            $qstring = 'mailing_adherents.php';
        }
        if (isset($_POST['attendance_sheet'])) {
            $qstring = 'attendance_sheet.php';
            if ( isset($_POST['wimages']) && $_POST['wimages'] == 1 ) {
                $qstring .= '?wimages=1';
            }
        }
        header('location: '.$qstring);
    } else {
        $error_detected[]
            = _T("No member was selected, please check at least one name.");
    }
}

if (isset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['pdf_error']) && $_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['pdf_error']) {
    $error_detected[] = $_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['pdf_error_msg'];
    unset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['pdf_error_msg']);
    unset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['pdf_error']);
}

// Filters
if (isset($_GET['page'])) {
    $varslist->current_page = (int)$_GET['page'];
}

if ( isset($_GET['clear_filter']) ) {
    $varslist->reinit();
} else {
    //string to filter
    if ( isset($_GET['filter_str']) ) { //filter search string
        $varslist->filter_str = stripslashes(
            htmlspecialchars($_GET['filter_str'], ENT_QUOTES)
        );
    }
    //field to filter
    if ( isset($_GET['filter_field']) ) {
        if ( is_numeric($_GET['filter_field']) ) {
            $varslist->field_filter = $_GET['filter_field'];
        }
    }
    //membership to filter
    if ( isset($_GET['filter_membership']) ) {
        if ( is_numeric($_GET['filter_membership']) ) {
            $varslist->membership_filter = $_GET['filter_membership'];
        }
    }
    //account status to filter
    if ( isset($_GET['filter_account']) ) {
        if ( is_numeric($_GET['filter_account']) ) {
            $varslist->account_status_filter = $_GET['filter_account'];
        }
    }
    //email filter
    if ( isset($_GET['email_filter']) ) {
        $varslist->email_filter = (int)$_GET['email_filter'];
    }
}

//numbers of rows to display
if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
    $varslist->show = $_GET['nbshow'];
}

// Sorting
if ( isset($_GET['tri']) ) {
    $varslist->orderby = $_GET['tri'];
}

$members = new Members();

//delete members
if (isset($_GET['sup']) || isset($_POST['delete'])) {
    if ( isset($_GET['sup']) ) {
        $members->removeMembers($_GET['sup']);
    } else if ( isset($_POST['member_sel']) ) {
        $members->removeMembers($_POST['member_sel']);
    }
}

$members_list = $members->getMembersList(true);

$_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['varslist'] = serialize($varslist);

//assign pagination variables to the template and add pagination links
$varslist->setSmartyPagination($tpl);

$tpl->assign('page_title', _T("Members management"));
$tpl->assign('require_dialog', true);
$tpl->assign('require_calendar', true);
$tpl->assign('error_detected', $error_detected);
if (isset($warning_detected)) {
    $tpl->assign('warning_detected', $warning_detected);
}
$tpl->assign('members', $members_list);
$tpl->assign('nb_members', $members->getCount());
$tpl->assign('varslist', $varslist);
$tpl->assign(
    'filter_field_options',
    array(
        0 => _T("Name"),
        1 => _T("Address"),
        2 => _T("Email,URL,IM"),
        3 => _T("Job"),
        4 => _T("Infos")
    )
);
$tpl->assign(
    'filter_membership_options',
    array(
        0 => _T("All members"),
        3 => _T("Up to date members"),
        1 => _T("Close expiries"),
        2 => _T("Latecomers"),
        4 => _T("Never contributed"),
        5 => _T("Staff members"),
        6 => _T("Administrators")
    )
);
$tpl->assign(
    'filter_accounts_options',
    array(
        0 => _T("All accounts"),
        1 => _T("Active accounts"),
        2 => _T("Inactive accounts")
    )
);

$content = $tpl->fetch('gestion_adherents.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
