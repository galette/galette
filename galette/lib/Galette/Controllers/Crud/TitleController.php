<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette Title controller
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
 * @category  Controllers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-06
 */

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;

use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Repository\Titles;
use Galette\Entity\Title;
use Analog\Analog;

/**
 * Galette Title controller
 *
 * @category  Controllers
 * @name      TitleController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-08
 */

class TitleController extends CrudController
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
    public function add(Request $request, Response $response, array $args = []) :Response
    {
        //no new page, just to satisfy inheritance
    }

    /**
     * Add ation
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, array $args = []) :Response
    {
        $args['id'] = null;
        return $this->store($request, $response, $args);
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * Titles list page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function list(Request $request, Response $response, array $args = []) :Response
    {
        $args = $this->getArgs($request);
        $titles = Titles::getList($this->zdb);

        // display page
        $this->view->render(
            $response,
            'gestion_titres.tpl',
            [
                'page_title'        => _T("Titles management"),
                'titles_list'       => $titles,
                'require_dialog'    => true
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
    public function filter(Request $request, Response $response) :Response
    {
        //no filtering
    }

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
    public function edit(Request $request, Response $response, array $args = []) :Response
    {
        $args = $this->getArgs($request);
        $id = (int)$args['id'];
        $title = new Title((int)$id);

        // display page
        $this->view->render(
            $response,
            'edit_title.tpl',
            [
                'page_title'    => _T("Edit title"),
                'title'         => $title
            ]
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, array $args = []) :Response
    {
        return $this->store($request, $response, $args);
    }

    /**
     * Store
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function store(Request $request, Response $response, array $args = []) :Response
    {
        $args = $this->getArgs($request);
        $id = $args['id'] ?? null;
        $post = $request->getParsedBody();

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri());
        }

        $title = new Title((int)$id);
        $title->short = $post['short_label'];
        $title->long = $post['long_label'];
        $res = $title->store($this->zdb);

        if (!$res) {
            if ($id === null) {
                $this->flash->addMessage(
                    'error_detected',
                    preg_replace(
                        '(%s)',
                        $title->short,
                        _T("Title '%s' has not been added!")
                    )
                );
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    preg_replace(
                        '(%s)',
                        $title->short,
                        _T("Title '%s' has not been modified!")
                    )
                );

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('editTitle', ['id' => $id]));
            }
        } else {
            if ($id === null) {
                $this->flash->addMessage(
                    'success_detected',
                    preg_replace(
                        '(%s)',
                        $title->short,
                        _T("Title '%s' has been successfully added.")
                    )
                );
            } else {
                $this->flash->addMessage(
                    'success_detected',
                    preg_replace(
                        '(%s)',
                        $title->short,
                        _T("Title '%s' has been successfully modified.")
                    )
                );
            }
        }
        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->redirectUri());








        $ptype = new PaymentType($this->zdb, $id);
        $ptype->name = $post['name'];
        $res = $ptype->store();

        if (!$res) {
            if ($id === null) {
                $this->flash->addMessage(
                    'error_detected',
                    preg_replace(
                        '(%s)',
                        $ptype->name,
                        _T("Payment type '%s' has not been added!")
                    )
                );
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    preg_replace(
                        '(%s)',
                        $ptype->name,
                        _T("Payment type '%s' has not been modified!")
                    )
                );

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('editPaymentType', ['id' => $id]));
            }
        } else {
            if ($id === null) {
                $this->flash->addMessage(
                    'success_detected',
                    preg_replace(
                        '(%s)',
                        $ptype->name,
                        _T("Payment type '%s' has been successfully added.")
                    )
                );
            } else {
                $this->flash->addMessage(
                    'success_detected',
                    preg_replace(
                        '(%s)',
                        $ptype->name,
                        _T("Payment type '%s' has been successfully modified.")
                    )
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->redirectUri());
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
    public function redirectUri(array $args = [])
    {
        return $this->router->pathFor('titles');
    }

    /**
     * Get form URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args = [])
    {
        return $this->router->pathFor(
            'doRemoveTitle',
            ['id' => $args['id']]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args = [])
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
     * @param array $args Route arguments
     * @param array $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post)
    {
        $title = new Title((int)$args['id']);
        return $title->remove($this->zdb);
    }

    // /CRUD - Delete
}
