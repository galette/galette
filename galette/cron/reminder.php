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
use Galette\Core\MailingQueue;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
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

$preferences = $container->get(Preferences::class);
$reminders = new Reminders();
$list_reminders = $reminders->getList($container->get(Db::class), false);

//queue the due reminders, then drain the queue respecting the configured
//throttling (delay, hourly/daily quota shared with mass mailings)
$queue = new MailingQueue($container->get(Db::class), $preferences);
$queue->setReminderContext(
    $container->get(History::class),
    $container->get(Login::class)
);
$queue->enqueueReminders($list_reminders);

$delay = (int)$preferences->pref_mail_batch_delay;
$total_sent = 0;
$total_failed = 0;

do {
    $progress = $queue->processBatch(null, MailingQueue::KIND_REMINDER);
    $total_sent += (int)$progress['batch_sent'];
    $total_failed += (int)$progress['batch_failed'];

    //stop when the queue is empty or the rate limit is reached (the remaining
    //messages will be sent on the next runs)
    if ($progress['done'] === true || $progress['rate_limited'] === true) {
        break;
    }
    if ($delay > 0) {
        sleep($delay);
    }
} while (true);

//called from a cron: successes and warnings have been stored into history and
//logged. Stay completely silent unless something failed, otherwise cron would
//notify the administrator on every (successful) run. A reached rate limit is
//not an error: the remaining messages are sent on the next runs.
if ($total_failed > 0) {
    echo str_replace(
        ['%sent', '%failed'],
        [(string)$total_sent, (string)$total_failed],
        _T("Reminders processed: %sent sent, %failed failed.")
    ) . "\n";
    exit(1);
}

exit(0);
