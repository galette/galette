<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Liste publique des adhérents
 *
 * On affiche les adhérents qui ont souhaité rendre leurs informations
 * publiques.
 *
 * PHP version 5
 *
 * Copyright © 2006-2011 The Galette Team
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
 * @category  PublicPages
 * @package   Galette
 *
 * @author    Loïs 'GruiicK' Taulelle <gruiick@gmail.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2006-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

$base_path = '../';
require_once $base_path . 'includes/galette.inc.php';
if ( !$preferences->showPublicPages() ) {
    //public pages are not actives
    header('location:../index.php');
}

require_once $base_path . 'classes/varslist.class.php';

if ( isset($_SESSION['galette']['public_varslist'])  ) {
    $varslist = unserialize($_SESSION['galette']['public_varslist']);
} else {
    $varslist = new VarsList();
}

// Filters
if (isset($_GET['page'])) {
    $varslist->current_page = (int)$_GET['page'];
}

if ( isset($_GET['clear_filter']) ) {
    $varslist->reinit();
}

//numbers of rows to display
if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
    $varslist->show = $_GET['nbshow'];
}

// Sorting
if ( isset($_GET['tri']) ) {
    $varslist->orderby = $_GET['tri'];
}

$members = Members::getPublicList(false, null);

$_SESSION['galette']['public_varslist'] = serialize($varslist);

//assign pagination variables to the template and add pagination links
$varslist->setSmartyPagination($tpl);
$tpl->assign('varslist', $varslist);

$tpl->assign('page_title', _T("Members list"));
$tpl->assign('members', $members);
$content = $tpl->fetch('liste_membres.tpl');
$tpl->assign('content', $content);
$tpl->display('public_page.tpl');
?>

