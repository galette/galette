<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\Status;
use Galette\Repository\Members;

/**
 * Galette status controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property int    $id
 * @property string $label
 * @property string $libelle
 * @property string $priority
 */

class StatusController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     */
    public function add(Request $request, Response $response): Response
    {
        //no new page (included on list), just to satisfy inheritance
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
     * @param int $id Status id
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        $status = new Status($this->zdb);
        $params['page_title'] = _T("Edit status");
        $params['non_staff_priority'] = Members::NON_STAFF_MEMBERS;


        $entry = $status->get($id);
        $params['entry'] = $entry;

        $params['mode'] = $this->isAjax($request) ? 'ajax' : '';

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
     * @param int $id Status id
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        return $this->store($request, $response, $id);
    }

    /**
     * Store
     *
     * @param ?int   $id     Status id
     * @param string $action Action
     */
    public function store(
        Request $request,
        Response $response,
        ?int $id = null,
        string $action = 'edit'
    ): Response {
        $post = $request->getParsedBody();

        if (isset($post['cancel'])) {
            return $this->redirect(
                response: $response,
                redirect_url: $this->cancelUri($this->getArgs($request))
            );
        }

        $error_detected = [];
        $success_detected = [];
        $msg = null;
        $status = new Status($this->zdb);

        $label = trim((string)$post['libelle_statut']);
        $field = (int)trim($post['priorite_statut'] ?? 0);

        if ($label != '') {
            $ret = ($action === 'add' ? $status->add($label, $field) : $status->update($id, $label, $field));
        } else {
            $ret = false;
            $error_detected[] = _T('Missing required status name!');
        }
        $redirect_uri = $this->routeparser->urlFor('status');

        if ($ret !== true) {
            $error_detected[] = $action === 'add'
                ? _T("Status has not been added :(") : _T("Status #%id has not been updated");
            if ($action === 'edit') {
                $redirect_uri = $this->routeparser->urlFor('editStatus', ['id' => (string)$id]);
            }
        } else {
            $msg = $action === 'add'
                ? _T("Status has been successfully added!") : _T("Status #%id has been successfully updated!");
        }

        if (count($error_detected) === 0) {
            $success_detected[] = str_replace(
                ['%id'],
                [(string)$id],
                $msg
            );
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_uri,
            successes: $success_detected,
            errors: $error_detected
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
        return $this->routeparser->urlFor('status');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
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
     */
    protected function doDelete(array $args, array $post): bool
    {
        $class = new Status($this->zdb);

        if ($class->delete((int)$args['id']) !== true) {
            foreach ($class->getErrors() as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
            return false;
        }

        return true;
    }

    // CRUD - Delete
}
