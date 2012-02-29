<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions management
 *
 * PHP version 5
 *
 * Copyright © 2004-2012 The Galette Team
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
 * @author    Frédéric Jacquot <unknown@unknwown.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2012 The Galette Team
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

$filtre_id_adh = '';

$ajax = false;
if ( isset($_POST['ajax']) && $_POST['ajax'] == 'true'
    || isset($_GET['ajax']) && $_GET['ajax'] == 'true'
) {
    $ajax = true;
}

require_once 'classes/contributions.class.php';
if ( isset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['contributions'])) {
    $contribs = unserialize($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['contributions']);
} else {
    $contribs = new Contributions();
}

if ( $ajax === true ) {
    $contribs->filtre_transactions = true;
    if ( isset($_POST['max_amount']) ) {
        $contribs->max_amount = (int)$_POST['max_amount'];
    } else if ( $_GET['max_amount'] ) {
        $contribs->max_amount = (int)$_GET['max_amount'];
    }
} else {
    $contribs->max_amount = null;
}

if ( isset($_GET['page']) && is_numeric($_GET['page']) ) {
    $contribs->current_page = (int)$_GET['page'];
}

if ( (isset($_GET['nbshow']) && is_numeric($_GET['nbshow']))
) {
    $contribs->show = $_GET['nbshow'];
}

if ( (isset($_POST['nbshow']) && is_numeric($_POST['nbshow']))
) {
    $contribs->show = $_POST['nbshow'];
}

if ( isset($_GET['tri']) ) {
    $contribs->orderby = $_GET['tri'];
}

if ( isset($_GET['clear_filter']) ) {
    $contribs->reinit();
} else {
    if ( isset($_GET['start_date_filter']) ) {
        if ( preg_match(
            "@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@",
            $_GET['start_date_filter'],
            $array_jours
        ) ) {
            if ( checkdate($array_jours[2], $array_jours[1], $array_jours[3]) ) {
                $contribs->start_date_filter = $_GET['start_date_filter'];
            } else {
                $error_detected[] = _T("- Non valid date!");
            }
        } elseif (
            preg_match("/^([0-9]{4})$/", $_GET['start_date_filter'], $array_jours)
        ) {
            $contribs->start_date_filter = "01/01/".$array_jours[1];
        } elseif ( $_GET['start_date_filter'] == '' ) {
            $contribs->start_date_filter = null;
        } else {
            $error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!");
        }
    }

    if ( isset($_GET['end_date_filter']) ) {
        if ( preg_match(
            "@^([0-9]{2})/([0-9]{2})/([0-9]{4})$@",
            $_GET['end_date_filter'],
            $array_jours
        ) ) {
            if ( checkdate($array_jours[2], $array_jours[1], $array_jours[3]) ) {
                $contribs->end_date_filter = $_GET['end_date_filter'];
            } else {
                $error_detected[] = _T("- Non valid date!");
            }
        } elseif (
            preg_match("/^([0-9]{4})$/", $_GET['end_date_filter'], $array_jours)
        ) {
            $contribs->end_date_filter = "01/01/".$array_jours[1];
        } elseif ( $_GET['end_date_filter'] == '' ) {
            $contribs->end_date_filter = null;
        } else {
            $error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!");
        }
    }

    if ( isset($_GET['payment_type_filter']) ) {
        $ptf = $_GET['payment_type_filter'];
        if ( $ptf == Contribution::PAYMENT_OTHER
            || $ptf == Contribution::PAYMENT_CASH
            || $ptf == Contribution::PAYMENT_CREDITCARD
            || $ptf == Contribution::PAYMENT_CHECK
            || $ptf == Contribution::PAYMENT_TRANSFER
            || $ptf == Contribution::PAYMENT_PAYPAL
        ) {
            $contribs->payment_type_filter = $ptf;
        } elseif ( $ptf == -1 ) {
            $contribs->payment_type_filter = null;
        } else {
            $error_detected[] = _T("- Unknown payment type!");
        }
    }
}

if ( ($login->isAdmin() || $login->isStaff()) && isset($_GET['id_adh']) && $_GET['id_adh'] != '' ) {
    if ( $_GET['id_adh'] == 'all' ) {
        $contribs->filtre_cotis_adh = null;
    } else {
        $contribs->filtre_cotis_adh = $_GET['id_adh'];
    }
}

if ( $login->isAdmin() || $login->isStaff() ) {
    //delete contributions
    if (isset($_GET['sup']) || isset($_POST['delete'])) {
        if ( isset($_GET['sup']) ) {
            $contribs->removeContributions($_GET['sup']);
        } else if ( isset($_POST['contrib_sel']) ) {
            $contribs->removeContributions($_POST['contrib_sel']);
        }
    }
}

$_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['contributions'] = serialize($contribs);
$list_contribs = $contribs->getContributionsList(true);

//assign pagination variables to the template and add pagination links
$contribs->setSmartyPagination($tpl);

$tpl->assign('page_title', _T("Contributions managment"));
$tpl->assign('max_amount', $contribs->max_amount);
$tpl->assign('require_dialog', true);
$tpl->assign('require_calendar', true);
if (isset($error_detected)) {
    $tpl->assign('error_detected', $error_detected);
}
if (isset($warning_detected)) {
    $tpl->assign('warning_detected', $warning_detected);
}

$tpl->assign('list_contribs', $list_contribs);
$tpl->assign('contributions', $contribs);
if ( $contribs->filtre_cotis_adh != null && !$ajax ) {
    $member = new Galette\Entity\Adherent();
    $member->load($contribs->filtre_cotis_adh);
    $tpl->assign('member', $member);
}
$tpl->assign('nb_contributions', $contribs->getCount());

$tpl->assign('mode', 'std');
if ( $ajax ) {
    $tpl->assign('mode', 'ajax');
    $tpl->display('gestion_contributions.tpl');
} else {
    $content = $tpl->fetch('gestion_contributions.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
?>
