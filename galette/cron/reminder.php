<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use Galette\Core\Db;
use Galette\Core\Galette;
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

$container = $app->getContainer();

Galette::loadRoutes(app: $app, cron: true);
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
