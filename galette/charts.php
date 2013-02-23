<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette charts
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-02-02
 */

use Galette\IO\Charts;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() || !$login->isAdmin() && !$login->isStaff() ) {
    header('location: index.php');
    die();
}

$charts = new Charts(
    array(
        Charts::MEMBERS_STATUS_PIE,
        Charts::MEMBERS_STATEDUE_PIE,
        Charts::CONTRIBS_TYPES_PIE,
        Charts::COMPANIES_OR_NOT,
        Charts::CONTRIBS_ALLTIME
    )
);
$data = array();

$tpl->assign('page_title', _T("Charts"));
$tpl->assign('charts', $charts->getCharts());
$tpl->assign('require_charts', true);
$content = $tpl->fetch('charts.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
