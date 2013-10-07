<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing preview
 *
 * This page can be loaded directly, or via ajax.
 * Via ajax, we do not have a full html page, but only
 * that will be displayed using javascript on another page
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
 * @category  Plugins
 * @package   GaletteAuto
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id: owners.php 556 2009-03-13 06:48:49Z trashy $
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-08-28
 */

use Analog\Analog as Analog;
use Galette\Core\Mailing;
use Galette\Core\MailingHistory;

require_once 'includes/galette.inc.php';

if ( !$login->isLogged() || !$login->isAdmin() && !$login->isStaff() ) {
    Analog::log(
        'Trying to display ajax_mailing_preview.php without appropriate permissions',
        Analog::INFO
    );
    die();
}

// check for ajax mode
$ajax = ( isset($_POST['ajax']) && $_POST['ajax'] == 'true' ) ? true : false;

$mailing = null;
if ( isset($_GET['id']) && is_numeric($_GET['id']) ) {
    $mailing = new Mailing(null);
    MailingHistory::loadFrom((int)$_GET['id'], $mailing);
} else {
    $mailing = unserialize($session['mailing']);

    $mailing->subject = $_POST['subject'];
    $mailing->message = $_POST['body'];
    $mailing->html = ($_POST['html'] === 'true');
}

$tpl->assign(
    'mailing_sender',
    $preferences->pref_email_nom . ' &lt;' . $preferences->pref_email . '&gt;'
);

//Set the path to the current plugin's templates,
//but backup main Galette's template path before
$tpl->assign('ajax', $ajax);
$tpl->assign('mailing', $mailing);
$tpl->assign('recipients', $mailing->recipients);

if ( $ajax ) {
    $tpl->assign('mode', 'ajax');
    $tpl->display('ajax_mailing_preview.tpl');
} else {
    $content = $tpl->fetch('ajax_mailing_preview.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
