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

namespace Galette\Controllers\Crud;

use Throwable;
use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\SavedSearch;
use Galette\Filters\AdvancedMembersList;
use Galette\Filters\MembersList;
use Galette\Filters\SavedSearchesList;
use Galette\Repository\SavedSearches;
use Analog\Analog;

/**
 * Galette saved searches controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
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
        $post = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();

        $name = null;
        if (isset($post['search_title'])) {
            $name = $post['search_title'];
            unset($post['search_title']);
        }

        //when using advanced search, no parameters are sent
        if (isset($post['advanced_search'])) {
            $post = [];
            $filters = $this->session->{$this->getFilterName(MembersController::getDefaultFilterName())};
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
            if (!$sco->store()) {
                $this->flash->addMessage(
                    'warning_detected',
                    _T("This search is already saved.")
                );
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

        if ($request->getMethod() === 'GET') {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor(MembersController::getDefaultFilterName()));
        } else {
            //called from ajax, return json
            return $this->withJson($response, ['success' => $check]);
        }
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param integer|string|null $value    Value of the option
     *
     * @return Response
     */
    public function list(Request $request, Response $response, ?string $option = null, int|string|null $value = null): Response
    {
        if (isset($this->session->{$this->getFilterName(static::getDefaultFilterName())})) {
            $filters = $this->session->{$this->getFilterName(static::getDefaultFilterName())};
        } else {
            $filters = new SavedSearchesList();
        }

        switch ($option) {
            case 'page':
                $filters->current_page = (int)$value;
                break;
            case 'order':
                $filters->orderby = $value;
                break;
            default:
                break;
        }

        $searches = new SavedSearches($this->zdb, $this->login, $filters);
        $list = $searches->getList(true);

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $this->session->{$this->getFilterName(static::getDefaultFilterName())} = $filters;

        // display page
        $this->view->render(
            $response,
            'pages/saved_searches_list.html.twig',
            [
                'page_title'        => _T("Saved searches"),
                'searches'          => $list,
                'nb'                => $searches->getCount(),
                'filters'           => $filters,
                'documentation'     => 'usermanual/recherche.html#saved-searches'
            ]
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
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('searches');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveSearch',
            ['id' => $args['id'] ?? null]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args): string
    {
        if (isset($args['id'])) {
            return _T('Remove saved search');
        } else {
            //batch saved search removal
            $filters = $this->session->{$this->getFilterName(static::getDefaultFilterName(), ['suffix' => 'delete'])};
            return sprintf(
                _T('You are about to remove %1$s searches.'),
                (string)count($filters->selected),
            );
        }
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post): bool
    {
        if (isset($this->session->{$this->getFilterName(static::getDefaultFilterName())})) {
            $filters = $this->session->{$this->getFilterName(static::getDefaultFilterName())};
        } else {
            $filters = new SavedSearchesList();
        }
        $searches = new SavedSearches($this->zdb, $this->login, $filters);

        $ids = !is_array($post['id']) ? (array)$post['id'] : $post['id'];

        return $searches->remove($ids, $this->history);
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

            $parameters = $sco->parameters;

            if (isset($parameters['free_search'])) {
                $filters = new AdvancedMembersList();
            } else {
                $filters = new MembersList();
            }

            foreach ($parameters as $key => $value) {
                $filters->$key = $value;
            }
            $this->session->{$this->getFilterName(MembersController::getDefaultFilterName())} = $filters;
        } catch (Throwable $e) {
            Analog::log($e->getMessage(), Analog::ERROR);
            $this->flash->addMessage(
                'error_detected',
                _T("An SQL error has occurred while loading search.")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('members'));
    }

    /**
     * Get default filter name
     *
     * @return string
     */
    public static function getDefaultFilterName(): string
    {
        return 'savedsearch';
    }
}
