<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Get an exported file
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @author    Guillaume Rousse <guillomovitch@gmail.com>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2013-07-27
 */

use Analog\Analog as Analog;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !isset($_GET['file']) ) {
    Analog::log(
        'No requested file',
        Analog::INFO
    );
    header("HTTP/1.1 500 Internal Server Error");
    die();
}

$file = $_GET['file'];
$name = $_GET['name'];

//Exports main contain user confidential data, they're accessible only for
//admins or staff members
if ( $login->isAdmin() || $login->isStaff() ) {

    if (file_exists(GALETTE_FILES_PATH . $file) ) {
        // try to identify MIME type
        if (function_exists("finfo_open")) {
            // require PECL fileinfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $type = finfo_file($finfo, $file);
        } else {
            // deprecated
            $type = mime_content_type($file);
        }
        header('Content-Type: ' . $type);
        header('Content-Disposition: attachment; filename="' . $name . '";');
        header('Pragma: no-cache');
        readfile(GALETTE_FILES_PATH . $file);
    } else {
        Analog::log(
            'A request has been made to get an exported file named `' .
            $file .'` that does not exists.',
            Analog::WARNING
        );
        header('HTTP/1.0 404 Not Found');        
    }
} else {
    Analog::log(
        'A non authorized person asked to retrieve exported file named `' .
        $file . '`. Access has not been granted.',
        Analog::WARNING
    );
    header('HTTP/1.0 403 Forbidden');
}
