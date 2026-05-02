<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Controllers\GaletteController;
use Galette\Controllers\PluginsController;
use Galette\Controllers\HistoryController;
use Galette\Controllers\DynamicTranslationsController;
use Galette\Controllers\Crud;
use Galette\Controllers\PdfController;
use Galette\Controllers\CsvController;
use Galette\Controllers\AdminToolsController;
use Galette\Controllers\TextController;
use Galette\DynamicFields\DynamicField;
use Galette\Middleware\Authenticate;

/**
 * @var \Slim\App<\DI\Container> $app
 */

//galette's dashboard
$app->get(
    '/dashboard',
    [GaletteController::class, 'dashboard']
)->setName('dashboard')->add(Authenticate::class);

//preferences page
$app->get(
    '/preferences',
    [GaletteController::class, 'preferences']
)->setName('preferences')->add(Authenticate::class);

//preferences procedure
$app->post(
    '/preferences',
    [GaletteController::class, 'storePreferences']
)->setName('store-preferences')->add(Authenticate::class);

$app->get(
    '/test/email',
    [GaletteController::class, 'testEmail']
)->setName('testEmail')->add(Authenticate::class);

//charts
$app->get(
    '/charts',
    [GaletteController::class, 'charts']
)->setName('charts')->add(Authenticate::class);

//plugins
$app->get(
    '/plugins',
    [PluginsController::class, 'showPlugins']
)->setName('plugins')->add(Authenticate::class);

//plugins (de)activation
$app->get(
    '/plugins/{action:activate|deactivate}/{module_id}',
    [PluginsController::class, 'togglePlugin']
)->setName('pluginsActivation')->add(Authenticate::class);

$app->map(
    ['GET', 'POST'],
    '/plugins/initialize-database/{id}',
    [PluginsController::class, 'initPluginDb']
)->setName('pluginInitDb')->add(Authenticate::class);

//galette logs
$app->get(
    '/history[/{option:page|order}/{value}]',
    [HistoryController::class, 'list']
)->setName('history')->add(Authenticate::class);

$app->post(
    '/history/filter',
    [HistoryController::class, 'historyFilter']
)->setName('history_filter')->add(Authenticate::class);

$app->get(
    '/history/flush',
    [HistoryController::class, 'confirmHistoryFlush']
)->setName('flushHistory')->add(Authenticate::class);

$app->post(
    '/history/flush',
    [HistoryController::class, 'flushHistory']
)->setName('doFlushHistory')->add(Authenticate::class);

//mailings management
$app->get(
    '/mailings[/{option:page|order|reset}/{value}]',
    [Crud\MailingsController::class, 'list']
)->setName('mailings')->add(Authenticate::class);

$app->post(
    '/mailings/filter',
    [Crud\MailingsController::class, 'filter']
)->setName('mailings_filter')->add(Authenticate::class);

$app->get(
    '/mailings/remove' . '/{id:\d+}',
    [Crud\MailingsController::class, 'confirmDelete']
)->setName('removeMailing')->add(Authenticate::class);

$app->post(
    '/mailings/remove/{id:\d+}',
    [Crud\MailingsController::class, 'delete']
)->setName('doRemoveMailing')->add(Authenticate::class);

//galette exports
$app->get(
    '/export',
    [CsvController::class, 'export']
)->setName('export')->add(Authenticate::class);

$app->get(
    '/{type:export|import}/remove/{file}',
    [CsvController::class, 'confirmRemoveFile']
)->setName('removeCsv')->add(Authenticate::class);

$app->post(
    '/{type:export|import}/remove/{file}',
    [CsvController::class, 'removeFile']
)->setName('doRemoveCsv')->add(Authenticate::class);

$app->post(
    '/export',
    [CsvController::class, 'doExport']
)->setName('doExport')->add(Authenticate::class);

$app->get(
    '/{type:export|import}/get/{file}',
    [CsvController::class, 'getFile']
)->setName('getCsv')->add(Authenticate::class);

$app->get(
    '/import',
    [CsvController::class, 'import']
)->setName('import')->add(Authenticate::class);

$app->post(
    '/import',
    [CsvController::class, 'doImports']
)->setName('doImport')->add(Authenticate::class);

$app->post(
    '/import/upload',
    [CsvController::class, 'uploadImportFile']
)->setname('uploadImportFile')->add(Authenticate::class);

$app->get(
    '/import/model',
    [CsvController::class, 'importModel']
)->setName('importModel')->add(Authenticate::class);

$app->get(
    '/import/model/get',
    [CsvController::class, 'getImportModel']
)->setName('getImportModel')->add(Authenticate::class);

$app->post(
    '/import/model/store',
    [CsvController::class, 'storeModel']
)->setName('storeImportModel')->add(Authenticate::class);

$app->get(
    '/models/pdf[/{id:\d+}]',
    [PdfController::class, 'models']
)->setName('pdfModels')->add(Authenticate::class);

$app->post(
    '/models/pdf',
    [PdfController::class, 'storeModels']
)->setName('pdfModels')->add(Authenticate::class);

$app->get(
    '/titles',
    [Crud\TitlesController::class, 'list']
)->setName('titles')->add(Authenticate::class);

$app->post(
    '/titles',
    [Crud\TitlesController::class, 'doAdd']
)->setName('titles')->add(Authenticate::class);

$app->get(
    '/titles/remove/{id:\d+}',
    [Crud\TitlesController::class, 'confirmDelete']
)->setName('removeTitle')->add(Authenticate::class);

$app->post(
    '/titles/remove/{id:\d+}',
    [Crud\TitlesController::class, 'delete']
)->setName('doRemoveTitle')->add(Authenticate::class);

$app->get(
    '/titles/edit/{id:\d+}',
    [Crud\TitlesController::class, 'edit']
)->setname('editTitle')->add(Authenticate::class);

$app->post(
    '/titles/edit/{id:\d+}',
    [Crud\TitlesController::class, 'doEdit']
)->setname('editTitle')->add(Authenticate::class);

$app->get(
    '/texts[/{lang}/{ref}]',
    [TextController::class, 'list']
)->setName('texts')->add(Authenticate::class);

$app->post(
    '/texts/change',
    [TextController::class, 'change']
)->setName('changeText')->add(Authenticate::class);

$app->post(
    '/texts',
    [TextController::class, 'edit']
)->setName('texts')->add(Authenticate::class);

$app->get(
    '/contributions-types',
    [Crud\ContributionsTypesController::class, 'list']
)->setName('contributionsTypes')->add(Authenticate::class);

$app->get(
    '/contributions-types/edit/{id:\d+}',
    [Crud\ContributionsTypesController::class, 'edit']
)->setName('editContributionType')->add(Authenticate::class);

$app->get(
    '/contributions-types/add',
    [Crud\ContributionsTypesController::class, 'add']
)->setName('addContributionType')->add(Authenticate::class);

$app->post(
    '/contributions-types/edit/{id:\d+}',
    [Crud\ContributionsTypesController::class, 'doEdit']
)->setName('doEditContributionType')->add(Authenticate::class);

$app->post(
    '/contributions-types/add',
    [Crud\ContributionsTypesController::class, 'doAdd']
)->setName('doAddContributionType')->add(Authenticate::class);

$app->get(
    '/contributions-types/remove/{id:\d+}',
    [Crud\ContributionsTypesController::class, 'confirmDelete']
)->setName('removeContributionType')->add(Authenticate::class);

$app->post(
    '/contributions-types/remove/{id:\d+}',
    [Crud\ContributionsTypesController::class, 'delete']
)->setName('doRemoveContributionType')->add(Authenticate::class);

$app->get(
    '/status',
    [Crud\StatusController::class, 'list']
)->setName('status')->add(Authenticate::class);

$app->get(
    '/status/edit/{id:\d+}',
    [Crud\StatusController::class, 'edit']
)->setName('editStatus')->add(Authenticate::class);

$app->get(
    '/status/add',
    [Crud\StatusController::class, 'add']
)->setName('addStatus')->add(Authenticate::class);

$app->post(
    '/status/edit/{id:\d+}',
    [Crud\StatusController::class, 'doEdit']
)->setName('doEditStatus')->add(Authenticate::class);

$app->post(
    '/status/add',
    [Crud\StatusController::class, 'doAdd']
)->setName('doAddStatus')->add(Authenticate::class);

$app->get(
    '/status/remove/{id:\d+}',
    [Crud\StatusController::class, 'confirmDelete']
)->setName('removeStatus')->add(Authenticate::class);

$app->post(
    '/status/remove/{id:\d+}',
    [Crud\StatusController::class, 'delete']
)->setName('doRemoveStatus')->add(Authenticate::class);

$app->get(
    '/dynamic-translation/{text_orig_sum}',
    [DynamicTranslationsController::class, 'dynamicTranslation']
)->setName('dynamicTranslation')->add(Authenticate::class);

$app->get(
    '/dynamic-translations[/{text_orig}]',
    [DynamicTranslationsController::class, 'dynamicTranslations']
)->setName('dynamicTranslations')->add(Authenticate::class);

$app->post(
    '/dynamic-translations',
    [DynamicTranslationsController::class, 'doDynamicTranslations']
)->setName('editDynamicTranslation')->add(Authenticate::class);

$app->get(
    '/lists/{table}/configure',
    [GaletteController::class, 'configureListFields']
)->setName('configureListFields')->add(Authenticate::class);

$app->post(
    '/lists/{table}/configure',
    [GaletteController::class, 'storeListFields']
)->setName('storeListFields')->add(Authenticate::class);

$app->get(
    '/fields/core/configure',
    [GaletteController::class, 'configureCoreFields']
)->setName('configureCoreFields')->add(Authenticate::class);

$app->post(
    '/fields/core/configure',
    [GaletteController::class, 'storeCoreFieldsConfig']
)->setName('storeCoreFieldsConfig')->add(Authenticate::class);

$app->get(
    '/fields/dynamic/configure[/{form_name:adh|contrib|trans|prefs}]',
    [Crud\DynamicFieldsController::class, 'list']
)->setName('configureDynamicFields')->add(Authenticate::class);

$app->get(
    '/fields/dynamic/move/{form_name:adh|contrib|trans|prefs}'
        . '/{direction:' . DynamicField::MOVE_UP . '|' . DynamicField::MOVE_DOWN . '}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'move']
)->setName('moveDynamicField')->add(Authenticate::class);

$app->get(
    '/fields/dynamic/remove/{form_name:adh|contrib|trans|prefs}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'confirmDelete']
)->setName('removeDynamicField')->add(Authenticate::class);

$app->post(
    '/fields/dynamic/remove/{form_name:adh|contrib|trans|prefs}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'delete']
)->setName('doRemoveDynamicField')->add(Authenticate::class);

$app->get(
    '/fields/dynamic/add/{form_name:adh|contrib|trans|prefs}',
    [Crud\DynamicFieldsController::class, 'add']
)->setName('addDynamicField')->add(Authenticate::class);

$app->get(
    '/fields/dynamic/edit/{form_name:adh|contrib|trans|prefs}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'edit']
)->setName('editDynamicField')->add(Authenticate::class);

$app->post(
    '/fields/dynamic/add/{form_name:adh|contrib|trans|prefs}',
    [Crud\DynamicFieldsController::class, 'doAdd']
)->setName('doAddDynamicField')->add(Authenticate::class);

$app->post(
    '/fields/dynamic/edit/{form_name:adh|contrib|trans|prefs}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'doEdit']
)->setName('doEditDynamicField')->add(Authenticate::class);

$app->get(
    '/admin-tools',
    [AdminToolsController::class, 'adminTools']
)->setName('adminTools')->add(Authenticate::class);

$app->post(
    '/admin-tools',
    [AdminToolsController::class, 'process']
)->setName('doAdminTools')->add(Authenticate::class);

$app->get(
    '/payment-types',
    [Crud\PaymentTypeController::class, 'list']
)->setName('paymentTypes')->add(Authenticate::class);

$app->post(
    '/payment-types',
    [Crud\PaymentTypeController::class, 'doAdd']
)->setName('paymentTypes')->add(Authenticate::class);

$app->get(
    '/payment-type/remove/{id:\d+}',
    [Crud\PaymentTypeController::class, 'confirmDelete']
)->setName('removePaymentType')->add(Authenticate::class);

$app->post(
    '/payment-type/remove/{id:\d+}',
    [Crud\PaymentTypeController::class, 'delete']
)->setName('doRemovePaymentType')->add(Authenticate::class);

$app->get(
    '/payment-type/edit/{id:\d+}',
    [Crud\PaymentTypeController::class, 'edit']
)->setname('editPaymentType')->add(Authenticate::class);

$app->post(
    '/payment-type/edit/{id:\d+}',
    [Crud\PaymentTypeController::class, 'doEdit']
)->setname('editPaymentType')->add(Authenticate::class);

$app->get(
    '/{form_name:adh|contrib|trans|prefs}/{id:\d+}/file/{fid:\d+}/{pos:\d+}/{name}',
    [Crud\DynamicFieldsController::class, 'getDynamicFile']
)->setName('getDynamicFile')->add(Authenticate::class);

$app->get(
    '/documents[/{option:page|order}/{value}]',
    [Crud\DocumentsController::class, 'list']
)->setName('documentsList')->add(Authenticate::class);

$app->post(
    '/documents/filter',
    [Crud\DocumentsController::class, 'filter']
)->setName('documentsFilter')->add(Authenticate::class);

$app->get(
    '/document/remove/{id:\d+}',
    [Crud\DocumentsController::class, 'confirmDelete']
)->setName('removeDocument')->add(Authenticate::class);

$app->post(
    '/document/remove/{id:\d+}',
    [Crud\DocumentsController::class, 'delete']
)->setName('doRemoveDocument')->add(Authenticate::class);

$app->get(
    '/document/add',
    [Crud\DocumentsController::class, 'add']
)->setName('addDocument')->add(Authenticate::class);

$app->get(
    '/document/edit/{id:\d+}',
    [Crud\DocumentsController::class, 'edit']
)->setName('editDocument')->add(Authenticate::class);

$app->post(
    '/document/add',
    [Crud\DocumentsController::class, 'doAdd']
)->setName('doAddDocument')->add(Authenticate::class);

$app->post(
    '/document/edit/{id:\d+}',
    [Crud\DocumentsController::class, 'doEdit']
)->setName('doEditDocument')->add(Authenticate::class);

$app->get(
    '/document/get/{id:\d+}',
    [Crud\DocumentsController::class, 'getDocument']
)->setName('getDocumentFile');
