<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use Galette\Core\Galette;
use Galette\Core\LightSlimApp;
use Galette\Core\Login;
use Galette\Core\MailingQueue;
use Galette\Core\Plugins;
use Galette\Core\Preferences;

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
    define('GALETTE_CRON', true);
}

if (!$container->get(Login::class)->isCron()) {
    die(1);
}

if ($cron && !defined('GALETTE_URI')) {
    echo _T('Please define constant "GALETTE_URI" with the path to your instance.') . "\n";
    die(1);
}

$preferences = $container->get(Preferences::class);
$queue = new MailingQueue(
    $container->get(\Galette\Core\Db::class),
    $preferences
);
$delay = (int)$preferences->pref_mail_batch_delay;

$total_sent = 0;
$total_failed = 0;
$rate_limited = false;

//drain the queue batch after batch, respecting the configured delay,
//until it is empty or a rate limit is reached
do {
    $progress = $queue->processBatch();
    $total_sent += (int)$progress['batch_sent'];
    $total_failed += (int)$progress['batch_failed'];

    if ($progress['done'] === true) {
        break;
    }
    if ($progress['rate_limited'] === true) {
        $rate_limited = true;
        break;
    }

    if ($delay > 0) {
        sleep($delay);
    }
} while (true);

if ($total_sent > 0 || $total_failed > 0) {
    echo str_replace(
        ['%sent', '%failed'],
        [(string)$total_sent, (string)$total_failed],
        _T("Mailing queue processed: %sent sent, %failed failed.")
    ) . "\n";
}

if ($rate_limited) {
    echo _T("Sending rate limit reached, remaining messages will be sent on next runs.") . "\n";
}

exit(0);
