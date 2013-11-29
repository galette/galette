<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Trombinoscope
 *
 * On affiche les adhérents qui ont souhaité rendre leurs informations
 * publiques et qui ont placé une photo sur Galette
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
 * @author    Alexandre 'laotseu' DE DOMMELIN <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2006-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

define('GALETTE_BASE_PATH', '../');
require_once GALETTE_BASE_PATH . 'includes/galette.inc.php';
if ( !$preferences->showPublicPages($login) ) {
    //public pages are not actives
    header('location:' . GALETTE_BASE_PATH  . 'index.php');
    die();
}

$m = new Galette\Repository\Members();
$members = $m->getPublicList(true, null);

$tpl->assign('page_title', _T("Trombinoscope"));
$tpl->assign('additionnal_html_class', 'trombinoscope');
$tpl->assign('members', $members);
$tpl->assign('time', time());
$content = $tpl->fetch('trombinoscope.tpl');
$tpl->assign('content', $content);
$tpl->display('public_page.tpl');
