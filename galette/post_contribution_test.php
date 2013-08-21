#!/usr/bin/php
<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Post configuration script test
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
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-06-10
 */

$args = array();
$internal = false;

if (defined('STDIN') ) {
    //we're called from command line
    $args = stream_get_contents(STDIN);
} else if ( count($_POST) > 0 ) {
    //we're called from HTTP POST
    $args = $_POST;
    //check if we're called from galette internal
    if ( isset($_POST['galette_internal']) ) {
        $internal = true;
        include_once 'includes/galette.inc.php';
        unset($_POST['galette_internal']);
        Analog\Analog::info(
            'Requested as Galette HTTP POST with parameters:' . "\n" .
            print_r($args, true)
        );
    } else {
        echo 'Requested as HTTP POST with parameters:<br/>';
    }
} else if ( count($_GET) > 0 ) {
    //we're called from HTTP GET
    echo 'Requested as HTTP GET with parameters:<br/>';
    $args = $_GET;
}

if ( count($args) == 0 ) {
    //we're called without arguments => exit.
    die('No arguments.');
}

if (defined('STDIN') ) {
    //a successfull script returns 0, we do not output anything
    $fp = fopen(__DIR__ . '/cache/galette_post_contrib_file.txt', 'w');
    fwrite($fp, $args);
    fclose($fp);
} else {
    $json_args = json_decode($args);
    foreach ( $json_args as $k=>$v ) {
        echo 'key: ' . $k . ' | value: ' . $v;
    }
}
