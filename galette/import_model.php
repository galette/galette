<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CSV import model
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
 * @since     Availaible since 0.7.6dev - 2013-09-26
 */

use Analog\Analog as Analog;
use Galette\IO\Csv as Csv;
use Galette\IO\CsvIn as CsvIn;
use Galette\IO\CsvOut as CsvOut;
use Galette\Entity\ImportModel as ImportModel;

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header("location: index.php");
    die();
}
if ( !$login->isAdmin() ) {
    header("location: voir_adherent.php");
    die();
}

$model = new ImportModel();

if ( isset($_POST['fields']) ) {
    $model->setFields($_POST['fields']);
    $res = $model->store($zdb);
    if ( $res === true ) {
        $success_detected[] = _T("Import model has been successfully stored :)");
    } else {
        $error_detected[] = _T("Import model has not been stored :(");
    }
}

if ( isset($_GET['remove']) ) {
    $model->remove($zdb);
}

$csv = new CsvIn();

/** FIXME: 
 * - set fields that should not be part of import
 * - set fields that must be part of import, and visually disable them in the list
 */

$model->load();
$fields = $model->getFields();
$defaults = $csv->getDefaultFields();
$defaults_loaded = false;

if ( $fields === null ) {
    $fields = $defaults;
    $defaults_loaded = true;
}

if ( isset($_GET['generate'] ) ) {
    $ocsv = new CsvOut();
    $res = $ocsv->export(
        $fields,
        Csv::DEFAULT_SEPARATOR,
        Csv::DEFAULT_QUOTE,
        $fields
    );
    $filename = _T("galette_import_model.csv");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    header('Pragma: no-cache');
    echo $res;
} else {
    $tpl->assign('success_detected', $success_detected);
    $tpl->assign('error_detected', $error_detected);

    $tpl->assign('fields', $fields);
    $tpl->assign('model', $model);
    $tpl->assign('defaults', $defaults);
    $import_fields = $members_fields;
    //we do not want to import id_adh. Never.
    unset($import_fields['id_adh']);
    $tpl->assign('members_fields', $import_fields);
    $tpl->assign('defaults_loaded', $defaults_loaded);

    $tpl->assign('require_tabs', true);
    $tpl->assign('require_dialog', true);
    $tpl->assign('page_title', _T("CVS import model"));
    $content = $tpl->fetch('import_model.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}

