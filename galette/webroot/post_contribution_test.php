<?php

/**
 * Copyright © 2003-2026 The Galette Team
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
 * Post contribution script test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */

//use the SCRIPT_AUTH_TOKEN constant defined in your config.inc.php file
$script_auth_token = 'change_this_value_with_a_very_strong_password';

$args = [];
$internal = false;

if (defined('STDIN')) {
    //we're called from command line
    $args = stream_get_contents(STDIN); //@phpstan-ignore theCodingMachineSafe.function
} elseif (count($_POST) > 0) {
    //we're called from HTTP POST
    $args = $_POST;
    //check if we're called from galette internal
    if (isset($_POST['galette_internal'])) {
        $internal = true;
        include_once __DIR__ . '/../includes/galette.inc.php';
        unset($_POST['galette_internal']);
        Analog\Analog::info(
            'Requested as Galette HTTP POST with parameters:' . "\n"
            . print_r($args, true)
        );
    } else {
        echo 'Requested as HTTP POST with parameters:<br/>';
    }
} elseif (count($_GET) > 0) {
    //we're called from HTTP GET
    echo 'Requested as HTTP GET with parameters:<br/>';
    $args = $_GET;
}

if (empty($args)) {
    //we're called without arguments => exit.
    echo 'No arguments.';
    die(1);
}

if (defined('STDIN')) {
    $json_args = json_decode((string)$args); //@phpstan-ignore theCodingMachineSafe.function
} else {
    $json_args = isset($args['params']) ? json_decode((string)$args['params']) : json_decode('{"auth_token": ""}'); //@phpstan-ignore theCodingMachineSafe.function,theCodingMachineSafe.function
}
if ($script_auth_token !== $json_args->auth_token) {
    //we're called without authentication token => exit.
    echo 'Unauthorized call.';
    die(1);
}

if (defined('STDIN')) {
    //a successful script returns 0, we do not output anything
    $fp = fopen(__DIR__ . '/../data/cache/galette_post_contrib_file.txt', 'w'); //@phpstan-ignore theCodingMachineSafe.function
    fwrite($fp, $args); //@phpstan-ignore theCodingMachineSafe.function
    fclose($fp); //@phpstan-ignore theCodingMachineSafe.function
} else {
    echo json_encode($json_args, JSON_PRETTY_PRINT); //@phpstan-ignore theCodingMachineSafe.function
}
