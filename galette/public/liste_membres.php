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
 * Copyright © 2006-2013 The Galette Team
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
 * @copyright 2006-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

use Galette\Filters\MembersList as MembersList;

define('GALETTE_BASE_PATH', '../');
require_once GALETTE_BASE_PATH . 'includes/galette.inc.php';
if ( !$preferences->showPublicPages() ) {
    //public pages are not actives
    header('location:'. GALETTE_BASE_PATH . 'index.php');
    die();
}

if ( isset($session['public_filters']['members']) ) {
    $filters = unserialize($session['public_filters']['members']);
} else {
    $filters = new MembersList();
}

// Filters
if (isset($_GET['page'])) {
    $filters->current_page = (int)$_GET['page'];
}

if ( isset($_GET['clear_filter']) ) {
    $filters->reinit();
}

//numbers of rows to display
if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
    $filters->show = $_GET['nbshow'];
}

// Sorting
if ( isset($_GET['tri']) ) {
    $filters->orderby = $_GET['tri'];
}


$m = new Galette\Repository\Members($filters);
$members = $m->getPublicList(false, null);

//assign pagination variables to the template and add pagination links
$filters->setSmartyPagination($tpl);
$tpl->assign('filters', $filters);
$session['public_filters']['members'] = serialize($filters);

$tpl->assign('page_title', _T("Members list"));
$tpl->assign('members', $members);
$tpl->assign('nb_members', $m->getCount());
$content = $tpl->fetch('liste_membres.tpl');
$tpl->assign('content', $content);
$tpl->display('public_page.tpl');
