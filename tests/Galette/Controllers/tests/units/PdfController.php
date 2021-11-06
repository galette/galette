<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF model tests
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @category  Entity
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2020-11-21
 */

namespace Galette\Controllers\test\units;

use atoum;

/**
 * PDF controller tests
 *
 * @category  Controllers
 * @name      PdfController
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-12-06
 */
class PdfController extends atoum
{
    private $zdb;
    private $preferences;
    private $login;
    private $remove = [];
    private $i18n;
    private $container;
    private $history;

    private $adh;
    private $contrib;
    private $members_fields;

    private $mocked_router;
    private $session;
    private $flash_data = [];
    private $flash;

    /**
     * Set up tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->preferences = new \Galette\Core\Preferences($this->zdb);
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n);

        $models = new \Galette\Repository\PdfModels($this->zdb, $this->preferences, $this->login);
        $res = $models->installInit(false);
        $this->boolean($res)->isTrue();

        $this->mocked_router = new \mock\Slim\Router();
        $this->calling($this->mocked_router)->pathFor = function ($name, $params) {
            return $name;
        };

        $this->session = new \RKA\Session();
        $this->history = new \Galette\Core\History($this->zdb, $this->login, $this->preferences);
        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        $_SERVER['HTTP_HOST'] = '';

        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        $this->members_fields = $members_fields;

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $app = new \Slim\App(['router' => $this->mocked_router, 'flash' => $this->flash]);
        $this->container = $app->getContainer();

        $this->container['view'] = null;
        $this->container['zdb'] = $this->zdb;
        $this->container['login'] = $this->login;
        $this->container['session'] = $this->session;
        $this->container['preferences'] = $this->preferences;
        $this->container['logo'] = null;
        $this->container['print_logo'] = null;
        $this->container['plugins'] = null;
        $this->container['history'] = $this->history;
        $this->container['i18n'] = null;
        $this->container['fields_config'] = null;
        $this->container['lists_config'] = null;
        $this->container['l10n'] = null;
        $this->container['members_fields'] = $this->members_fields;
        $members_form_fields = $this->members_fields;
        foreach ($members_form_fields as $k => $field) {
            if ($field['position'] == -1) {
                unset($members_form_fields[$k]);
            }
        }
        //$this->members_form_fields = $members_form_fields;
        $this->container['members_form_fields'] = $members_form_fields;

        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields_cats.php';
        $this->container['members_fields_cats'] = $members_fields_cats;

        global $container, $zdb, $i18n, $login, $hist;
        $zdb = $this->zdb; //globals '(
        $container = $this->container; //globals '(
        $i18n = $this->i18n;
        $login = $this->login;
        $hist = $this->history;
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        if (TYPE_DB === 'mysql') {
            $this->array($this->zdb->getWarnings())->isIdenticalTo([]);
        }
    }

    /**
     * Test store models
     *
     * @return void
     */
    public function testStoreModels()
    {
        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences);
        $this->string($model->title)->isIdenticalTo('_T("Invoice") {CONTRIBUTION_YEAR}-{CONTRIBUTION_ID}');

        $environment = \Slim\Http\Environment::mock(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/models/pdf'
            ]
        );
        $uri = \Slim\Http\Uri::createFromEnvironment($environment);
        $headers = \Slim\Http\Headers::createFromEnvironment($environment);
        $cookies = [];
        $serverParams = $environment->all();

        $body = new \Slim\Http\RequestBody();
        $body->write(
            json_encode([
                'store' => true,
                'models_id' => \Galette\Entity\PdfModel::INVOICE_MODEL,
                'model_type' => \Galette\Entity\PdfModel::INVOICE_MODEL,
                'model_title' => 'DaTitle'
            ])
        );

        $request = new \Slim\Http\Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request = $request->withHeader('Content-Type', 'application/json');
        $response = new \Slim\Http\Response();
        $controller = new \Galette\Controllers\PdfController($this->container);

        $test_response = $controller->storeModels($request, $response);
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo([
            'success_detected' => [
                'Model has been successfully stored!'
            ]
        ]);

        $model = new \Galette\Entity\PdfInvoice($this->zdb, $this->preferences);
        $this->string($model->title)->isIdenticalTo('DaTitle');
    }
}
