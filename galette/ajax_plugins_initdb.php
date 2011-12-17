<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Plugins database initialization
 *
 * This page can be loaded directly, or via ajax.
 * Via ajax, we do not have a full html page, but only
 * that will be displayed using javascript on another page
 *
 * PHP version 5
 *
 * Copyright © 2011 The Galette Team
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
 * @category  Plugins
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-12-17
 */

require_once 'includes/galette.inc.php';
if ( !$login->isLogged() || !$login->isAdmin() ) {
    $log->log(
        'Trying to display ajax_members.php without appropriate permissions',
        PEAR_LOG_INFO
    );
    die();
}

// check for ajax mode
$ajax = ( isset($_POST['ajax']) && $_POST['ajax'] == 'true' ) ? true : false;
$plugid = null;
$plugin = null;
if ( isset($_GET['plugid']) ) {
    $plugid = $_GET['plugid'];
}
if ( isset($_POST['plugid']) ) {
    $plugid = $_POST['plugid'];
}
if ( $plugid !== null ) {
    $plugin = $plugins->getModules($plugid);
}

if ( $plugin === null ) {
    $log->log(
        'Unable to load plugin `' . $plugid . '`!',
        PEAR_LOG_EMERG
    );
    die();
}

$step = 1;

if ( $step === 1 ) {
    //let's look for updates scripts
    $update_scripts = GaletteZendDb::getUpdateScripts($plugin['root']);
    if ( count($update_scripts) > 0 ) {
        $tpl->assign('update_scripts', $update_scripts);
    }
}

$tpl->assign('ajax', $ajax);
$tpl->assign('step', $step);
$tpl->assign('plugin', $plugin);

if ( $ajax ) {
    $tpl->assign('mode', 'ajax');
    $tpl->display('ajax_plugins_initdb.tpl');
} else {
    $content = $tpl->fetch('ajax_plugins_initdb.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
?>