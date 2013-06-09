<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF management
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
 * @since     Availaible since 0.7.5dev - 2013-02-19
 */

use Galette\Entity\PdfModel;
use Galette\Repository\PdfModels;

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

$id = 1;
if ( isset($_GET['id']) ) {
    $id = (int)$_GET['id'];
} else if ( isset($_POST[PdfModel::PK]) ) {
    $id = (int)$_POST[PdfModel::PK];
}

$model = null;
if ( isset($_POST['store']) && $_POST['store'] == 'true' ) {
    $type = null;
    if ( isset($_POST['model_type']) ) {
        $type = (int)$_POST['model_type'];
    }

    if ( $type === null ) {
        $error_detected[] = _T("Missing PDF model type!");
    } else {
        $class = PdfModel::getTypeClass($type);
        if ( isset($_POST[PdfModel::PK]) ) {

            $model = new $class($zdb, $preferences, (int)$_POST[PdfModel::PK]);
        } else {
            $model = new $class($zdb, $preferences);
        }

        try {
            $model->header = $_POST['model_header'];
            $model->footer = $_POST['model_footer'];
            $model->type = $type;
            if ( isset($_POST['model_body']) ) {
                $model->body = $_POST['model_body'];
            }
            if ( isset($_POST['model_title']) ) {
                $model->title = $_POST['model_title'];
            }
            if ( isset($_POST['model_body']) ) {
                $model->subtitle = $_POST['model_subtitle'];
            }
            if ( isset($_POST['model_styles']) ) {
                $model->styles = $_POST['model_styles'];
            }
            $res = $model->store();
            if ( $res === true ) {
                $success_detected[] = _T("Model has been successfully stored!");
            } else {
                $error_detected[] = _T("Model has not been stored :(");
            }
        } catch ( \Exception $e ) {
            $error_detected[] = $e->getMessage();
        }
    }
}

$ms = new PdfModels($zdb, $preferences);
$models = $ms->getList();

foreach ( $models as $m ) {
    if ( $m->id === $id ) {
        $model = $m;
        break;
    }
}

$ajax = false;
if ( isset($_GET['ajax']) && $_GET['ajax'] == 'true' ) {
    $ajax = true;
}

if ( $ajax ) {
    $tpl->assign('model', $model);
    $tpl->display('gestion_pdf_content.tpl');
} else {
    $tpl->assign('page_title', _T("PDF models"));
    $tpl->assign('error_detected', $error_detected);
    $tpl->assign('success_detected', $success_detected);
    $tpl->assign('models', $models);
    $tpl->assign('require_tabs', true);
    $tpl->assign('require_dialog', true);
    $tpl->assign('model', $model);
    $content = $tpl->fetch('gestion_pdf.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
