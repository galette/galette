<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use Galette\Core\Galette;
use Galette\Core\LightSlimApp;
use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Galette\Webhooks\Dispatcher;

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
        $container->get(Preferences::class)
    );
    define('GALETTE_CRON', value: true);
}

if (!$container->get(Login::class)->isCron()) {
    die(1);
}

$report = $container->get(Dispatcher::class)->process();

//stay silent on success so cron does not notify the administrator on every run;
//only report (and fail) when a delivery has been given up on
if ($report->getFailed() > 0) {
    echo sprintf(
        //TRANS: %1$d is the number of delivered webhooks, %2$d the number of failed ones
        _T('Webhooks processed: %1$d delivered, %2$d failed.'),
        $report->getDelivered(),
        $report->getFailed()
    ) . "\n";
    exit(1);
}

exit(0);
