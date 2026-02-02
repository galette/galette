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

use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\LightSlimApp;
use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Entity\Texts;
use Galette\Middleware\Authenticate;
use Galette\Repository\Reminders;

use function Safe\define;
use function Safe\session_start;

/** @ignore */
require_once __DIR__ . '/../includes/galette.inc.php';

/** @var Plugins $plugins */

session_start();
$gapp = new LightSlimApp(
    plugins: $plugins,
    mode: 'CRON'
);
$app = $gapp->getApp();

if (isset($needs_update) && $needs_update === true) {
    echo _T("Your Galette database is not present, or not up to date.");
    die(1);
}

/**
 * Authentication middleware
 */
$container = $app->getContainer();
$authenticate = $container->get(Authenticate::class);

require_once GALETTE_ROOT . 'includes/routes/main.routes.php';
require_once GALETTE_ROOT . 'includes/routes/authentication.routes.php';
require_once GALETTE_ROOT . 'includes/routes/management.routes.php';
require_once GALETTE_ROOT . 'includes/routes/members.routes.php';
require_once GALETTE_ROOT . 'includes/routes/groups.routes.php';
require_once GALETTE_ROOT . 'includes/routes/contributions.routes.php';
$cron = (PHP_SAPI === 'cli');
if ($cron) {
    $container->get(Login::class)->logCron(
        basename($argv[0], '.php'),
        $container->get(\Galette\Core\Preferences::class)
    );
    define('GALETTE_CRON', true);
}

if (!$container->get(Login::class)->isCron()) {
    die(1);
}

if ($cron && !defined('GALETTE_URI')) {
    echo _T('Please define constant "GALETTE_URI" with the path to your instance.') . "\n";
    die(1);
}

$texts = new Texts(
    $container->get(\Galette\Core\Preferences::class)
);
$reminders = new Reminders();
$success_detected = [];
$error_detected = [];

$list_reminders = $reminders->getList($container->get(Db::class), false);
if (count($list_reminders) > 0) {
    foreach ($list_reminders as $reminder) {
        //send reminders by email
        $sent = $reminder->send($texts, $container->get(History::class), $container->get(Db::class));

        if ($sent === true) {
            $success_detected[] = $reminder->getMessage();
        } else {
            $error_detected[] = $reminder->getMessage();
        }
    }

    if (count($error_detected) > 0) {
        array_unshift(
            $error_detected,
            _T("Reminder has not been sent:")
        );
    }

    if (count($success_detected) > 0) {
        array_unshift(
            $success_detected,
            _T("Sent reminders:")
        );
    }
}

//called from a cron. warning and errors has been stored into history
//and probably logged
if (count($error_detected) > 0) {
    //if there are errors, we print them
    echo "\n";
    $count = 0;
    foreach ($error_detected as $e) {
        if ($count > 0) {
            echo '    ';
        }
        echo $e . "\n";
        $count++;
    }
    //we can also print additional information.
    if (count($success_detected) > 0) {
        echo "\n";
        echo str_replace(
            '%i',
            (string)count($success_detected),
            _T("%i emails have been sent successfully.")
        );
    }
    exit(1);
} else {
    //if there were no errors, we just exit properly for cron to be quiet.
    exit(0);
}
