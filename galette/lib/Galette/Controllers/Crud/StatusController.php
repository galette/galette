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

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\ContributionsTypes;
use Galette\Entity\Status;
use Galette\Repository\Members;
use Analog\Analog;

/**
 * Galette status controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property integer $id
 * @property string $label
 * @property string $libelle
 * @property string $priority
 */

class StatusController extends CrudController
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

        $status = new Status($this->zdb);
        $params['page_title'] = _T("User statuses");
        $params['non_staff_priority'] = Members::NON_STAFF_MEMBERS;

        $list = $status->getCompleteList();
        $params['entries'] = $list;

        if (count($status->getErrors()) > 0) {
            foreach ($status->getErrors() as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        // display page
        $this->view->render(
            $response,
            'pages/status_list.html.twig',
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
     * @param integer  $id       Status id
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        $status = new Status($this->zdb);
        $params['page_title'] = _T("Edit status");
        $params['non_staff_priority'] = Members::NON_STAFF_MEMBERS;


        $entry = $status->get($id);
        $params['entry'] = $entry;

        $params['mode'] = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ? 'ajax' : '';

        // display page
        $this->view->render(
            $response,
            'pages/status_form.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Status id
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
     * @param ?integer $id       Status id
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
        $status = new Status($this->zdb);

        $label = trim($post['libelle_statut']);
        $field = (int)trim($post['priorite_statut'] ?? 0);

        if ($label != '') {
            $ret = ($action === 'add' ? $status->add($label, $field) : $status->update($id, $label, $field));
        } else {
            $ret = false;
            $error_detected[] = _T('Missing required status name!');
        }
        $redirect_uri = $this->routeparser->urlFor('status');

        if ($ret !== true) {
            $error_detected[] = $action === 'add' ?
                _T("Status has not been added :(") : _T("Status #%id has not been updated");
            if ($action === 'edit') {
                $redirect_uri = $this->routeparser->urlFor('editStatus', ['id' => (string)$id]);
            }
        } else {
            $msg = $action === 'add' ?
                _T("Status has been successfully added!") : _T("Status #%id has been successfully updated!");
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
        return $this->routeparser->urlFor('status');
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
            'doRemoveStatus',
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
        $class = new Status($this->zdb);
        $label = $class->getLabel((int)$args['id']);

        return str_replace(
            ['%label'],
            [$label],
            _T("Remove status '%label'")
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
        $class = new Status($this->zdb);
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
