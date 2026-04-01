<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

use Galette\Api\Repository\ApiTokenRepository;
use Galette\Controllers\CrudController;
use Galette\Entity\ApiClient;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Galette API clients controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiClientsController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     * Form is inline in the list — no dedicated add page.
     */
    public function add(Request $request, Response $response): Response
    {
        return $response;
    }

    /**
     * Add action
     */
    public function doAdd(Request $request, Response $response): Response
    {
        return $this->store($request, $response, null, 'add');
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param int|string|null $value  Value of the option
     */
    public function list(
        Request $request,
        Response $response,
        ?string $option = null,
        int|string|null $value = null,
    ): Response {
        $select = $this->zdb->select(ApiClient::TABLE);
        $select->order('created_at DESC');
        $results = $this->zdb->execute($select);

        $entries = [];
        foreach ($results as $row) {
            $entries[] = new ApiClient($row);
        }

        $this->view->render(
            $response,
            'pages/api_clients_list.html.twig',
            [
                'page_title' => _T("API clients"),
                'entries'    => $entries,
            ]
        );
        return $response;
    }

    /**
     * List filtering — no filters for API clients
     */
    public function filter(Request $request, Response $response): Response
    {
        return $response;
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page — client_id and secret are immutable; no edit page.
     *
     * @param int $id Record id (unused)
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        return $response;
    }

    /**
     * Edit action — no edit for API clients.
     *
     * @param int $id Record id (unused)
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        return $response;
    }

    /**
     * Store — handles API client creation
     *
     * @param ?int   $id     Unused (always null for API clients)
     * @param string $action Action ('add')
     */
    public function store(
        Request $request,
        Response $response,
        ?int $id = null,
        string $action = 'edit'
    ): Response {
        $post = $request->getParsedBody();

        $clientId   = trim((string)($post['client_id'] ?? ''));
        $clientName = trim((string)($post['client_name'] ?? ''));
        $trusted    = isset($post['is_trusted']);
        $secret     = trim((string)($post['client_secret'] ?? ''));

        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
        }

        $errors = [];
        if ($clientId === '') {
            $errors[] = _T("Client ID is required.");
        }
        if ($clientName === '') {
            $errors[] = _T("Name is required.");
        }

        if ($errors === []) {
            $existing = new ApiClient($clientId);
            if ($existing->isLoaded()) {
                $errors[] = _T("An API client with this ID already exists.");
            }
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->flash->addMessage('error_detected', $error);
            }
            return $this->redirect(
                response: $response,
                redirect_url: $this->routeparser->urlFor('apiClients')
            );
        }

        $client = new ApiClient();
        $client->setClientId($clientId)
               ->setClientName($clientName)
               ->setClientSecret($secret)
               ->setTrusted($trusted)
               ->setCreatedAt(new \Safe\DateTime());
        $client->save();

        $this->flash->addMessage('success_detected', _T("API client has been created successfully."));
        $this->flash->addMessage('api_secret', $secret);

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('apiClients')
        );
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('apiClients');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveApiClient',
            ['id' => $args['id']]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        return str_replace(
            ['%id'],
            [$args['id']],
            _T("Remove API client '%id'")
        );
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        $clientId = (string)$args['id'];
        $client = new ApiClient($clientId);

        if (!$client->isLoaded()) {
            $this->flash->addMessage(
                'error_detected',
                _T("API client not found.")
            );
            return false;
        }

        $repo = new ApiTokenRepository($this->zdb);
        $repo->revokeAllForClient($clientId);
        $client->remove();

        return true;
    }

    // /CRUD - Delete
}
