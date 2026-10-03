<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\GaletteController;
use Galette\Controllers\Crud;
use Galette\Controllers\CsvController;
use Galette\Controllers\PdfController;
use Galette\Entity\Contribution;
use Galette\Middleware\Authenticate;

/**
 * @var \Slim\App<\DI\Container> $app
 */

$app->get(
    '/{type:transactions|contributions}/mine',
    [Crud\ContributionsController::class, 'myList']
)->setName('myContributions')->add(Authenticate::class);

$app->get(
    '/{type:transactions|contributions}[/{option:page|order|member}/{value:\d+|all}]',
    [Crud\ContributionsController::class, 'list']
)->setName('contributions')->add(Authenticate::class);

$app->post(
    '/{type:contributions|transactions}/filter',
    [Crud\ContributionsController::class, 'filter']
)->setName('filterContributions')->add(Authenticate::class);

$app->get(
    '/contribution/{type:' . Contribution::TYPE_FEE . '|' . Contribution::TYPE_DONATION . '}/add',
    [Crud\ContributionsController::class, 'add']
)->setName('addContribution')->add(Authenticate::class);

$app->get(
    '/contribution/{type:' . Contribution::TYPE_FEE . '|' . Contribution::TYPE_DONATION . '}/edit/{id:\d+}',
    [Crud\ContributionsController::class, 'edit']
)->setName('editContribution')->add(Authenticate::class);

$app->post(
    '/contribution/{type:' . Contribution::TYPE_FEE . '|' . Contribution::TYPE_DONATION . '}/add',
    [Crud\ContributionsController::class, 'doAdd']
)->setName('doAddContribution')->add(Authenticate::class);

$app->post(
    '/contribution/{type:' . Contribution::TYPE_FEE . '|' . Contribution::TYPE_DONATION . '}/edit/{id:\d+}',
    [Crud\ContributionsController::class, 'doEdit']
)->setName('doEditContribution')->add(Authenticate::class);

//Batch actions on contributions list
$app->post(
    '/{type:contributions|transactions}/batch',
    [Crud\ContributionsController::class, 'handleBatch']
)->setName('batch-contributionslist')->add(Authenticate::class);

//contributions list CSV export
$app->map(
    ['GET', 'POST'],
    '/{type:contributions|transactions}/export/csv',
    [CsvController::class, 'contributionsExport']
)->setName('csv-contributionslist')->add(Authenticate::class);

$app->get(
    '/transaction/add',
    [Crud\TransactionsController::class, 'add']
)->setName('addTransaction')->add(Authenticate::class);

$app->get(
    '/transaction/edit/{id:\d+}',
    [Crud\TransactionsController::class, 'edit']
)->setName('editTransaction')->add(Authenticate::class);

$app->get(
    '/transaction/{id}/attach/{cid}',
    [Crud\TransactionsController::class, 'attach']
)->setName('attach_contribution')->add(Authenticate::class);

$app->get(
    '/transaction/{id}/detach/{cid}',
    [Crud\TransactionsController::class, 'detach']
)->setName('detach_contribution')->add(Authenticate::class);

$app->post(
    '/transaction/add',
    [Crud\TransactionsController::class, 'doAdd']
)->setName('doAddTransaction')->add(Authenticate::class);

$app->post(
    '/transaction/edit/{id:\d+}',
    [Crud\TransactionsController::class, 'doEdit']
)->setName('doEditTransaction')->add(Authenticate::class);

$app->get(
    '/{type:contributions|transactions}/remove' . '/{id:\d+}',
    [Crud\ContributionsController::class, 'confirmDelete']
)->setName('removeContribution')->add(Authenticate::class);

$app->get(
    '/{type:contributions|transactions}/batch/remove',
    [Crud\ContributionsController::class, 'confirmDelete']
)->setName('removeContributions')->add(Authenticate::class);

$app->post(
    '/{type:contributions|transactions}/remove[/{id}]',
    [Crud\ContributionsController::class, 'delete']
)->setName('doRemoveContribution')->add(Authenticate::class);

//Contribution PDF
$app->get(
    '/contribution/print/{id:\d+}',
    [PdfController::class, 'contribution']
)->setName('printContribution')->add(Authenticate::class);

$app->get(
    '/document/download/{hash}',
    [GaletteController::class, 'documentLink']
)->setName('directlink');

$app->post(
    '/document/download/{hash}',
    [PdfController::class, 'directlinkDocument']
)->setName('get-directlink');

$app->get(
    '/contribution/mass-add/choose-type',
    [Crud\ContributionsController::class, 'massAddChooseType']
)->setName('massAddContributionsChooseType')->add(Authenticate::class);

$app->post(
    '/contribution/mass-add',
    [Crud\ContributionsController::class, 'massAddContributions']
)->setName('massAddContributions')->add(Authenticate::class);

$app->post(
    '/contribution/do-mass-add',
    [Crud\ContributionsController::class, 'doMassAddContributions']
)->setName('doMassAddContributions')->add(Authenticate::class);

$app->get(
    '/scheduled-payments/mine',
    [Crud\ScheduledPaymentController::class, 'myList']
)->setName('myScheduledPayments')->add(Authenticate::class);

$app->post(
    '/scheduled-payments/mine/filter',
    [Crud\ScheduledPaymentController::class, 'myFilter']
)->setName('filterMyScheduledPayments')->add(Authenticate::class);

$app->get(
    '/scheduled-payments[/{option:page|order|member}/{value:\d+|all}]',
    [Crud\ScheduledPaymentController::class, 'list']
)->setName('scheduledPayments')->add(Authenticate::class);

$app->post(
    '/scheduled-payments/filter',
    [Crud\ScheduledPaymentController::class, 'filter']
)->setName('filterScheduledPayments')->add(Authenticate::class);

$app->get(
    '/scheduled-payment/{id_cotis:\d+}/add',
    [Crud\ScheduledPaymentController::class, 'add']
)->setName('addScheduledPayment')->add(Authenticate::class);

$app->get(
    '/scheduled-payment/edit/{id:\d+}',
    [Crud\ScheduledPaymentController::class, 'edit']
)->setName('editScheduledPayment')->add(Authenticate::class);

$app->post(
    '/scheduled-payments/{id_cotis:\d+}/add',
    [Crud\ScheduledPaymentController::class, 'doAdd']
)->setName('doAddScheduledPayment')->add(Authenticate::class);

$app->post(
    '/scheduled-payments/edit/{id:\d+}',
    [Crud\ScheduledPaymentController::class, 'doEdit']
)->setName('doEditScheduledPayment')->add(Authenticate::class);

//Batch actions on scheduled payments list
$app->post(
    '/scheduled-payments/batch',
    [Crud\ScheduledPaymentController::class, 'handleBatch']
)->setName('batch-scheduledPaymentslist')->add(Authenticate::class);

//scheduled payments list CSV export
$app->map(
    ['GET', 'POST'],
    '/scheduled-payments/export/csv',
    [CsvController::class, 'scheduledPaymentsExport']
)->setName('csv-scheduledPaymentslist')->add(Authenticate::class);

$app->get(
    '/scheduled-payment/remove' . '/{id:\d+}',
    [Crud\ScheduledPaymentController::class, 'confirmDelete']
)->setName('removeScheduledPayment')->add(Authenticate::class);

$app->get(
    '/scheduled-payment/batch/remove',
    [Crud\ScheduledPaymentController::class, 'confirmDelete']
)->setName('removeScheduledPayments')->add(Authenticate::class);

$app->post(
    '/scheduled-payment/remove[/{id}]',
    [Crud\ScheduledPaymentController::class, 'delete']
)->setName('doRemoveScheduledPayment')->add(Authenticate::class);
