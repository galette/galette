<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);


/**
 * Post configuration script test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

$args = [];
$internal = false;

if (defined('STDIN')) {
    //we're called from command line
    $args = stream_get_contents(STDIN);
} elseif (count($_POST) > 0) {
    //we're called from HTTP POST
    $args = $_POST;
    //check if we're called from galette internal
    if (isset($_POST['galette_internal'])) {
        $internal = true;
        include_once __DIR__ . '/../includes/galette.inc.php';
        unset($_POST['galette_internal']);
        Analog\Analog::info(
            'Requested as Galette HTTP POST with parameters:' . "\n" .
            print_r($args, true)
        );
    } else {
        echo 'Requested as HTTP POST with parameters:<br/>';
    }
} elseif (count($_GET) > 0) {
    //we're called from HTTP GET
    echo 'Requested as HTTP GET with parameters:<br/>';
    $args = $_GET;
}

if (count($args) == 0) {
    //we're called without arguments => exit.
    die('No arguments.');
}

if (defined('STDIN')) {
    //a successful script returns 0, we do not output anything
    $fp = fopen(__DIR__ . '/cache/galette_post_contrib_file.txt', 'w');
    fwrite($fp, $args);
    fclose($fp);
} else {
    $json_args = json_decode((string) $args['params']);
    echo json_encode($json_args, JSON_PRETTY_PRINT);
}
