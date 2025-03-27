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

//galette's dashboard
$app->get(
    '/dashboard',
    [GaletteController::class, 'dashboard']
)->setName('dashboard')->add($authenticate);

//preferences page
$app->get(
    '/preferences',
    [GaletteController::class, 'preferences']
)->setName('preferences')->add($authenticate);

//preferences procedure
$app->post(
    '/preferences',
    [GaletteController::class, 'storePreferences']
)->setName('store-preferences')->add($authenticate);

$app->get(
    '/test/email',
    [GaletteController::class, 'testEmail']
)->setName('testEmail')->add($authenticate);

//charts
$app->get(
    '/charts',
    [GaletteController::class, 'charts']
)->setName('charts')->add($authenticate);

//plugins
$app->get(
    '/plugins',
    [PluginsController::class, 'showPlugins']
)->setName('plugins')->add($authenticate);

//plugins (de)activation
$app->get(
    '/plugins/{action:activate|deactivate}/{module_id}',
    [PluginsController::class, 'togglePlugin']
)->setName('pluginsActivation')->add($authenticate);

$app->map(
    ['GET', 'POST'],
    '/plugins/initialize-database/{id}',
    [PluginsController::class, 'initPluginDb']
)->setName('pluginInitDb')->add($authenticate);

//galette logs
$app->get(
    '/logs[/{option:page|order}/{value}]',
    function ($request, $response, $args) use ($routeparser) {
        return $response
            ->withStatus(302)
            ->withHeader(
                'Location',
                $routeparser->urlFor('history', $args)
            );
    }
);
$app->get(
    '/history[/{option:page|order}/{value}]',
    [HistoryController::class, 'list']
)->setName('history')->add($authenticate);

$app->post(
    '/history/filter',
    [HistoryController::class, 'historyFilter']
)->setName('history_filter')->add($authenticate);

$app->get(
    '/logs/flush',
    function ($request, $response) use ($routeparser) {
        return $response
            ->withStatus(302)
            ->withHeader(
                'Location',
                $routeparser->urlFor('flushHistory')
            );
    }
);
$app->get(
    '/history/flush',
    [HistoryController::class, 'confirmHistoryFlush']
)->setName('flushHistory')->add($authenticate);

$app->post(
    '/history/flush',
    [HistoryController::class, 'flushHistory']
)->setName('doFlushHistory')->add($authenticate);

//mailings management
$app->get(
    '/mailings[/{option:page|order|reset}/{value}]',
    [Crud\MailingsController::class, 'list']
)->setName('mailings')->add($authenticate);

$app->post(
    '/mailings/filter',
    [Crud\MailingsController::class, 'filter']
)->setName('mailings_filter')->add($authenticate);

$app->get(
    '/mailings/remove' . '/{id:\d+}',
    [Crud\MailingsController::class, 'confirmDelete']
)->setName('removeMailing')->add($authenticate);

$app->post(
    '/mailings/remove/{id:\d+}',
    [Crud\MailingsController::class, 'delete']
)->setName('doRemoveMailing')->add($authenticate);

//galette exports
$app->get(
    '/export',
    [CsvController::class, 'export']
)->setName('export')->add($authenticate);

$app->get(
    '/{type:export|import}/remove/{file}',
    [CsvController::class, 'confirmRemoveFile']
)->setName('removeCsv')->add($authenticate);

$app->post(
    '/{type:export|import}/remove/{file}',
    [CsvController::class, 'removeFile']
)->setName('doRemoveCsv')->add($authenticate);

$app->post(
    '/export',
    [CsvController::class, 'doExport']
)->setName('doExport')->add($authenticate);

$app->get(
    '/{type:export|import}/get/{file}',
    [CsvController::class, 'getFile']
)->setName('getCsv')->add($authenticate);

$app->get(
    '/import',
    [CsvController::class, 'import']
)->setName('import')->add($authenticate);

$app->post(
    '/import',
    [CsvController::class, 'doImports']
)->setName('doImport')->add($authenticate);

$app->post(
    '/import/upload',
    [CsvController::class, 'uploadImportFile']
)->setname('uploadImportFile')->add($authenticate);

$app->get(
    '/import/model',
    [CsvController::class, 'importModel']
)->setName('importModel')->add($authenticate);

$app->get(
    '/import/model/get',
    [CsvController::class, 'getImportModel']
)->setName('getImportModel')->add($authenticate);

$app->post(
    '/import/model/store',
    [CsvController::class, 'storeModel']
)->setName('storeImportModel')->add($authenticate);

$app->get(
    '/models/pdf[/{id:\d+}]',
    [PdfController::class, 'models']
)->setName('pdfModels')->add($authenticate);

$app->post(
    '/models/pdf',
    [PdfController::class, 'storeModels']
)->setName('pdfModels')->add($authenticate);

$app->get(
    '/titles',
    [Crud\TitlesController::class, 'list']
)->setName('titles')->add($authenticate);

$app->post(
    '/titles',
    [Crud\TitlesController::class, 'doAdd']
)->setName('titles')->add($authenticate);

$app->get(
    '/titles/remove/{id:\d+}',
    [Crud\TitlesController::class, 'confirmDelete']
)->setName('removeTitle')->add($authenticate);

$app->post(
    '/titles/remove/{id:\d+}',
    [Crud\TitlesController::class, 'delete']
)->setName('doRemoveTitle')->add($authenticate);

$app->get(
    '/titles/edit/{id:\d+}',
    [Crud\TitlesController::class, 'edit']
)->setname('editTitle')->add($authenticate);

$app->post(
    '/titles/edit/{id:\d+}',
    [Crud\TitlesController::class, 'doEdit']
)->setname('editTitle')->add($authenticate);

$app->get(
    '/texts[/{lang}/{ref}]',
    [TextController::class, 'list']
)->setName('texts')->add($authenticate);

$app->post(
    '/texts/change',
    [TextController::class, 'change']
)->setName('changeText')->add($authenticate);

$app->post(
    '/texts',
    [TextController::class, 'edit']
)->setName('texts')->add($authenticate);

$app->get(
    '/contributions-types',
    [Crud\ContributionsTypesController::class, 'list']
)->setName('contributionsTypes')->add($authenticate);

$app->get(
    '/contributions-types/edit/{id:\d+}',
    [Crud\ContributionsTypesController::class, 'edit']
)->setName('editContributionType')->add($authenticate);

$app->get(
    '/contributions-types/add',
    [Crud\ContributionsTypesController::class, 'add']
)->setName('addContributionType')->add($authenticate);

$app->post(
    '/contributions-types/edit/{id:\d+}',
    [Crud\ContributionsTypesController::class, 'doEdit']
)->setName('doEditContributionType')->add($authenticate);

$app->post(
    '/contributions-types/add',
    [Crud\ContributionsTypesController::class, 'doAdd']
)->setName('doAddContributionType')->add($authenticate);

$app->get(
    '/contributions-types/remove/{id:\d+}',
    [Crud\ContributionsTypesController::class, 'confirmDelete']
)->setName('removeContributionType')->add($authenticate);

$app->post(
    '/contributions-types/remove/{id:\d+}',
    [Crud\ContributionsTypesController::class, 'delete']
)->setName('doRemoveContributionType')->add($authenticate);

$app->get(
    '/status',
    [Crud\StatusController::class, 'list']
)->setName('status')->add($authenticate);

$app->get(
    '/status/edit/{id:\d+}',
    [Crud\StatusController::class, 'edit']
)->setName('editStatus')->add($authenticate);

$app->get(
    '/status/add',
    [Crud\StatusController::class, 'add']
)->setName('addStatus')->add($authenticate);

$app->post(
    '/status/edit/{id:\d+}',
    [Crud\StatusController::class, 'doEdit']
)->setName('doEditStatus')->add($authenticate);

$app->post(
    '/status/add',
    [Crud\StatusController::class, 'doAdd']
)->setName('doAddStatus')->add($authenticate);

$app->get(
    '/status/remove/{id:\d+}',
    [Crud\StatusController::class, 'confirmDelete']
)->setName('removeStatus')->add($authenticate);

$app->post(
    '/status/remove/{id:\d+}',
    [Crud\StatusController::class, 'delete']
)->setName('doRemoveStatus')->add($authenticate);

$app->get(
    '/dynamic-translation/{text_orig_sum}',
    [DynamicTranslationsController::class, 'dynamicTranslation']
)->setName('dynamicTranslation')->add($authenticate);

$app->get(
    '/dynamic-translations[/{text_orig}]',
    [DynamicTranslationsController::class, 'dynamicTranslations']
)->setName('dynamicTranslations')->add($authenticate);

$app->post(
    '/dynamic-translations',
    [DynamicTranslationsController::class, 'doDynamicTranslations']
)->setName('editDynamicTranslation')->add($authenticate);

$app->get(
    '/dynamic-translation/remove/{text_orig}',
    [DynamicTranslationsController::class, 'undoDynamicTranslation']
)->setName('removeDynamicTranslation')->add($authenticate);

$app->get(
    '/lists/{table}/configure',
    [GaletteController::class, 'configureListFields']
)->setName('configureListFields')->add($authenticate);

$app->post(
    '/lists/{table}/configure',
    [GaletteController::class, 'storeListFields']
)->setName('storeListFields')->add($authenticate);

$app->get(
    '/fields/core/configure',
    [GaletteController::class, 'configureCoreFields']
)->setName('configureCoreFields')->add($authenticate);

$app->post(
    '/fields/core/configure',
    [GaletteController::class, 'storeCoreFieldsConfig']
)->setName('storeCoreFieldsConfig')->add($authenticate);

$app->get(
    '/fields/dynamic/configure[/{form_name:adh|contrib|trans}]',
    [Crud\DynamicFieldsController::class, 'list']
)->setName('configureDynamicFields')->add($authenticate);

$app->get(
    '/fields/dynamic/move/{form_name:adh|contrib|trans}' .
        '/{direction:' . DynamicField::MOVE_UP . '|' . DynamicField::MOVE_DOWN . '}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'move']
)->setName('moveDynamicField')->add($authenticate);

$app->get(
    '/fields/dynamic/remove/{form_name:adh|contrib|trans}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'confirmDelete']
)->setName('removeDynamicField')->add($authenticate);

$app->post(
    '/fields/dynamic/remove/{form_name:adh|contrib|trans}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'delete']
)->setName('doRemoveDynamicField')->add($authenticate);

$app->get(
    '/fields/dynamic/add/{form_name:adh|contrib|trans}',
    [Crud\DynamicFieldsController::class, 'add']
)->setName('addDynamicField')->add($authenticate);

$app->get(
    '/fields/dynamic/edit/{form_name:adh|contrib|trans}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'edit']
)->setName('editDynamicField')->add($authenticate);

$app->post(
    '/fields/dynamic/add/{form_name:adh|contrib|trans}',
    [Crud\DynamicFieldsController::class, 'doAdd']
)->setName('doAddDynamicField')->add($authenticate);

$app->post(
    '/fields/dynamic/edit/{form_name:adh|contrib|trans}/{id:\d+}',
    [Crud\DynamicFieldsController::class, 'doEdit']
)->setName('doEditDynamicField')->add($authenticate);

$app->get(
    '/admin-tools',
    [AdminToolsController::class, 'adminTools']
)->setName('adminTools')->add($authenticate);

$app->post(
    '/admin-tools',
    [AdminToolsController::class, 'process']
)->setName('doAdminTools')->add($authenticate);

$app->get(
    '/payment-types',
    [Crud\PaymentTypeController::class, 'list']
)->setName('paymentTypes')->add($authenticate);

$app->post(
    '/payment-types',
    [Crud\PaymentTypeController::class, 'doAdd']
)->setName('paymentTypes')->add($authenticate);

$app->get(
    '/payment-type/remove/{id:\d+}',
    [Crud\PaymentTypeController::class, 'confirmDelete']
)->setName('removePaymentType')->add($authenticate);

$app->post(
    '/payment-type/remove/{id:\d+}',
    [Crud\PaymentTypeController::class, 'delete']
)->setName('doRemovePaymentType')->add($authenticate);

$app->get(
    '/payment-type/edit/{id:\d+}',
    [Crud\PaymentTypeController::class, 'edit']
)->setname('editPaymentType')->add($authenticate);

$app->post(
    '/payment-type/edit/{id:\d+}',
    [Crud\PaymentTypeController::class, 'doEdit']
)->setname('editPaymentType')->add($authenticate);

$app->get(
    '/{form_name:adh|contrib|trans}/{id:\d+}/file/{fid:\d+}/{pos:\d+}/{name}',
    [Crud\DynamicFieldsController::class, 'getDynamicFile']
)->setName('getDynamicFile')->add($authenticate);

$app->get(
    '/documents[/{option:page|order}/{value}]',
    [Crud\DocumentsController::class, 'list']
)->setName('documentsList')->add($authenticate);

$app->post(
    '/documents/filter',
    [Crud\DocumentsController::class, 'filter']
)->setName('documentsFilter')->add($authenticate);

$app->get(
    '/document/remove/{id:\d+}',
    [Crud\DocumentsController::class, 'confirmDelete']
)->setName('removeDocument')->add($authenticate);

$app->post(
    '/document/remove/{id:\d+}',
    [Crud\DocumentsController::class, 'delete']
)->setName('doRemoveDocument')->add($authenticate);

$app->get(
    '/document/add',
    [Crud\DocumentsController::class, 'add']
)->setName('addDocument')->add($authenticate);

$app->get(
    '/document/edit/{id:\d+}',
    [Crud\DocumentsController::class, 'edit']
)->setName('editDocument')->add($authenticate);

$app->post(
    '/document/add',
    [Crud\DocumentsController::class, 'doAdd']
)->setName('doAddDocument')->add($authenticate);

$app->post(
    '/document/edit/{id:\d+}',
    [Crud\DocumentsController::class, 'doEdit']
)->setName('doEditDocument')->add($authenticate);

$app->get(
    '/document/get/{id:\d+}',
    [Crud\DocumentsController::class, 'getDocument']
)->setName('getDocumentFile');
