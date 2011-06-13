<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Utilities
 *
 * PHP version 5
 *
 * Copyright © 2007-2011 The Galette Team
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
 * @author    John Perr <johnperr@abul.org>
 * @author    Johan Cwiklinski <joahn@x-tnd.be>
 * @copyright 2007-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-31
 */

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header("location: index.php");
    die();
}
if ( !$login->isAdmin() ) {
    header("location: voir_adherent.php");
    die();
}

require_once WEB_ROOT . 'classes/models.class.php';

// initialize warnings
$error_detected = array();
$warning_detected = array();

$mods = new models();

if ( isset($_POST['fieldsfile']) ) {
    $_SESSION['galette']['fields_file'] = $_POST['exportfields'];
    header('location: fields_adh.php');
}

if ( isset($_POST['xmlupload']) ) {
    if ( $_FILES['loadxml']['tmp_name'] !='' ) {
        $err = $mods->readXMLModels($_FILES['loadxml']['tmp_name']);
        if ( $err ) {
            $error_detected = $mods->getError();
        } else {
            array_push(
                $warning_detected,
                $_FILES['loadxml']['name'] . _T(" sucessfully read.")
            );
        }
    } else {
        array_push($warning_detected, _T("Error, filename is empty"));
    }
}

if ( isset($_FILES['loadxml']) ) {
    $tpl->assign('loadxml', $_FILES['loadxml']['name']);
}
$tpl->assign(
    'exportfields', empty($_POST['exportfields']) ?
    'adh_fields.txt' :
    $_POST['exportfields']
);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('warning_detected', $warning_detected);
$content = $tpl->fetch('utilitaires.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');

?>
