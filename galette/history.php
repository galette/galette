<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * History
 *
 * PHP version 5
 *
 * Copyright © 2003-2010 The Galette Team
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
 * @copyright 2003-2010 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 */

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin()) {
    header('location: voir_adherent.php');
    die();
}

if ( isset($_GET['reset']) && $_GET['reset'] == 1 ) {
    $hist->clean();
}


if ( isset($_GET['page']) && is_numeric($_GET['page']) ) {
    $hist->current_page = (int)$_GET['page'];
}

if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
    $hist->show = $_GET['nbshow'];
}

/** FIXME: should be handled in GalettePagination */
if ( isset($_GET['tri']) ) {
    if ( $_GET['tri'] == $hist->orderby ) {//ordre inverse
        $hist->invertorder();
    } else {//ordre normal
        $hist->orderby = $_GET['tri'];
        $hist->setDirection(History::ORDER_ASC);
    }
}

$logs = array();

$logs = $hist->getHistory();
$_SESSION['galette']['history'] = serialize($hist);

$paginate = null;
$tabs = "\t\t\t\t\t\t";

/** Pagination */
if ( $hist->current_page < 11 ) {
    $idepart=1;
} else {
    $idepart = $hist->current_page - 10;
}
if ( $hist->current_page + 10 < $hist->pages ) {
    $ifin = $hist->current_page + 10;
} else {
    $ifin = $hist->pages;
}

$next = $hist->current_page + 1;
$previous = $hist->current_page - 1;

if ( $hist->current_page != 1 ) {
    $paginate .= "\n" . $tabs . "<li><a href=\"index.php?page=1\" title=\"" .
         _T("First page") . "\">&lt;&lt;</a></li>\n";
    $paginate .= $tabs . "<li><a href=\"?page=" . $previous . "\" title=\"" .
        preg_replace("(%i)", $previous, _T("Previous page (%i)")) .
        "\">&lt;</a></li>\n";
}

for ( $i = $idepart ; $i <= $ifin ; $i++ ) {
    if ( $i == $hist->current_page ) {
        $paginate .= $tabs . "<li class=\"current\"><a href=\"#\" title=\"" .
            preg_replace("(%i)", $hist->current_page, _T("Current page (%i)")) .
            "\">-&nbsp;$i&nbsp;-</a></li>\n";
    } else {
        $paginate .= $tabs . "<li><a href=\"?page=" . $i . "\" title=\"" .
            preg_replace("(%i)", $i, _T("Page %i")) . "\">" . $i . "</a></li>\n";
    }
}
if ($hist->current_page != $hist->pages ) {
    $paginate .= $tabs . "<li><a href=\"?page=" . $next . "\" title=\"" .
        preg_replace("(%i)", $next, _T("Next page (%i)")) . "\">&gt;</a></li>\n";
    $paginate .= $tabs . "<li><a href=\"?page=" . $hist->pages . "\" title=\"" .
        preg_replace("(%i)", $hist->pages, _T("Last page (%i)")) .
        "\">&gt;&gt;</a></li>\n";
}
/** /Pagination */

$tpl->assign('logs', $logs);
$tpl->assign('nb_lines', count($logs));
$tpl->assign('nb_pages', $hist->pages);
$tpl->assign('page', $hist->current_page);
$tpl->assign('numrows', $hist->show);
$tpl->assign('history', $hist);
$tpl->assign('pagination', $paginate);
$tpl->assign(
    'nbshow_options',
    array(
        10 => "10",
        20 => "20",
        50 => "50",
        100 => "100",
        0 => _T("All")
    )
);
$content = $tpl->fetch('history.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
