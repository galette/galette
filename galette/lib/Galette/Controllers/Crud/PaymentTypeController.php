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
use Galette\Repository\PaymentTypes;
use Galette\Entity\PaymentType;

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
        name: 'storePaymentType',
        pattern: '/payment-types',
        methods: ['POST']
    )]
    public function doAdd(Request $request, Response $response): Response
    {
        return $this->store($request, $response, null);
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param int|string|null $value  Value of the option
     */
    #[Route(
        name: 'paymentTypes',
        pattern: '/payment-types',
        methods: ['GET']
    )]
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
     * @param int $id Type id
     */
    #[Route(
        name: 'editPaymentType',
        pattern: '/payment-type/edit/{id:\d+}',
        methods: ['GET']
    )]
    public function edit(Request $request, Response $response, int $id): Response
    {
        $ptype = new PaymentType($this->zdb, $id);
        $mode = $this->isAjax($request) ? 'ajax' : '';


        // display page
        $this->view->render(
            $response,
            'pages/configuration_payment_type_form.html.twig',
            [
                'page_title' => sprintf('%1$s - %2$s', _T('Payment type'), $ptype->getName()),
                'ptype' => $ptype,
                'mode' => $mode
            ]
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param int $id Type id
     */
    #[Route(
        name: 'doEditPaymentType',
        pattern: '/payment-type/edit/{id:\d+}',
        methods: ['POST']
    )]
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        return $this->store($request, $response, $id);
    }

    /**
     * Store
     *
     * @param ?int $id Type id
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
        $success_detected = [];
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
                $error_detected[] = sprintf(
                    _T('Payment type \'%1$s\' has not been added!'),
                    $ptype->getName()
                );
            } else {
                $error_detected[] = sprintf(
                    _T('Payment type \'%1$s\' has not been modified!'),
                    $ptype->getName()
                );
                //redirect to payment type edition
                $redirect_uri = $this->routeparser->urlFor('editPaymentType', ['id' => (string)$id]);
            }
        } elseif ($id === null) {
            $msg = sprintf(
                _T('Payment type \'%1$s\' has been successfully added.'),
                $ptype->getName()
            );
        } else {
            $msg = sprintf(
                _T('Payment type \'%1$s\' has been successfully modified.'),
                $ptype->getName()
            );
        }

        $warning_detected = $ptype->getWarnings();

        if (count($error_detected) === 0) {
            $success_detected[] = $msg;
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_uri,
            successes: $success_detected,
            warnings: $warning_detected,
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
        return $this->routeparser->urlFor('paymentTypes');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
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
     */
    protected function doDelete(array $args, array $post): bool
    {
        $ptype = new PaymentType($this->zdb, (int)$args['id']);
        return $ptype->remove();
    }

    // CRUD - Delete
}
