<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Management routes
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Routes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-11
 */

use Galette\Controllers\GaletteController;
use Galette\Controllers\PluginsController;
use Galette\Controllers\HistoryController;
use Galette\Controllers\DynamicTranslationsController;
use Galette\Controllers\Crud;
use Galette\Controllers\PdfController;
use Galette\Controllers\CsvController;
use Galette\Controllers\AdminToolsController;

use Galette\DynamicFields\DynamicField;
use Galette\Repository\Members;
use Galette\IO\News;
use \Analog\Analog;
use Galette\IO\Csv;
use Galette\IO\CsvOut;
use Galette\IO\CsvIn;
use Galette\Entity\Title;
use Galette\Repository\Titles;
use Galette\Entity\Texts;
use Galette\Core\Install;
use Galette\Entity\Status;

//galette's dashboard
$app->get(
    '/dashboard',
    GaletteController::class . ':dashboard'
)->setName('dashboard')->add($authenticate);

//preferences page
$app->get(
    '/preferences',
    GaletteController::class . ':preferences'
)->setName('preferences')->add($authenticate);

//preferences procedure
$app->post(
    '/preferences',
    GaletteController::class . ':storePreferences'
)->setName('store-preferences')->add($authenticate);

$app->get(
    '/test/email',
    GaletteController::class . ':testEmail'
)->setName('testEmail')->add($authenticate);

//charts
$app->get(
    '/charts',
    GaletteController::class . ':charts'
)->setName('charts')->add($authenticate);

//plugins
$app->get(
    '/plugins',
    PluginsController::class . ':showPlugins'
)->setName('plugins')->add($authenticate);

//plugins (de)activation
$app->get(
    '/plugins/{action:activate|deactivate}/{module_id}',
    PluginsController::class . ':togglePlugin'
)->setName('pluginsActivation')->add($authenticate);

$app->map(
    ['GET', 'POST'],
    '/plugins/initialize-database/{id}',
    PluginsController::class . ':initPluginDb'
)->setName('pluginInitDb')->add($authenticate);

//galette logs
$app->get(
    '/logs[/{option:page|order}/{value}]',
    HistoryController::class . ':history'
)->setName('history')->add($authenticate);

$app->post(
    '/logs/filter',
    HistoryController::class . ':historyFilter'
)->setName(
    'history_filter'
)->add($authenticate);

$app->get(
    '/logs/flush',
    HistoryController::class . ':confirmHistoryFlush'
)->setName('flushHistory')->add($authenticate);

$app->post(
    '/logs/flush',
    HistoryController::class . ':flushHistory'
)->setName('doFlushHistory')->add($authenticate);

//mailings management
$app->get(
    '/mailings[/{option:page|order|reset}/{value}]',
    Crud\MailingsController::class . ':list'
)->setName('mailings')->add($authenticate);

$app->post(
    '/mailings/filter',
    Crud\MailingsController::class . ':filter'
)->setName('mailings_filter')->add($authenticate);

$app->get(
    '/mailings/remove' . '/{id:\d+}',
    Crud\MailingsController::class . ':confirmDelete'
)->setName('removeMailing')->add($authenticate);

$app->post(
    '/mailings/remove/{id:\d+}',
    Crud\MailingsController::class . ':delete'
)->setName('doRemoveMailing')->add($authenticate);

//galette exports
$app->get(
    '/export',
    CsvController::class . ':export'
)->setName('export')->add($authenticate);

$app->get(
    '/{type:export|import}/remove/{file}',
    CsvController::class . ':confirmRemoveFile'
)->setName('removeCsv')->add($authenticate);

$app->post(
    '/{type:export|import}/remove/{file}',
    CsvController::class . ':removeFile'
)->setName('doRemoveCsv')->add($authenticate);

$app->post(
    '/export',
    CsvController::class . ':doExport'
)->setName('doExport')->add($authenticate);

$app->get(
    '/{type:export|import}/get/{file}',
    CsvController::class . ':getFile'
)->setName('getCsv')->add($authenticate);

$app->get(
    '/import',
    CsvController::class . ':import'
)->setName('import')->add($authenticate);

$app->post(
    '/import',
    CsvController::class . ':doImports'
)->setName('doImport')->add($authenticate);

$app->post(
    '/import/upload',
    CsvController::class . ':uploadImportFile'
)->setname('uploadImportFile')->add($authenticate);

$app->get(
    '/import/model',
    CsvController::class . ':importModel'
)->setName('importModel')->add($authenticate);

$app->get(
    '/import/model/get',
    CsvController::class . ':getImportModel'
)->setName('getImportModel')->add($authenticate);

$app->post(
    '/import/model/store',
    CsvController::class . ':storeModel'
)->setName('storeImportModel')->add($authenticate);

$app->get(
    '/models/pdf[/{id:\d+}]',
    PdfController::class . ':models'
)->setName('pdfModels')->add($authenticate);

$app->post(
    '/models/pdf',
    PdfController::class . ':storeModels'
)->setName('pdfModels')->add($authenticate);

$app->get(
    '/titles',
    Crud\TitlesController::class . ':list'
)->setName('titles')->add($authenticate);

$app->post(
    '/titles',
    Crud\TitlesController::class . ':doAdd'
)->setName('titles')->add($authenticate);

$app->get(
    '/titles/remove/{id:\d+}',
    Crud\TitlesController::class . ':confirmDelete'
)->setName('removeTitle')->add($authenticate);

$app->post(
    '/titles/remove/{id:\d+}',
    Crud\TitlesController::class . ':delete'
)->setName('doRemoveTitle')->add($authenticate);

$app->get(
    '/titles/edit/{id:\d+}',
    Crud\TitlesController::class . ':edit'
)->setname('editTitle')->add($authenticate);

$app->post(
    '/titles/edit/{id:\d+}',
    Crud\TitlesController::class . ':doEdit'
)->setname('editTitle')->add($authenticate);

$app->get(
    '/texts[/{lang}/{ref}]',
    function ($request, $response, $args) {
        if (!isset($args['lang'])) {
            $args['lang'] = $this->preferences->pref_lang;
        }
        if (!isset($args['ref'])) {
            $args['ref'] = Texts::DEFAULT_REF;
        }

        $texts = new Texts(
            $this->preferences,
            $this->router
        );

        $mtxt = $texts->getTexts($args['ref'], $args['lang']);

        // display page
        $this->view->render(
            $response,
            'gestion_textes.tpl',
            [
                'page_title'        => _T("Automatic emails texts edition"),
                'reflist'           => $texts->getRefs($args['lang']),
                'langlist'          => $this->i18n->getList(),
                'cur_lang'          => $args['lang'],
                'cur_ref'           => $args['ref'],
                'mtxt'              => $mtxt,
            ]
        );
        return $response;
    }
)->setName('texts')->add($authenticate);

$app->post(
    '/texts/change',
    function ($request, $response) {
        $post = $request->getParsedBody();
        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->router->pathFor(
                    'texts',
                    [
                        'lang'  => $post['sel_lang'],
                        'ref'   => $post['sel_ref']
                    ]
                )
            );
    }
)->setName('changeText')->add($authenticate);

$app->post(
    '/texts',
    function ($request, $response) {
        $post = $request->getParsedBody();
        $texts = new Texts($this->preferences, $this->router);

        //set the language
        $cur_lang = $post['cur_lang'];
        //set the text entry
        $cur_ref = $post['cur_ref'];

        $mtxt = $texts->getTexts($cur_ref, $cur_lang, $this->router);
        $res = $texts->setTexts(
            $cur_ref,
            $cur_lang,
            $post['text_subject'],
            $post['text_body']
        );

        if (!$res) {
            $this->flash->addMessage(
                'error_detected',
                preg_replace(
                    '(%s)',
                    $mtxt->tcomment,
                    _T("Email: '%s' has not been modified!")
                )
            );
        } else {
            $this->flash->addMessage(
                'success_detected',
                preg_replace(
                    '(%s)',
                    $mtxt->tcomment,
                    _T("Email: '%s' has been successfully modified.")
                )
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('texts'));
    }
)->setName('texts')->add($authenticate);

$app->get(
    '/{class:contributions-types|status}',
    Crud\EntitledsController::class . ':list'
)->setName('entitleds')->add($authenticate);

$app->get(
    '/{class:contributions-types|status}/{action:edit|add}[/{id:\d+}]',
    Crud\EntitledsController::class . ':edit'
)->setName('editEntitled')->add($authenticate);

$app->post(
    '/{class:contributions-types|status}/{action:edit|add}[/{id:\d+}]',
    Crud\EntitledsController::class . ':doEdit'
)->setName('doEditEntitled')->add($authenticate);

$app->get(
    '/{class:contributions-types|status}/remove/{id:\d+}',
    Crud\EntitledsController::class . ':confirmDelete'
)->setName('removeEntitled')->add($authenticate);

$app->post(
    '/{class:contributions-types|status}/remove/{id:\d+}',
    Crud\EntitledsController::class . ':delete'
)->setName('doRemoveEntitled')->add($authenticate);

$app->get(
    '/dynamic-translations[/{text_orig}]',
    DynamicTranslationsController::class . ':dynamicTranslations'
)->setName('dynamicTranslations')->add($authenticate);

$app->post(
    '/dynamic-translations',
    DynamicTranslationsController::class . ':doDynamicTranslations'
)->setName('editDynamicTranslation')->add($authenticate);

$app->get(
    '/lists/{table}/configure',
    GaletteController::class . ':configureListFields'
)->setName('configureListFields')->add($authenticate);

$app->post(
    '/lists/{table}/configure',
    GaletteController::class . ':storeListFields'
)->setName('storeListFields')->add($authenticate);

$app->get(
    '/fields/core/configure',
    GaletteController::class . ':configureCoreFields'
)->setName('configureCoreFields')->add($authenticate);

$app->post(
    '/fields/core/configure',
    GaletteController::class . ':storeCoreFieldsConfig'
)->setName('storeCoreFieldsConfig')->add($authenticate);

$app->get(
    '/fields/dynamic/configure[/{form:adh|contrib|trans}]',
    Crud\DynamicFieldsController::class . ':list'
)->setName('configureDynamicFields')->add($authenticate);

$app->get(
    '/fields/dynamic/move/{form:adh|contrib|trans}' .
        '/{direction:up|down}/{id:\d+}',
    Crud\DynamicFieldsController::class . ':move'
)->setName('moveDynamicField')->add($authenticate);

$app->get(
    '/fields/dynamic/remove/{form:adh|contrib|trans}/{id:\d+}',
    Crud\DynamicFieldsController::class . ':confirmDelete'
)->setName('removeDynamicField')->add($authenticate);

$app->post(
    '/fields/dynamic/remove/{form:adh|contrib|trans}/{id:\d+}',
    Crud\DynamicFieldsController::class . ':delete'
)->setName('doRemoveDynamicField')->add($authenticate);

$app->get(
    '/fields/dynamic/add/{form:adh|contrib|trans}',
    Crud\DynamicFieldsController::class . ':add'
)->setName('addDynamicField')->add($authenticate);

$app->get(
    '/fields/dynamic/edit/{form:adh|contrib|trans}/{id:\d+}',
    Crud\DynamicFieldsController::class . ':edit'
)->setName('editDynamicField')->add($authenticate);

$app->post(
    '/fields/dynamic/add/{form:adh|contrib|trans}',
    Crud\DynamicFieldsController::class . ':doAdd'
)->setName('doAddDynamicField')->add($authenticate);

$app->post(
    '/fields/dynamic/edit/{form:adh|contrib|trans}/{id:\d+}',
    Crud\DynamicFieldsController::class . ':doEdit'
)->setName('doEditDynamicField')->add($authenticate);

if (GALETTE_MODE != 'DEMO') {
    $app->get(
        '/generate-data',
        GaletteController::class . ':fakeData'
    )->setName('fakeData')->add($authenticate);

    $app->post(
        '/generate-data',
        GaletteController::class . ':doFakeData'
    )->setName('doFakeData')->add($authenticate);
}

$app->get(
    '/admin-tools',
    AdminToolsController::class . ':adminTools'
)->setName('adminTools')->add($authenticate);

$app->post(
    '/admin-tools',
    AdminToolsController::class . ':process'
)->setName('doAdminTools')->add($authenticate);

$app->get(
    '/payment-types',
    Crud\PaymentTypeController::class . ':list'
)->setName('paymentTypes')->add($authenticate);

$app->post(
    '/payment-types',
    Crud\PaymentTypeController::class . ':doAdd'
)->setName('paymentTypes')->add($authenticate);

$app->get(
    '/payment-type/remove/{id:\d+}',
    Crud\PaymentTypeController::class . ':confirmDelete'
)->setName('removePaymentType')->add($authenticate);

$app->post(
    '/payment-type/remove/{id:\d+}',
    Crud\PaymentTypeController::class . ':delete'
)->setName('doRemovePaymentType')->add($authenticate);

$app->get(
    '/payment-type/edit/{id:\d+}',
    Crud\PaymentTypeController::class . ':edit'
)->setname('editPaymentType')->add($authenticate);

$app->post(
    '/payment-type/edit/{id:\d+}',
    Crud\PaymentTypeController::class . ':doEdit'
)->setname('editPaymentType')->add($authenticate);
