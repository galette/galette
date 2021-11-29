<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PDF model tests
 *
 * PHP version 5
 *
 * Copyright © 2020-2021 The Galette Team
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
 * @copyright 2020-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2020-11-21
 */

namespace Galette\Controllers\test\units;

use atoum;
use Galette\GaletteTestCase;

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
class PdfController extends GaletteTestCase
{
    protected $seed = 58144569971203;

    /**
     * Set up tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        parent::beforeTestMethod($method);

        $this->initModels();
        $this->initStatus();
        $this->initContributionsTypes();

        $this->adh = new \Galette\Entity\Adherent($this->zdb);
        $this->adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
    }

    /**
     * Cleanup after tests
     *
     * @return void
     */
    public function tearDown()
    {
        $this->zdb = new \Galette\Core\Db();

        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where(['info_cotis' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        $this->cleanHistory();
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

    /**
     * Test membersCards
     *
     * @return void
     */
    public function testMembersCards()
    {
        $this->getMemberOne();
        $environment = \Slim\Http\Environment::mock(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/members/card/' . $this->adh->id
            ]
        );
        $uri = \Slim\Http\Uri::createFromEnvironment($environment);
        $headers = \Slim\Http\Headers::createFromEnvironment($environment);
        $cookies = [];
        $serverParams = $environment->all();

        $body = new \Slim\Http\RequestBody();
        $request = new \Slim\Http\Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request = $request->withHeader('Content-Type', 'text/html');
        $response = new \Slim\Http\Response();
        $controller = new \Galette\Controllers\PdfController($this->container);

        //test with non-logged-in user
        $test_response = $controller->membersCards($request, $response, $this->adh->id);
        $this->array($test_response->getHeaders())->isIdenticalTo(['Location' => ['me']]);
        $this->integer($test_response->getStatusCode())->isIdenticalTo(302);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo([
            'error_detected' => [
                'You do not have permission for requested URL.'
            ]
        ]);
        $this->flash_data = [];

        //test logged-in as superadmin
        $this->logSuperAdmin();
        $test_response = null;
        $this
            ->output(
                function () use ($controller, $request, $response, &$test_response) {
                    $test_response = $controller->membersCards($request, $response, $this->adh->id);
                }
            )->isNotEmpty();

        $this->integer($test_response->getStatusCode())->isIdenticalTo(200);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->string($test_response->getHeader('Content-type')[0])->isIdenticalTo('application/pdf');
        $this->string($test_response->getHeader('Content-Disposition')[0])->isIdenticalTo('attachment;filename="cards.pdf"');

        //test no selection
        $test_response = null;
        $test_response = $controller->membersCards($request, $response);
        $this->array($test_response->getHeaders())->isIdenticalTo(['Location' => ['members']]);
        $this->integer($test_response->getStatusCode())->isIdenticalTo(301);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo([
            'error_detected' => [
                'No member was selected, please check at least one name.'
            ]
        ]);
        $this->flash_data = [];

        //test again from filters
        $test_response = null;
        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$this->adh->id];
        $this->session->filter_members = $filters;
        $this
            ->output(
                function () use ($controller, $request, $response, &$test_response) {
                    $test_response = $controller->membersCards($request, $response);
                }
            )->isNotEmpty();

        unset($this->session->filter_members);
        $this->integer($test_response->getStatusCode())->isIdenticalTo(200);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->string($test_response->getHeader('Content-type')[0])->isIdenticalTo('application/pdf');
        $this->string($test_response->getHeader('Content-Disposition')[0])->isIdenticalTo('attachment;filename="cards.pdf"');
    }

    /**
     * Test membersLabels
     *
     * @return void
     */
    public function testMembersLabels()
    {
        unset($this->session->filter_members);
        $this->getMemberOne();
        $environment = \Slim\Http\Environment::mock(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/members/labels'
            ]
        );
        $uri = \Slim\Http\Uri::createFromEnvironment($environment);
        $headers = \Slim\Http\Headers::createFromEnvironment($environment);
        $cookies = [];
        $serverParams = $environment->all();

        $body = new \Slim\Http\RequestBody();
        $request = new \Slim\Http\Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request = $request->withHeader('Content-Type', 'text/html');
        $response = new \Slim\Http\Response();
        $controller = new \Galette\Controllers\PdfController($this->container);

        //test with non-logged-in user
        $test_response = $controller->membersLabels($request, $response, $this->adh->id);
        $this->array($test_response->getHeaders())->isIdenticalTo(['Location' => ['members']]);
        $this->integer($test_response->getStatusCode())->isIdenticalTo(301);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo([
            'error_detected' => [
                'No member was selected, please check at least one name.'
            ]
        ]);
        $this->flash_data = [];

        //test again from filters
        $test_response = null;
        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$this->adh->id];
        $this->session->filter_members = $filters;
        $this
            ->output(
                function () use ($controller, $request, $response, &$test_response) {
                    $test_response = $controller->membersLabels($request, $response);
                }
            )->isNotEmpty();

        $this->integer($test_response->getStatusCode())->isIdenticalTo(200);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->string($test_response->getHeader('Content-type')[0])->isIdenticalTo('application/pdf');
        $this->string($test_response->getHeader('Content-Disposition')[0])->isIdenticalTo('attachment;filename="labels_print_filename.pdf"');
        unset($this->session->filter_members);

        //test logged-in as superadmin
        $this->logSuperAdmin();
        //test no selection
        $test_response = null;
        $test_response = $controller->membersCards($request, $response);
        $this->array($test_response->getHeaders())->isIdenticalTo(['Location' => ['members']]);
        $this->integer($test_response->getStatusCode())->isIdenticalTo(301);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo([
            'error_detected' => [
                'No member was selected, please check at least one name.'
            ]
        ]);
        $this->flash_data = [];

        //test again from filters
        $test_response = null;
        $filters = new \Galette\Filters\MembersList();
        $filters->selected = [$this->adh->id];
        $this->session->filter_members = $filters;
        $this
            ->output(
                function () use ($controller, $request, $response, &$test_response) {
                    $test_response = $controller->membersCards($request, $response);
                }
            )->isNotEmpty();

        $this->integer($test_response->getStatusCode())->isIdenticalTo(200);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->string($test_response->getHeader('Content-type')[0])->isIdenticalTo('application/pdf');
        $this->string($test_response->getHeader('Content-Disposition')[0])->isIdenticalTo('attachment;filename="cards.pdf"');
    }

    /**
     * Test adhesionForm
     *
     * @return void
     */
    public function testadhesionForm()
    {
        unset($this->session->filter_members);
        $this->getMemberOne();
        $environment = \Slim\Http\Environment::mock(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/members/labels'
            ]
        );
        $uri = \Slim\Http\Uri::createFromEnvironment($environment);
        $headers = \Slim\Http\Headers::createFromEnvironment($environment);
        $cookies = [];
        $serverParams = $environment->all();

        $body = new \Slim\Http\RequestBody();
        $request = new \Slim\Http\Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request = $request->withHeader('Content-Type', 'text/html');
        $response = new \Slim\Http\Response();
        $controller = new \Galette\Controllers\PdfController($this->container);

        //test with non-logged-in user
        $test_response = $controller->adhesionForm($request, $response, $this->adh->id);
        $this->array($test_response->getHeaders())->isIdenticalTo(['Location' => ['me']]);
        $this->integer($test_response->getStatusCode())->isIdenticalTo(302);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo([
            'error_detected' => [
                'You do not have permission for requested URL.'
            ]
        ]);
        $this->flash_data = [];

        //test logged-in as superadmin
        $this->logSuperAdmin();
        $test_response = null;
        $this
            ->output(
                function () use ($controller, $request, $response, &$test_response) {
                    $test_response = $controller->adhesionForm($request, $response, $this->adh->id);
                }
            )->isNotEmpty();

        $this->integer($test_response->getStatusCode())->isIdenticalTo(200);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->string($test_response->getHeader('Content-type')[0])->isIdenticalTo('application/pdf');
        $this->string($test_response->getHeader('Content-Disposition')[0])->isIdenticalTo('attachment;filename="adherent_form.' . $this->adh->id . '.pdf"');
    }

    /**
     * Test attendanceSheet
     *
     * @return void
     */
    public function testAttendanceSheet()
    {
        $this->getMemberOne();
        $environment = \Slim\Http\Environment::mock(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/attendance-sheet'
            ]
        );
        $uri = \Slim\Http\Uri::createFromEnvironment($environment);
        $headers = \Slim\Http\Headers::createFromEnvironment($environment);
        $cookies = [];
        $serverParams = $environment->all();

        $body = new \Slim\Http\RequestBody();

        $request = new \Slim\Http\Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request = $request->withHeader('Content-Type', 'application/json');
        $response = new \Slim\Http\Response();
        $controller = new \Galette\Controllers\PdfController($this->container);

        //test no selection
        $test_response = null;
        $test_response = $controller->membersCards($request, $response);
        $this->array($test_response->getHeaders())->isIdenticalTo(['Location' => ['members']]);
        $this->integer($test_response->getStatusCode())->isIdenticalTo(301);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo([
            'error_detected' => [
                'No member was selected, please check at least one name.'
            ]
        ]);
        $this->flash_data = [];

        //test with selection
        $body = new \Slim\Http\RequestBody();
        $body->write(
            json_encode([
                'selection' => [$this->adh->id]
            ])
        );

        $request = new \Slim\Http\Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request = $request->withHeader('Content-Type', 'application/json');
        $test_response = null;
        $this
            ->output(
                function () use ($controller, $request, $response, &$test_response) {
                    $test_response = $controller->attendanceSheet($request, $response);
                }
            )->isNotEmpty();

        $this->integer($test_response->getStatusCode())->isIdenticalTo(200);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->string($test_response->getHeader('Content-type')[0])->isIdenticalTo('application/pdf');
        $this->string($test_response->getHeader('Content-Disposition')[0])->isIdenticalTo('attachment;filename="attendance_sheet.pdf"');
    }

    /**
     * Test contribution
     *
     * @return void
     */
    public function testContribution()
    {
        $this->getMemberOne();
        $this->createContribution();
        $environment = \Slim\Http\Environment::mock(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/contribution/print/' . $this->contrib->id
            ]
        );
        $uri = \Slim\Http\Uri::createFromEnvironment($environment);
        $headers = \Slim\Http\Headers::createFromEnvironment($environment);
        $cookies = [];
        $serverParams = $environment->all();

        $body = new \Slim\Http\RequestBody();
        $request = new \Slim\Http\Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request = $request->withHeader('Content-Type', 'application/json');
        $response = new \Slim\Http\Response();
        $controller = new \Galette\Controllers\PdfController($this->container);

        //test not logged
        $test_response = $controller->contribution($request, $response, $this->contrib->id);
        $this->array($test_response->getHeaders())->isIdenticalTo(['Location' => ['contributions']]);
        $this->integer($test_response->getStatusCode())->isIdenticalTo(301);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo([
            'error_detected' => [
                'Unable to load contribution #' . $this->contrib->id . '!'
            ]
        ]);
        $this->flash_data = [];

        //test superadmin
        $this->logSuperAdmin();
        $body = new \Slim\Http\RequestBody();
        $request = new \Slim\Http\Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request = $request->withHeader('Content-Type', 'application/json');
        $test_response = null;
        $this
            ->output(
                function () use ($controller, $request, $response, &$test_response) {
                    $test_response = $controller->contribution($request, $response, $this->contrib->id);
                }
            )->isNotEmpty();

        $this->integer($test_response->getStatusCode())->isIdenticalTo(200);
        $this->boolean($test_response->isServerError())->isFalse();
        $this->string($test_response->getHeader('Content-type')[0])->isIdenticalTo('application/pdf');
        $this->string($test_response->getHeader('Content-Disposition')[0])->isIdenticalTo('attachment;filename="contribution_' . $this->contrib->id . '_invoice.pdf"');
    }
}
