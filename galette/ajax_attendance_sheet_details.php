<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Add details on PDF attendance sheet generation
 *
 * User have to select members in the member's list to generate labels.
 * Format is defined in the preferences screen
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
 * @category  Print
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-11-26
 */

use Analog\Analog as Analog;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() || !$login->isAdmin() && !$login->isStaff() && !$login->isGroupManager() ) {
    Analog::log(
        'Trying to display ajax_attendance_sheet_details.php without appropriate permissions',
        Analog::INFO
    );
    die();
}

if ( isset($session['filters']['members']) ) {
    $filters = unserialize(
        $session['filters']['members']
    );
} else {
    $filters = new Galette\Filters\MembersList();
}

// check for ajax mode
$ajax = ( isset($_POST['ajax']) && $_POST['ajax'] == 'true' ) ? true : false;
//retrieve selected members
$selection = ( isset($_POST['selection']) ) ? $_POST['selection'] : array();

$filters->selected = $selection;
$session['filters']['members'] = serialize(
    $filters
);

$tpl->assign('ajax', $ajax);
$tpl->assign('selection', $selection);

if ( $ajax ) {
    $tpl->display('ajax_attendance_sheet_details.tpl');
} else {
    $content = $tpl->fetch('ajax_attendance_sheet_details.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
