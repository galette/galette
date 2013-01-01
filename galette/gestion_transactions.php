<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Transactions management
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

if ( !$login->isAdmin() && !$login->isStaff() ) {
    $id_adh = $login->id;
} else {
    $id_adh = get_numeric_form_value('id_adh', '');
}

$filtre_id_adh = '';

if ( isset($session['transactions']) ) {
    $trans = unserialize($session['transactions']);
} else {
    $trans = new Galette\Repository\Transactions();
}

if ( isset($_GET['page']) && is_numeric($_GET['page']) ) {
    $trans->current_page = (int)$_GET['page'];
}

if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
    $trans->show = $_GET['nbshow'];
}

if ( isset($_GET['tri']) ) {
    $trans->orderby = $_GET['tri'];
}

if ( ($login->isAdmin() || $login->isStaff()) && isset($_GET['id_adh']) && $_GET['id_adh'] != '' ) {
    if ( $_GET['id_adh'] == 'all' ) {
        $trans->filtre_cotis_adh = null;
    } else {
        $trans->filtre_cotis_adh = $_GET['id_adh'];
    }
}

if ( $login->isAdmin() || $login->isStaff() ) {
    $trans_id = get_numeric_form_value('sup', '');
    if ($trans_id != '') {
        $trans->removeTransactions($trans_id);
    }
}

$session['transactions'] = serialize($trans);
$list_trans = $trans->getTransactionsList(true);

//assign pagination variables to the template and add pagination links
$trans->setSmartyPagination($tpl);

$tpl->assign('page_title', _T("Transactions managment"));
$tpl->assign('require_dialog', true);
$tpl->assign('list_trans', $list_trans);
$tpl->assign('transactions', $trans);
$tpl->assign('nb_transactions', $trans->getCount());
if ( $trans->filtre_cotis_adh != null ) {
    $member = new Galette\Entity\Adherent();
    $member->load($trans->filtre_cotis_adh);
    $tpl->assign('member', $member);
}
$content = $tpl->fetch('gestion_transactions.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
