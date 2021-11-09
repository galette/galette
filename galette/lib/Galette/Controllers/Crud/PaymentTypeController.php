<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette payment types controller
 *
 * PHP version 5
 *
 * Copyright © 2019-2021 The Galette Team
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
 * @copyright 2019-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-09
 */

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;
use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Repository\PaymentTypes;
use Galette\Entity\PaymentType;
use Analog\Analog;

/**
 * Galette payment types controller
 *
 * @category  Controllers
 * @name      PaymentTypeController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-09
 */

class PaymentTypeController extends CrudController
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
     * List page
     *
     * @param Request        $request  PSR Request
     * @param Response       $response PSR Response
     * @param string         $option   One of 'page' or 'order'
     * @param string|integer $value    Value of the option
     *
     * @return Response
     */
    public function list(Request $request, Response $response, $option = null, $value = null): Response
    {
        $ptypes = new PaymentTypes(
            $this->zdb,
            $this->preferences,
            $this->login
        );
        $list = $ptypes->getList();

        // display page
        $this->view->render(
            $response,
            'gestion_paymentstypes.tpl',
            [
                'page_title'        => _T("Payment types management"),
                'list'              => $list
            ]
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
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Type id
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        $ptype = new PaymentType($this->zdb, $id);

        // display page
        $this->view->render(
            $response,
            'edit_paymenttype.tpl',
            [
                'page_title'    => _T("Edit payment type"),
                'ptype'         => $ptype
            ]
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Type id
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
     * @param integer  $id       Type id
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

        $ptype = new PaymentType($this->zdb, $id);
        $ptype->name = $post['name'];
        $res = $ptype->store();
        $redirect_uri = $this->redirectUri($this->getArgs($request));

        if (!$res) {
            if ($id === null) {
                $this->flash->addMessage(
                    'error_detected',
                    preg_replace(
                        '(%s)',
                        $ptype->getName(),
                        _T("Payment type '%s' has not been added!")
                    )
                );
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    preg_replace(
                        '(%s)',
                        $ptype->getName(),
                        _T("Payment type '%s' has not been modified!")
                    )
                );
                //redirect to payment type edition
                $redirect_uri = $this->router->pathFor('editPaymentType', ['id' => $id]);
            }
        } else {
            if ($id === null) {
                $this->flash->addMessage(
                    'success_detected',
                    preg_replace(
                        '(%s)',
                        $ptype->getName(),
                        _T("Payment type '%s' has been successfully added.")
                    )
                );
            } else {
                $this->flash->addMessage(
                    'success_detected',
                    preg_replace(
                        '(%s)',
                        $ptype->getName(),
                        _T("Payment type '%s' has been successfully modified.")
                    )
                );
            }
        }

        $warning_detected = $ptype->getWarnings();
        if (count($warning_detected)) {
            foreach ($warning_detected as $warning) {
                $this->flash->addMessage(
                    'warning_detected',
                    $warning
                );
            }
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
     * @param array $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args)
    {
        return $this->router->pathFor('paymentTypes');
    }

    /**
     * Get form URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args)
    {
        return $this->router->pathFor(
            'doRemovePaymentType',
            ['id' => $args['id'] ?? null]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args)
    {
        $ptype = new PaymentType($this->zdb, (int)$args['id']);
        return sprintf(
            _T('Remove payment type %1$s'),
            $ptype->getName()
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
        $ptype = new PaymentType($this->zdb, (int)$args['id']);
        return $ptype->remove();
    }

    // CRUD - Delete
}
