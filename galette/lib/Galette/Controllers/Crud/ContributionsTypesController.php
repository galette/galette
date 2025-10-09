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

use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\ContributionsTypes;

/**
 * Galette contributions types controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ContributionsTypesController extends CrudController
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
        return $this->store($request, $response, null, 'add');
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
    public function list(
        Request $request,
        Response $response,
        ?string $option = null,
        int|string|null $value = null,
    ): Response {
        $ctypes = new ContributionsTypes($this->zdb);
        $params['page_title'] = _T("Contributions types");

        $list = $ctypes->getCompleteList();
        $params['entries'] = $list;

        $params['documentation'] = 'usermanual/contributions.html#contributions-types';

        if (count($ctypes->getErrors()) > 0) {
            foreach ($ctypes->getErrors() as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        // display page
        $this->view->render(
            $response,
            'pages/contributions_types_list.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Contributions types filtering
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
     * @param integer  $id       Contribution type id
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        $ctype = new ContributionsTypes($this->zdb);
        $params['page_title'] = _T("Edit contribution type");

        $entry = $ctype->get($id);
        $params['entry'] = $entry;

        $params['mode'] = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ? 'ajax' : '';

        // display page
        $this->view->render(
            $response,
            'pages/contribution_type_form.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Contribution type id
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        return $this->store($request, $response, $id);
    }

    /**
     * Store
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?integer $id       Contribution type id
     * @param string   $action   Action
     *
     * @return Response
     */
    public function store(
        Request $request,
        Response $response,
        ?int $id = null,
        string $action = 'edit'
    ): Response {
        $post = $request->getParsedBody();

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $error_detected = [];
        $msg = null;
        $ctype = new ContributionsTypes($this->zdb);

        $label = trim($post['libelle_type_cotis']);
        $field = (int)trim($post['cotis_extension'] ?? 0);
        $amount = null;
        if (isset($post['amount']) && $post['amount'] !== '') {
            $amount = (float)$post['amount'];
        }

        if ($label != '') {
            $ret = ($action === 'add' ? $ctype->add($label, $amount, $field) : $ctype->update($id, $label, $amount, $field));
        } else {
            $ret = false;
            $error_detected[] = _T('Missing required contribution type name!');
        }
        $redirect_uri = $this->routeparser->urlFor('contributionsTypes');

        if ($ret !== true) {
            $error_detected[] = $action === 'add'
                ? _T("Contribution type has not been added :(") : _T("Contribution type #%id has not been updated");
            if ($action === 'edit') {
                $redirect_uri = $this->routeparser->urlFor('editContributionType', ['id' => (string)$id]);
            }
        } else {
            $msg = $action === 'add'
                ? _T("Contribution type has been successfully added!") : _T("Contribution type #%id has been successfully updated!");
        }

        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    str_replace(
                        ['%id'],
                        [(string)$id],
                        $error
                    )
                );
            }
        } else {
            $this->flash->addMessage(
                'success_detected',
                str_replace(
                    ['%id'],
                    [(string)$id],
                    $msg
                )
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_uri);
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
        return $this->routeparser->urlFor('contributionsTypes');
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
            'doRemoveContributionType',
            [
                'id'    => $args['id']
            ]
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
        $ctype = new ContributionsTypes($this->zdb);
        $label = $ctype->getLabel((int)$args['id']);

        return str_replace(
            ['%label'],
            [$label],
            _T("Remove contribution type '%label'")
        );
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
        $ctype = new ContributionsTypes($this->zdb);

        $label = $ctype->getLabel((int)$args['id']);
        $ret = false;
        if ($label !== $ctype::ID_NOT_EXITS) {
            $ret = $ctype->delete((int)$args['id']);

            if (!$ret) {
                foreach ($ctype->getErrors() as $error) {
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
