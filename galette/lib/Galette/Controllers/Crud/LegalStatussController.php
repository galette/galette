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

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Repository\LegalStatuss;
use Galette\Entity\LegalStatus;
use Analog\Analog;

/**
 * Galette Legal status controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class LegalStatussController extends CrudController
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
        return $this->store($request, $response, null);
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * Titles list page
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param integer|string|null $value    Value of the option
     *
     * @return Response
     */
    public function list(Request $request, Response $response, string $option = null, int|string $value = null): Response
    {
        $legalStatuss = new LegalStatuss();//$this->zdb);

        // display page
        $this->view->render(
            $response,
            'pages/configuration_titles.html.twig',
            [
                'page_title'        => _T("Legal status management"),
                'titles_list'       => $legalStatuss->getList()
            ]
        );
        return $response;
    }

    /**
     * Titles filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
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
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Title id
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        $title = new Title($id);
        $mode = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ? 'ajax' : '';

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
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Title id
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
     * @param ?integer $id       Title id
     *
     * @return Response
     */
    public function store(Request $request, Response $response, int $id = null): Response
    {
        $post = $request->getParsedBody();

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $error_detected = [];
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
                $error_detected[] = preg_replace(
                    '(%s)',
                    $title->short !== null ? $title->short : '',
                    _T("Title '%s' has not been added!")
                );
            } else {
                $error_detected[] = preg_replace(
                    '(%s)',
                    $title->short !== null ? $title->short : '',
                    _T("Title '%s' has not been modified!")
                );

                $redirect_uri = $this->routeparser->urlFor('editTitle', ['id' => (string)$id]);
            }
        } else {
            if ($id === null) {
                $error_detected[] = preg_replace(
                    '(%s)',
                    $title->short,
                    _T("Title '%s' has been successfully added.")
                );
            } else {
                $msg = preg_replace(
                    '(%s)',
                    $title->short,
                    _T("Title '%s' has been successfully modified.")
                );
            }
        }

        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        } else {
            $this->flash->addMessage(
                'success_detected',
                $msg
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
        return $this->routeparser->urlFor('legalstatus');
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
            'doRemoveTitle',
            ['id' => $args['id']]
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
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post): bool
    {
        $title = new Title((int)$args['id']);
        return $title->remove($this->zdb);
    }

    // /CRUD - Delete
}
