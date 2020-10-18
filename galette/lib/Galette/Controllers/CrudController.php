<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette CRUD controller
 *
 * PHP version 5
 *
 * Copyright © 2019 The Galette Team
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
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-08
 */

namespace Galette\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Analog\Analog;

/**
 * Galette CRUD controller
 *
 * @category  Controllers
 * @name      CrudController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-08
 */

abstract class CrudController extends AbstractController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    abstract public function add(Request $request, Response $response, array $args = []): Response;

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    abstract public function doAdd(Request $request, Response $response, array $args = []): Response;

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    abstract public function list(Request $request, Response $response, array $args = []): Response;

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
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    abstract public function edit(Request $request, Response $response, array $args = []): Response;

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    abstract public function doEdit(Request $request, Response $response, array $args = []): Response;

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Removal confirmation
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function confirmDelete(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();
        $data = [
            'id'            => $this->getIdsToRemove($args, $post),
            'redirect_uri'  => $this->redirectUri($args)
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => $this->confirmRemoveTitle($args),
                'form_url'      => $this->formUri($args),
                'cancel_uri'    => $this->cancelUri($args),
                'data'          => $data
            )
        );
        return $response;
    }

    /**
     * Get ID to remove
     *
     * In simple cases, we get the ID in the route arguments; but for
     * batchs, it should be found elsewhere.
     * In post values, we look for id key, as well as all {sthing}_sel keys (like members_sel or contrib_sel)
     *
     * @param array $args Request arguments
     * @param array $post POST values
     *
     * @return null|integer|integer[]
     */
    protected function getIdsToRemove(&$args, $post)
    {
        $ids = null;
        if (isset($post['id'])) {
            $ids = $post['id'];
        } elseif (isset($args['id'])) {
            $ids = $args['id'];
        }

        //look for {sthing}_sel as multiple ids selection (members_sel, contrib_sel, and so on)
        if (is_array($post) && count($post)) {
            $selecteds = preg_grep('/.+_sel$/', array_keys($post));
            if (count($selecteds) == 1 && !isset($args['id'])) {
                $ids = $post[array_shift($selecteds)];
            } elseif (count($selecteds) > 1) {
                //maybe an error to have multiple {type}_sel in same post request.
                Analog::log(
                    'Several {sthing}_sel variables in same post request should be avoid.',
                    ANalog::WARNING
                );
            }
        }

        //type
        if (is_array($ids)) {
            $ids = array_map('intval', $ids);
        } elseif (is_string($ids)) {
            $ids = (int)$ids;
        }

        //add to $args if needed
        if (is_array($ids)) {
            $args['ids'] = $ids;
        } elseif (!isset($args['id'])) {
            $args['id'] = $ids;
        }

        return $ids;
    }

    /**
     * Get redirection URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    abstract public function redirectUri(array $args = []);

    /**
     * Get cancel URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function cancelUri(array $args = [])
    {
        return $this->redirectUri($args);
    }

    /**
     * Get form URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    abstract public function formUri(array $args = []);

    /**
     * Get confirmation removal page title
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    abstract public function confirmRemoveTitle(array $args = []);

    /**
     * Removal
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function delete(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = $post['redirect_uri'] ?? $this->redirectUri();

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            try {
                $ids = $this->getIdsToRemove($args, $post);

                $res = $this->doDelete($args, $post);
                if ($res === true) {
                    $this->flash->addMessage(
                        'success_detected',
                        _T('Successfully deleted!')
                    );
                    $success = true;
                }
            } catch (\Exception $e) {
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
            return $response->withJson(
                [
                    'success'   => $success
                ]
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
    abstract protected function doDelete(array $args, array $post);
    // /CRUD - Delete
}
