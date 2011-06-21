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
} elseif ( !$login->isAdmin() ) {
    header('location: voir_adherent.php');
    die();
}

//Reset Mailing object
if ( isset($_SESSION['galette']['mailing']) ) {
    $_SESSION['galette']['mailing'] = null;
    unset($_SESSION['galette']['mailing']);
}

require_once 'classes/varslist.class.php';

if ( isset($_SESSION['galette']['varslist']) ) {
    $varslist = unserialize($_SESSION['galette']['varslist']);
} else {
    $varslist = new VarsList();
}

require_once 'classes/members.class.php';

$error_detected = array();
// Set caller page ref for cards error reporting
$_SESSION['galette']['caller'] = 'gestion_adherents.php';

if (   isset($_POST['cards'])
    || isset($_POST['labels'])
    || isset($_POST['mailing'])
) {
    if (isset($_POST['member_sel'])) {
        $varslist->selected = $_POST['member_sel'];
        $_SESSION['galette']['varslist'] = serialize($varslist);

        if (isset($_POST['cards'])) {
            $qstring = 'carte_adherent.php';
        }
        if (isset($_POST['labels'])) {
            $qstring = 'etiquettes_adherents.php';
        }
        if (isset($_POST['mailing'])) {
            $qstring = 'mailing_adherents.php';
        }
        header('location: '.$qstring);
    } else {
        $error_detected[]
            = _T("No member was selected, please check at least one name.");
    }
}

if (isset($_SESSION['galette']['pdf_error']) && $_SESSION['galette']['pdf_error']) {
    $error_detected[] = $_SESSION['galette']['pdf_error_msg'];
    unset($_SESSION['galette']['pdf_error_msg']);
    unset($_SESSION['galette']['pdf_error']);
}

// Filters
if (isset($_GET['page'])) {
    $varslist->current_page = (int)$_GET['page'];
}

if ( isset($_GET['clear_filter']) ) {
    $varslist->reinit();
} else if ( isset($_GET['filter_str']) ) { //filter search string
    $varslist->filter_str = stripslashes(
        htmlspecialchars($_GET['filter_str'], ENT_QUOTES)
    );
    //filed for filter
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
}

//numbers of rows to display
if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
    $hist->show = $_GET['nbshow'];
}

// Sorting
if ( isset($_GET['tri']) ) {
    $varslist->orderby = $_GET['tri'];
}

//delete members
/** TODO: Members object must do that stuff */
if (isset($_GET['sup']) || isset($_POST['delete'])) {
    /** TODO: finalize the new function in Members class */
    /*$m = new Members();
    if ( isset($_GET['sup']) ) {
        $m->removeMembers($_GET['sup']);
    } else if ( isset($_POST['member_sel']) ) {
        $m->removeMembers($_POST['member_sel']);
    }*/

    $array_sup = array();
    if (isset($_GET['sup'])) {
        if (is_numeric($_GET['sup'])) {
            $array_sup[] = $_GET['sup'];
        }
    } else {
        if (isset($_POST['member_sel'])) {
            foreach ($_POST['member_sel'] as $supval) {
                if (is_numeric($supval)) {
                    $array_sup[] = $supval;
                }
            }
        }
    }

    foreach ($array_sup as $supval) {
        $requetesup = 'SELECT nom_adh, prenom_adh FROM ' . PREFIX_DB .
            'adherents WHERE id_adh=' . $DB->qstr($supval, get_magic_quotes_gpc());
        $resultat = $DB->Execute($requetesup);
        if (!$resultat->EOF) {
            $member = new Adherent((int)$supval);

            // supression record adhérent
            $requetesup = 'DELETE FROM ' . PREFIX_DB . 'adherents WHERE id_adh=' .
                $DB->qstr($supval, get_magic_quotes_gpc());
            $DB->Execute($requetesup);
            $hist->add(
                "Delete the member card (and dues)",
                strtoupper($resultat->fields[0]) . ' ' . $resultat->fields[1],
                $requetesup
            );

            // suppression records cotisations
            $requetesup = 'DELETE FROM ' . PREFIX_DB . 'cotisations WHERE id_adh=' .
                $DB->qstr($supval, get_magic_quotes_gpc());
            $DB->Execute($requetesup);

            // erase custom fields
            /** FIXME: no table named 'adh_info'... */
            /*$requetesup = "DELETE FROM ".PREFIX_DB."adh_info WHERE id_adh=".
                $DB->qstr($supval, get_magic_quotes_gpc());
            $DB->Execute($requetesup);*/

            // erase picture
            /** TODO: picture should be removed by object when member is deleted */
            $member->picture->delete($DB->qstr($supval, get_magic_quotes_gpc()));
        }
        $resultat->Close();
    }
}

$members = new Members();
$members_list = $members->getMembersList(true);

$_SESSION['galette']['varslist'] = serialize($varslist);

//assign pagination variables to the template and add pagination links
$varslist->setSmartyPagination($tpl);

$tpl->assign('page_title', _T("Members management"));
$tpl->assign('require_dialog', true);
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
        4 => _T("Never contributed")
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
