<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette saved searches controller
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
 * @since     Available since 0.9.4dev - 2020-05-03
 */

namespace Galette\Controllers\Crud;

use Throwable;
use Galette\Controllers\CrudController;
use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Entity\SavedSearch;
use Galette\Filters\AdvancedMembersList;
use Galette\Filters\MembersList;
use Galette\Filters\SavedSearchesList;
use Galette\Repository\SavedSearches;
use Analog\Analog;

/**
 * Galette saved searches controller
 *
 * @category  Controllers
 * @name      SavedSearchesController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-03
 */

class SavedSearchesController extends CrudController
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
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response): Response
    {
        if ($request->isPost()) {
            $post = $request->getParsedBody();
        } else {
            $post = $request->getQueryParams();
        }

        $name = null;
        if (isset($post['search_title'])) {
            $name = $post['search_title'];
            unset($post['search_title']);
        }

        //when using advanced search, no parameters are sent
        if (isset($post['advanced_search'])) {
            $post = [];
            $filters = $this->session->filter_members;
            foreach ($filters->search_fields as $field) {
                $post[$field] = $filters->$field;
            }
        }

        //reformat, add required infos
        $post = [
            'parameters'    => $post,
            'form'          => 'Adherent',
            'name'          => $name
        ];

        $sco = new SavedSearch($this->zdb, $this->login);
        if ($check = $sco->check($post)) {
            if (!$res = $sco->store()) {
                if ($res === false) {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("An SQL error has occurred while storing search.")
                    );
                } else {
                    $this->flash->addMessage(
                        'warning_detected',
                        _T("This search is already saved.")
                    );
                }
            } else {
                $this->flash->addMessage(
                    'success_detected',
                    _T("Search has been saved.")
                );
            }
        } else {
            //report errors
            foreach ($sco->getErrors() as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        if ($request->isGet()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        } else {
            //called from ajax, return json
            return $response->withJson(['success' => $check]);
        }
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
     *
     * @return Response
     */
    public function list(Request $request, Response $response, $option = null, $value = null): Response
    {
        if (isset($this->session->filter_savedsearch)) {
            $filters = $this->session->filter_savedsearch;
        } else {
            $filters = new SavedSearchesList();
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
            }
        }

        $searches = new SavedSearches($this->zdb, $this->login, $filters);
        $list = $searches->getList(true);

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->router, $this->view, false);

        $this->session->filter_savedsearch = $filters;

        // display page
        $this->view->render(
            $response,
            'pages/saved_searches_list.html.twig',
            array(
                'page_title'        => _T("Saved searches"),
                'searches'          => $list,
                'nb'                => $searches->getCount(),
                'filters'           => $filters
            )
        );
        return $response;
    }

    /**
     * Filtering
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
     * @param integer  $id       Record id
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        //no edition
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Record id
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        //no edition
        return $response;
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
        return $this->router->pathFor('searches');
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
        return $this->router->pathFor(
            'doRemoveSearch',
            ['id' => $args['id'] ?? null]
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
        if (isset($args['id'])) {
            return _T('Remove saved search');
        } else {
            //batch saved search removal
            $filters = $this->session->filter_savedsearch;
            return str_replace(
                '%count',
                count($filters->selected),
                _T('You are about to remove %count searches.')
            );
        }
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
        if (isset($this->session->filter_savedsearch)) {
            $filters = $this->session->filter_savedsearch;
        } else {
            $filters = new SavedSearchesList();
        }
        $searches = new SavedSearches($this->zdb, $this->login, $filters);

        if (!is_array($post['id'])) {
            $ids = (array)$post['id'];
        } else {
            $ids = $post['id'];
        }

        $del = $searches->remove($ids, $this->history);
        return $del;
    }

    // CRUD - Delete

    /**
     * Load saved search
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Saved search id
     *
     * @return Response
     */
    public function load(Request $request, Response $response, int $id): Response
    {
        try {
            $sco = new SavedSearch($this->zdb, $this->login, $id);
            $this->flash->addMessage(
                'success_detected',
                _T("Saved search loaded")
            );
        } catch (Throwable $e) {
            $this->flash->addMessage(
                'error_detected',
                _T("An SQL error has occurred while loading search.")
            );
        }
        $parameters = (array)$sco->parameters;

        $filters = null;
        if (isset($parameters['free_search'])) {
            $filters = new AdvancedMembersList();
        } else {
            $filters = new MembersList();
        }

        foreach ($parameters as $key => $value) {
            $filters->$key = $value;
        }
        $this->session->filter_members = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('members'));
    }
}
