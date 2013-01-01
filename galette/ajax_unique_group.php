<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Check for group name uniqueness
 *
 * This page can be loaded directly, or via ajax.
 * Via ajax, we do not have a full html page, but only
 * that will be displayed using javascript on another page
 *
 * PHP version 5
 *
 * Copyright © 2012-2013 The Galette Team
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
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-01-22
 */

use Analog\Analog as Analog;

require_once 'includes/galette.inc.php';

$name = $_POST['gname'];
if ( !$name ) {
    Analog::log(
        'Trying to check if group name is unique without name specified',
        Analog::INFO
    );
    die();
}

if ( !$login->isLogged() || !$login->isAdmin() && !$login->isStaff() ) {
    Analog::log(
        'Trying to display ajax_group.php without appropriate permissions',
        Analog::INFO
    );
    die();
}

// check for ajax mode
$ajax = ( isset($_POST['ajax']) && $_POST['ajax'] == 'true' ) ? true : false;

echo json_encode(array('success' => Galette\Repository\Groups::isUnique($name)));
