<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailings managment
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
 */

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
}

use Galette\Core;

$mailhist = new Core\MailingHistory();

if ( isset($_GET['reset']) && $_GET['reset'] == 1 ) {
    $mailhist->clean();
    //reinitialize object after flush
    $mailhist = new Core\MailingHistory();
}

//delete mailings
if (isset($_GET['sup']) || isset($_POST['delete'])) {
    if ( isset($_GET['sup']) ) {
        $mailhist->removeEntries($_GET['sup']);
    } else if ( isset($_POST['member_sel']) ) {
        $mailhist->removeEntries($_POST['member_sel']);
    }
}

if ( isset($_GET['page']) && is_numeric($_GET['page']) ) {
    $mailhist->current_page = (int)$_GET['page'];
}

if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
    $mailhist->show = $_GET['nbshow'];
}

if ( isset($_GET['tri']) ) {
    $mailhist->orderby = $_GET['tri'];
}

$history_list = array();

$history_list = $mailhist->getHistory();

//assign pagination variables to the template and add pagination links
$mailhist->setSmartyPagination($tpl);

$tpl->assign('page_title', _T("Mailings"));
$tpl->assign('require_dialog', true);
$tpl->assign('logs', $history_list);
$tpl->assign('nb_lines', count($history_list));
$tpl->assign('history', $mailhist);
$content = $tpl->fetch('gestion_mailings.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
