<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Import members from CSV file
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
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7ev - 2013-08-27
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

use Galette\IO\Csv;
use Galette\IO\CsvIn;
use Galette\Entity\Adherent;
use Galette\Entity\FieldsConfig;
use Galette\Repository\Members;

$csv = new CsvIn();

$written = array();
$dryrun = true;

if ( isset($_GET['sup']) ) {
    $res = $csv->remove($_GET['sup']);
    if ( $res === true ) {
        $success_detected[] = str_replace(
            '%export',
            $_GET['sup'],
            _T("'%export' file has been removed from disk.")
        );
    } else {
        $error_detected[] = str_replace(
            '%export',
            $_GET['sup'],
            _T("Cannot remove '%export' from disk :/")
        );
    }
}


// CSV file upload
if ( isset($_FILES['new_file']) ) {
    if ( $_FILES['new_file']['error'] === UPLOAD_ERR_OK ) {
        if ( $_FILES['new_file']['tmp_name'] !='' ) {
            if ( is_uploaded_file($_FILES['new_file']['tmp_name']) ) {
                $res = $csv->store($_FILES['new_file']);
                if ( $res < 0 ) {
                    $error_detected[] = $csv->getErrorMessage($res);
                }
            }
        }
    } else if ($_FILES['new_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        Analog::log(
            $csv->getPhpErrorMessage($_FILES['new_file']['error']),
            Analog::WARNING
        );
        $error_detected[] = $csv->getPhpErrorMessage(
            $_FILES['new_file']['error']
        );
    } else if ( isset($_POST['upload']) ) {
        $error_detected[] = _T("No files has been seleted for upload!");
    }
}

if ( isset($_POST['import']) && isset($_POST['import_file']) ) {
    if ( isset($_POST['dryrun']) ) {
        $dryrun = true;
    } else {
        $dryrun = false;
    }
    $res = $csv->import($_POST['import_file'], $members_fields, $dryrun);
    if ( $res !== true ) {
        if ( $res < 0 ) {
            $error_detected[] = $csv->getErrorMessage($res);
            if ( count($csv->getErrors()) > 0 ) {
                $error_detected = array_merge($error_detected, $csv->getErrors());
            }
        } else {
            $error_detected[] = _T("An error occured importing the file :(");
        }
        $tpl->assign('import_file', $_POST['import_file']);
    } else {
        $success_detected[] = str_replace(
            '%filename%',
            $_POST['import_file'],
            _T("File '%filename%' has been successfully imported :)")
        );
    }
}

$tpl->assign('dryrun', $dryrun);
$existing = $csv->getExisting();

$tpl->assign('page_title', _T("CVS members import"));
$tpl->assign('require_dialog', true);
//$tpl->assign('written', $written);
$tpl->assign('existing', $existing);
$tpl->assign('success_detected', $success_detected);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('warning_detected', $warning_detected);
$content = $tpl->fetch('import.tpl');
$tpl->assign('content', $content);

$tpl->display('page.tpl');
