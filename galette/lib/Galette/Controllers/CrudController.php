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

declare(strict_types=1);

namespace Galette\Controllers;

use Throwable;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Analog\Analog;

/**
 * Galette CRUD controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

abstract class CrudController extends AbstractController
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
    abstract public function add(Request $request, Response $response): Response;

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    abstract public function doAdd(Request $request, Response $response): Response;

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
    abstract public function list(Request $request, Response $response, string $option = null, int|string $value = null): Response;

    /**
     * List filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    abstract public function filter(Request $request, Response $response): Response;

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
    abstract public function edit(Request $request, Response $response, int $id): Response;

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Record id
     *
     * @return Response
     */
    abstract public function doEdit(Request $request, Response $response, int $id): Response;

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Removal confirmation
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function confirmDelete(Request $request, Response $response): Response
    {
        // display page
        $this->view->render(
            $response,
            'modals/confirm_removal.html.twig',
            $this->getconfirmDeleteParams($request)
        );
        return $response;
    }

    /**
     * Removal confirmation parameters, can be override
     *
     * @param Request $request PSR Request
     *
     * @return array<string,mixed>
     */
    protected function getconfirmDeleteParams(Request $request): array
    {
        $args = $this->getArgs($request);
        $post = $request->getParsedBody();
        $data = [
            'id'            => $this->getIdsToRemove($args, $post),
            'redirect_uri'  => $this->redirectUri($args)
        ];

        return [
            'mode'          => ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : '',
            'page_title'    => $this->confirmRemoveTitle($args),
            'form_url'      => $this->formUri($args),
            'cancel_uri'    => $this->cancelUri($args),
            'data'          => $data
        ];
    }

    /**
     * Get ID to remove
     *
     * In simple cases, we get the ID in the route arguments; but for
     * batchs, it should be found elsewhere.
     * In post values, we look for id key, as well as all entries_sel keys
     *
     * @param array<string,mixed>  $args Request arguments
     * @param ?array<string,mixed> $post POST values
     *
     * @return null|integer|integer[]
     */
    protected function getIdsToRemove(array &$args, ?array $post): int|array|null
    {
        /** @var  null|array<int>|string $ids */
        $ids = null;
        if (isset($post['id'])) {
            $ids = $post['id'];
        } elseif (isset($args['id'])) {
            $ids = $args['id'];
        }

        if ($ids === null) {
            $filter_name = null;
            if (isset($args['type'])) {
                $filter_name = $this->getFilterName($args['type'], $args);
            } elseif (method_exists($this, 'getDefaultFilterName')) {
                $filter_name = $this->getFilterName($this->getDefaultFilterName(), $args);
            }
            if ($filter_name === null) {
                $filters = $this->session->$filter_name;
                $ids = $filters->selected;
            }
        }

        //type
        if (is_array($ids)) {
            $ids = array_map('intval', $ids);
        } elseif (is_string($ids)) {
            $ids = (int)$ids;
        }

        //add to $args if needed
        //@phpstan-ignore-next-line
        if (is_array($ids)) {
            $args['ids'] = $ids;
        } elseif (!isset($args['id']) && $ids) {
            $args['id'] = $ids;
        }

        return $ids;
    }

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    abstract public function redirectUri(array $args): string;

    /**
     * Get cancel URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function cancelUri(array $args): string
    {
        return $this->redirectUri($args);
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    abstract public function formUri(array $args): string;

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    abstract public function confirmRemoveTitle(array $args): string;

    /**
     * Removal
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function delete(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $args = $this->getArgs($request);
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = $post['redirect_uri'] ?? $this->redirectUri($args);

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            try {
                $this->getIdsToRemove($args, $post);
                $res = $this->doDelete($args, $post);
                if ($res === true) {
                    $this->flash->addMessage(
                        'success_detected',
                        _T('Successfully deleted!')
                    );
                    $success = true;
                }
            } catch (Throwable $e) {
                Analog::log(
                    'An error occurred on delete | ' . $e->getMessage(),
                    Analog::ERROR
                );

                $this->flash->addMessage(
                    'error_detected',
                    _T('An error occurred trying to delete :(')
                );

                $success = false;
            }
        }

        if (!$ajax) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $uri);
        } else {
            return $this->withJson($response, ['success'   => $success]);
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
    abstract protected function doDelete(array $args, array $post): bool;
    // /CRUD - Delete
}
