<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Galette\Controllers\Attributes\Route;
use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Repository\Titles;
use Galette\Entity\Title;

/**
 * Galette Titles controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class TitlesController extends CrudController
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
    #[Route(
        name: 'storeTitle',
        pattern: '/titles',
        methods: ['POST']
    )]
    public function doAdd(Request $request, Response $response): Response
    {
        return $this->store($request, $response, null);
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * Titles list page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param int|string|null $value  Value of the option
     */
    #[Route(
        name: 'titles',
        pattern: '/titles',
        methods: ['GET']
    )]
    public function list(Request $request, Response $response, ?string $option = null, int|string|null $value = null): Response
    {
        $titles = new Titles($this->zdb);

        // display page
        $this->view->render(
            $response,
            'pages/configuration_titles.html.twig',
            [
                'page_title'        => _T("Titles"),
                'titles_list'       => $titles->getList()
            ]
        );
        return $response;
    }

    /**
     * Titles filtering
     */
    public function filter(Request $request, Response $response): Response
    {
        //no filtering
        return $response;
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param int $id Title id
     */
    #[Route(
        name: 'editTitle',
        pattern: '/titles/edit/{id:\d+}',
        methods: ['GET']
    )]
    public function edit(Request $request, Response $response, int $id): Response
    {
        $title = new Title($id);
        $mode = $this->isAjax($request) ? 'ajax' : '';

        // display page
        $this->view->render(
            $response,
            'pages/configuration_title_form.html.twig',
            [
                'page_title'    => _T("Edit title"),
                'title'         => $title,
                'mode'         => $mode
            ]
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param int $id Title id
     */
    #[Route(
        name: 'doEditTitle',
        pattern: '/titles/edit/{id:\d+}',
        methods: ['POST']
    )]
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        return $this->store($request, $response, $id);
    }

    /**
     * Store
     *
     * @param ?int $id Title id
     */
    public function store(Request $request, Response $response, ?int $id = null): Response
    {
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

        $title = new Title($id);
        $title->short = $post['short_label'];
        $title->long = $post['long_label'];
        if ((isset($post['short_label']) && $post['short_label'] != '') && (isset($post['long_label']) && $post['long_label'] != '')) {
            $res = $title->store($this->zdb);
        } else {
            $res = false;
            $error_detected[] = _T("Missing required title's short or long form!");
        }
        $redirect_uri = $this->redirectUri($this->getArgs($request));

        if (!$res) {
            if ($id === null) {
                $error_detected[] = sprintf(
                    _T('Title \'%1$s\' has not been added!'),
                    $title->short,
                );
            } else {
                $error_detected[] = sprintf(
                    _T('Title \'%1$s\' has not been modified!'),
                    $title->short,
                );

                $redirect_uri = $this->routeparser->urlFor('editTitle', ['id' => (string)$id]);
            }
        } elseif ($id === null) {
            $msg = sprintf(
                _T('Title \'%1$s\' has been successfully added.'),
                $title->short
            );
        } else {
            $msg = sprintf(
                _T('Title \'%1$s\' has been successfully modified.'),
                $title->short
            );
        }

        if (count($error_detected) === 0) {
            $success_detected[] = $msg;
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
        return $this->routeparser->urlFor('titles');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveTitle',
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
        $title = new Title((int)$args['id']);
        return sprintf(
            _T('Remove title %1$s'),
            $title->short
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
        $title = new Title((int)$args['id']);
        return $title->remove($this->zdb);
    }

    // /CRUD - Delete
}
