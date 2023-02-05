<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Entitleds (contributions types and status) controller
 *
 * PHP version 5
 *
 * Copyright © 2020-2023 The Galette Team
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
 * @category  Controllers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-09
 */

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\ContributionsTypes;
use Galette\Entity\Status;
use Galette\Repository\Members;
use Analog\Analog;

/**
 * Galette Entitleds (contributions types and status) controller
 *
 * @category  Controllers
 * @name      EntitledsController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-06-07
 */

class EntitledsController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function add(Request $request, Response $response): Response
    {
        //no new page (included on list), just to satisfy inheritance
        return $response;
    }

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $class    Entitled class
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, string $class = null): Response
    {
        return $this->store($request, $response, $class, null, 'add');
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request        $request  PSR Request
     * @param Response       $response PSR Response
     * @param string         $option   One of 'page' or 'order'
     * @param string|integer $value    Value of the option
     * @param string         $class    Entitled class from url
     *
     * @return Response
     */
    public function list(
        Request $request,
        Response $response,
        $option = null,
        $value = null,
        $class = null
    ): Response {
        $className = null;
        $entitled = null;

        $params = [];
        switch ($class) {
            case 'status':
                $className = 'Status';
                $entitled = new Status($this->zdb);
                $params['page_title'] = _T("User statuses");
                $params['non_staff_priority'] = Members::NON_STAFF_MEMBERS;
                break;
            case 'contributions-types':
                $className = 'ContributionsTypes';
                $entitled = new ContributionsTypes($this->zdb);
                $params['page_title'] = _T("Contribution types");
                break;
        }

        $params['class'] = $className;
        $params['url_class'] = $class;
        $params['fields'] = $entitled::$fields;

        $list = $entitled->getCompleteList();
        $params['entries'] = $list;

        if (count($entitled->errors) > 0) {
            foreach ($entitled->errors as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        // display page
        $this->view->render(
            $response,
            'pages/configuration_entitleds.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Mailings filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function filter(Request $request, Response $response): Response
    {
        //no filters
        return $response;
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Entitled id
     * @param string   $class    Entitled class from url
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id, string $class = null): Response
    {
        $className = null;
        $entitled = null;

        $params = [];
        switch ($class) {
            case 'status':
                $className = 'Status';
                $entitled = new Status($this->zdb);
                $params['page_title'] = _T("Edit status");
                $params['non_staff_priority'] = Members::NON_STAFF_MEMBERS;
                break;
            case 'contributions-types':
                $className = 'ContributionsTypes';
                $entitled = new ContributionsTypes($this->zdb);
                $params['page_title'] = _T("Edit contribution type");
                break;
        }

        $params['class'] = $className;
        $params['url_class'] = $class;
        $params['fields'] = $entitled::$fields;

        $entry = $entitled->get($id);
        $params['entry'] = $entry;

        // display page
        $this->view->render(
            $response,
            'pages/configuration_entitled_form.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Entitled id
     * @param string   $class    Entitled class from url
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id, string $class = null): Response
    {
        return $this->store($request, $response, $class, $id);
    }

    /**
     * Store
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $class    Entitled class from url
     * @param integer  $id       Entitled id
     * @param string   $action   Action
     *
     * @return Response
     */
    public function store(
        Request $request,
        Response $response,
        string $class = null,
        int $id = null,
        string $action = 'edit'
    ): Response {
        $post = $request->getParsedBody();

        switch ($class) {
            case 'status':
                $entitled = new Status($this->zdb);
                break;
            case 'contributions-types':
                $entitled = new ContributionsTypes($this->zdb);
                break;
        }

        $label = trim($post[$entitled::$fields['libelle']]);
        $field = trim($post[$entitled::$fields['third']] ?? 0);

        $ret = ($action === 'add' ? $entitled->add($label, $field) : $entitled->update($id, $label, $field));

        if ($ret !== true) {
            $msg_type = 'error_detected';
            $msg = $action === 'add' ?
                _T("%type has not been added :(") : _T("%type #%id has not been updated");
        } else {
            $msg_type = 'success_detected';
            $msg = $action === 'add' ?
                _T("%type has been successfully added!") : _T("%type #%id has been successfully updated!");
        }

        $this->flash->addMessage(
            $msg_type,
            str_replace(
                ['%type', '%id'],
                [$entitled->getI18nType(), $id],
                $msg
            )
        );

        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor(
                    'entitleds',
                    ['class' => $class]
                )
            );
    }


    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args)
    {
        return $this->routeparser->urlFor('entitleds', ['class' => $args['class']]);
    }

    /**
     * Get form URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args)
    {
        return $this->routeparser->urlFor(
            'doRemoveEntitled',
            [
                'class' => $args['class'],
                'id'    => $args['id']
            ]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args)
    {
        $class = null;
        switch ($args['class']) {
            case 'status':
                $class = new Status($this->zdb);
                break;
            case 'contributions-types':
                $class = new ContributionsTypes($this->zdb);
                break;
        }
        $label = $class->getLabel((int)$args['id']);

        return str_replace(
            ['%type', '%label'],
            [$class->getI18nType(), $label],
            _T("Remove %type '%label'")
        );
    }

    /**
     * Remove object
     *
     * @param array $args Route arguments
     * @param array $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post)
    {
        $class = null;
        switch ($args['class']) {
            case 'status':
                $class = new Status($this->zdb);
                break;
            case 'contributions-types':
                $class = new ContributionsTypes($this->zdb);
                break;
        }

        $label = $class->getLabel((int)$args['id']);
        $ret = false;
        if ($label !== $class::ID_NOT_EXITS) {
            $ret = $class->delete((int)$args['id']);

            if (!$ret) {
                foreach ($class->getErrors() as $error) {
                    $this->flash->addMessage(
                        'error_detected',
                        $error
                    );
                }
            }
        }

        return $ret;
    }

    // CRUD - Delete
}
