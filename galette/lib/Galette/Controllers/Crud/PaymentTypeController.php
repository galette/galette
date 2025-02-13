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
use Galette\Repository\PaymentTypes;
use Galette\Entity\PaymentType;
use Analog\Analog;

/**
 * Galette payment types controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
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
     * List page
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param integer|string|null $value    Value of the option
     *
     * @return Response
     */
    public function list(Request $request, Response $response, ?string $option = null, int|string|null $value = null): Response
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
            'pages/configuration_payment_types.html.twig',
            [
                'page_title'        => _T("Payment types"),
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
        return $response;
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
        $mode = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ? 'ajax' : '';


        // display page
        $this->view->render(
            $response,
            'pages/configuration_payment_type_form.html.twig',
            [
                'page_title'    => _T("Edit payment type"),
                'ptype'         => $ptype,
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
     * @param ?integer $id       Type id
     *
     * @return Response
     */
    public function store(Request $request, Response $response, ?int $id = null): Response
    {
        $post = $request->getParsedBody();

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $error_detected = [];
        $msg = null;

        $ptype = new PaymentType($this->zdb, $id);
        $ptype->name = $post['name'];
        if (isset($post['name']) && $post['name'] != '') {
            $res = $ptype->store();
        } else {
            $res = false;
            $error_detected[] = _T("Missing required payment type's name!");
        }
        $redirect_uri = $this->redirectUri($this->getArgs($request));

        if (!$res) {
            if ($id === null) {
                $error_detected[] = preg_replace(
                    '(%s)',
                    $ptype->getName(),
                    _T("Payment type '%s' has not been added!")
                );
            } else {
                $error_detected[] = preg_replace(
                    '(%s)',
                    $ptype->getName(),
                    _T("Payment type '%s' has not been modified!")
                );
                //redirect to payment type edition
                $redirect_uri = $this->routeparser->urlFor('editPaymentType', ['id' => (string)$id]);
            }
        } else {
            if ($id === null) {
                $msg = preg_replace(
                    '(%s)',
                    $ptype->getName(),
                    _T("Payment type '%s' has been successfully added.")
                );
            } else {
                $msg = preg_replace(
                    '(%s)',
                    $ptype->getName(),
                    _T("Payment type '%s' has been successfully modified.")
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
        return $this->routeparser->urlFor('paymentTypes');
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
            'doRemovePaymentType',
            ['id' => $args['id'] ?? null]
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
        $ptype = new PaymentType($this->zdb, (int)$args['id']);
        return sprintf(
            _T('Remove payment type %1$s'),
            $ptype->getName()
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
        $ptype = new PaymentType($this->zdb, (int)$args['id']);
        return $ptype->remove();
    }

    // CRUD - Delete
}
