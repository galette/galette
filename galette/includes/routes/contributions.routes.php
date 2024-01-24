<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

use Galette\Controllers\GaletteController;
use Galette\Controllers\Crud;
use Galette\Controllers\CsvController;
use Galette\Controllers\PdfController;
use Galette\Entity\Contribution;

$app->get(
    '/{type:transactions|contributions}/mine',
    [Crud\ContributionsController::class, 'myList']
)->setName('myContributions')->add($authenticate);

$app->get(
    '/{type:transactions|contributions}[/{option:page|order|member}/{value:\d+|all}]',
    [Crud\ContributionsController::class, 'list']
)->setName('contributions')->add($authenticate);

$app->post(
    '/{type:contributions|transactions}/filter',
    [Crud\ContributionsController::class, 'filter']
)->setName('payments_filter')->add($authenticate);

$app->get(
    '/contribution/{type:' . Contribution::TYPE_FEE . '|' . Contribution::TYPE_DONATION . '}/add',
    [Crud\ContributionsController::class, 'add']
)->setName('addContribution')->add($authenticate);

$app->get(
    '/contribution/{type:' . Contribution::TYPE_FEE . '|' . Contribution::TYPE_DONATION . '}/edit/{id:\d+}',
    [Crud\ContributionsController::class, 'edit']
)->setName('editContribution')->add($authenticate);

$app->post(
    '/contribution/{type:' . Contribution::TYPE_FEE . '|' . Contribution::TYPE_DONATION . '}/add',
    [Crud\ContributionsController::class, 'doAdd']
)->setName('doAddContribution')->add($authenticate);

$app->post(
    '/contribution/{type:' . Contribution::TYPE_FEE . '|' . Contribution::TYPE_DONATION . '}/edit/{id:\d+}',
    [Crud\ContributionsController::class, 'doEdit']
)->setName('doEditContribution')->add($authenticate);

//Batch actions on contributions list
$app->post(
    '/{type:contributions|transactions}/batch',
    [Crud\ContributionsController::class, 'handleBatch']
)->setName('batch-contributionslist')->add($authenticate);

//contributions list CSV export
$app->map(
    ['GET', 'POST'],
    '/{type:contributions|transactions}/export/csv',
    [CsvController::class, 'contributionsExport']
)->setName('csv-contributionslist')->add($authenticate);

$app->get(
    '/transaction/add',
    [Crud\TransactionsController::class, 'add']
)->setName('addTransaction')->add($authenticate);

$app->get(
    '/transaction/edit/{id:\d+}',
    [Crud\TransactionsController::class, 'edit']
)->setName('editTransaction')->add($authenticate);

$app->get(
    '/transaction/{id}/attach/{cid}',
    [Crud\TransactionsController::class, 'attach']
)->setName('attach_contribution')->add($authenticate);

$app->get(
    '/transaction/{id}/detach/{cid}',
    [Crud\TransactionsController::class, 'detach']
)->setName('detach_contribution')->add($authenticate);

$app->post(
    '/transaction/add',
    [Crud\TransactionsController::class, 'doAdd']
)->setName('doEditTransaction')->add($authenticate);

$app->post(
    '/transaction/edit/{id:\d+}',
    [Crud\TransactionsController::class, 'doEdit']
)->setName('doEditTransaction')->add($authenticate);

$app->get(
    '/{type:contributions|transactions}/remove' . '/{id:\d+}',
    [Crud\ContributionsController::class, 'confirmDelete']
)->setName('removeContribution')->add($authenticate);

$app->get(
    '/{type:contributions|transactions}/batch/remove',
    [Crud\ContributionsController::class, 'confirmDelete']
)->setName('removeContributions')->add($authenticate);

$app->post(
    '/{type:contributions|transactions}/remove[/{id}]',
    [Crud\ContributionsController::class, 'delete']
)->setName('doRemoveContribution')->add($authenticate);

//Contribution PDF
$app->get(
    '/contribution/print/{id:\d+}',
    [PdfController::class, 'contribution']
)->setName('printContribution')->add($authenticate);

$app->get(
    '/document/{hash}',
    [GaletteController::class, 'documentLink']
)->setName('directlink');

$app->post(
    '/document/{hash}',
    [PdfController::class, 'directlinkDocument']
)->setName('get-directlink');

$app->get(
    '/contribution/mass-add/choose-type',
    [Crud\ContributionsController::class, 'massAddChooseType']
)->setName('massAddContributionsChooseType')->add($authenticate);

$app->post(
    '/contribution/mass-add',
    [Crud\ContributionsController::class, 'massAddContributions']
)->setName('massAddContributions')->add($authenticate);

$app->post(
    '/contribution/do-mass-add',
    [Crud\ContributionsController::class, 'doMassAddContributions']
)->setName('doMassAddContributions')->add($authenticate);
