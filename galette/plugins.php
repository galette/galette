<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's plugins informations and managment
 *
 * PHP version 5
 *
 * Copyright © 2011-2013 The Galette Team
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
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-12-16
 */

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
} elseif ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
} else if ( !$login->isAdmin() ) {
    header('location: desktop.php');
    die();
}

$success_detected = array();
$error_detected = array();

if ( GALETTE_MODE !== 'DEMO' ) {
    $reload_plugins = false;
    if ( isset($_GET['activate']) ) {
        try {
            $plugins->activateModule($_GET['activate']);
            $success_detected[] = str_replace(
                '%name',
                $_GET['activate'],
                _T("Plugin %name has been enabled")
            );
            $reload_plugins = true;
        } catch (Exception $e) {
            $error_detected[] = $e->getMessage();
        }
    }

    if ( isset($_GET['deactivate']) ) {
        try {
            $plugins->deactivateModule($_GET['deactivate']);
            $success_detected[] = str_replace(
                '%name',
                $_GET['deactivate'],
                _T("Plugin %name has been disabled")
            );
            $reload_plugins = true;
        } catch (Exception $e) {
            $error_detected[] = $e->getMessage();
        }
    }

    //If some plugins have been (de)activated, we have to reload
    if ( $reload_plugins === true ) {
        $plugins->loadModules(GALETTE_PLUGINS_PATH, $i18n->getFileName());
    }
}

$plugins_list = $plugins->getModules();
$disabled_plugins = $plugins->getDisabledModules();

$tpl->assign('plugins_list', $plugins_list);
$tpl->assign('plugins_disabled_list', $disabled_plugins);

if ( count($error_detected) > 0 ) {
    $tpl->assign('error_detected', $error_detected);
}
if ( count($success_detected) > 0 ) {
    $tpl->assign('success_detected', $success_detected);
}

$tpl->assign('page_title', _T("Plugins"));
$tpl->assign('require_dialog', true);

$content = $tpl->fetch('plugins.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');

if ( isset($profiler) ) {
    $profiler->stop();
}
