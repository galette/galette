<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Edit titles
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
 * @since     Available since 0.7.4dev 2013-01-27
 */

use Analog\Analog as Analog;
use Galette\Entity\Title as Title;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
} else if ( !$login->isAdmin() ) {
    header('location: gestion_adherents.php');
    die();
}

if ( isset($_POST['cancel']) ) {
    header('location: gestion_titres.php');
    die();
}

if (isset($_POST['id']) && !isset($_GET['id']) ) {
    $_GET['id'] = $_POST['id'];
}
$title = new Title((int)$_GET['id']);

if ( isset($_POST['id']) ) {
    $title->short = $_POST['short_label'];
    $title->long = $_POST['long_label'];

    $res = $title->store($zdb);

    if ( !$res ) {
        $error_detected[] = preg_replace(
            '(%s)',
            $title->short,
            _T("Title '%s' has not been modified!")
        );
    } else {
        $success_detected[] = preg_replace(
            '(%s)',
            $title->short,
            _T("Title '%s' has been successfully modified.")
        );
        $session['success_detected'] = serialize($success_detected);
        header('location: gestion_titres.php');
        die();
    }
}

$tpl->assign('page_title', _T("Edit title"));
$tpl->assign('title', $title);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('success_detected', $success_detected);

$content = $tpl->fetch('edit_title.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
