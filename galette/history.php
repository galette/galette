<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * History
 *
 * PHP version 5
 *
 * Copyright © 2003-2013 The Galette Team
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
 * @author    Frédéric Jaqcuot <unknow@unknwow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

use Galette\Core\history as History;

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
}

if ( isset($_GET['reset']) && $_GET['reset'] == 1 ) {
    $hist->clean();
    //reinitialize object after flush
    $hist = new History();
}

if ( isset($_GET['page']) && is_numeric($_GET['page']) ) {
    $hist->current_page = (int)$_GET['page'];
}

if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
    $hist->show = $_GET['nbshow'];
}

if ( isset($_GET['tri']) ) {
    $hist->tri = $_GET['tri'];
}

$logs = array();

$logs = $hist->getHistory();
$session['history'] = serialize($hist);

//assign pagination variables to the template and add pagination links
$hist->setSmartyPagination($tpl);

$tpl->assign('page_title', _T("Logs"));
$tpl->assign('logs', $logs);
$tpl->assign('nb_lines', count($logs));
$tpl->assign('history', $hist);
$content = $tpl->fetch('history.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
