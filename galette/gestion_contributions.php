<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions management
 *
 * PHP version 5
 *
 * Copyright © 2004-2013 The Galette Team
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
 * @copyright 2004-2013 The Galette Team
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

if ( isset($session['contributions'])) {
    $contribs = unserialize($session['contributions']);
} else {
    $contribs = new Galette\Repository\Contributions();
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
    if ( isset($_GET['end_date_filter']) || isset($_GET['start_date_filter']) ) {
        try {
            if ( isset($_GET['start_date_filter']) ) {
                $field = _T("start date filter");
                $contribs->start_date_filter = $_GET['start_date_filter'];
            }
            if ( isset($_GET['end_date_filter']) ) {
                $field = _T("end date filter");
                $contribs->end_date_filter = $_GET['end_date_filter'];
            }
        } catch (Exception $e) {
            $error_detected[] = $e->getMessage();
        }
    }

    if ( isset($_GET['payment_type_filter']) ) {
        $ptf = $_GET['payment_type_filter'];
        if ( $ptf == Galette\Entity\Contribution::PAYMENT_OTHER
            || $ptf == Galette\Entity\Contribution::PAYMENT_CASH
            || $ptf == Galette\Entity\Contribution::PAYMENT_CREDITCARD
            || $ptf == Galette\Entity\Contribution::PAYMENT_CHECK
            || $ptf == Galette\Entity\Contribution::PAYMENT_TRANSFER
            || $ptf == Galette\Entity\Contribution::PAYMENT_PAYPAL
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

$session['contributions'] = serialize($contribs);
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
