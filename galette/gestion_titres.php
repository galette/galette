<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Titles management
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
 * @since     Availaible since 0.7.4dev - 2013-01-27
 */

use Galette\Entity\Title as Title;
use Galette\Repository\Titles as Titles;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
} elseif ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
} elseif ( !$login->isAdmin() ) {
    header('location: gestion_adherents.php');
    die();
}

//delete members
if ( isset($_GET['del']) ) {
    if ( isset($_GET['del']) ) {
        $title = new Title((int)$_GET['del']);
        try {
            $res = $title->remove($zdb);
            if ( $res === true ) {
                $success_detected[] = str_replace(
                    '%name',
                    $title->short,
                    _T("Title '%name' has been successfully deleted.")
                );
            } else {
                $error_detected[] = str_replace(
                    '%name',
                    $title->short,
                    _T("An error occured removing title '%name' :(")
                );
            }
        } catch (\RuntimeException $re) {
            $error_detected[] = $re->getMessage();
        } catch (\Exception $e) {
            if ($e->getCode() === 23503) {
                $error_detected[] = _T("That title is still in use, you cannot delete it!");
            }
        }
    }
}

if (isset($_POST['new']) && $_POST['new'] == '1') {
    //add new title
    $title = new Title();

    $title->short = $_POST['short_label'];
    $title->long = $_POST['long_label'];

    $res = $title->store($zdb);

    if ( !$res ) {
        $error_detected[] = preg_replace(
            '(%s)',
            $title->short,
            _T("Title '%s' has not been added!")
        );
    } else {
        $success_detected[] = preg_replace(
            '(%s)',
            $title->short,
            _T("Title '%s' has been successfully added.")
        );
    }
}

$titles = Titles::getList($zdb);

$tpl->assign('page_title', _T("Titles management"));
$tpl->assign('titles_list', $titles);
//$tpl->assign('require_dialog', true);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('success_detected', $success_detected);
$content = $tpl->fetch('gestion_titres.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
