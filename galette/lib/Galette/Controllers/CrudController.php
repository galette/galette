<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Throwable;
use Galette\Controllers\Attributes\Route;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Analog\Analog;

/**
 * Galette CRUD controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

abstract class CrudController extends AbstractController
{
    // CRUD - Create

    /**
     * Add page
     */
    abstract public function add(Request $request, Response $response): Response;

    /**
     * Add action
     */
    abstract public function doAdd(Request $request, Response $response): Response;

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param int|string|null $value  Value of the option
     */
    abstract public function list(Request $request, Response $response, ?string $option = null, int|string|null $value = null): Response;

    /**
     * List filtering
     */
    abstract public function filter(Request $request, Response $response): Response;

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param int $id Record id
     */
    abstract public function edit(Request $request, Response $response, int $id): Response;

    /**
     * Edit action
     *
     * @param int $id Record id
     */
    abstract public function doEdit(Request $request, Response $response, int $id): Response;

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Removal confirmation — handled by every concrete CRUD subclass via inheritance.
     */
    #[Route(
        name: 'removeContribution',
        pattern: '/{type:contributions|transactions}/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeContributions',
        pattern: '/{type:contributions|transactions}/batch/remove',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeScheduledPayment',
        pattern: '/scheduled-payment/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeScheduledPayments',
        pattern: '/scheduled-payment/batch/remove',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeGroup',
        pattern: '/group/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeMailing',
        pattern: '/mailings/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeTitle',
        pattern: '/titles/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeContributionType',
        pattern: '/contributions-types/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeStatus',
        pattern: '/status/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeDynamicField',
        pattern: '/fields/dynamic/remove/{form_name:adh|contrib|trans|prefs}/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removePaymentType',
        pattern: '/payment-type/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeDocument',
        pattern: '/document/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeMember',
        pattern: '/member/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeMembers',
        pattern: '/members/remove',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeSearch',
        pattern: '/search/remove/{id:\d+}',
        methods: ['GET']
    )]
    #[Route(
        name: 'removeSearches',
        pattern: '/searches/remove',
        methods: ['GET']
    )]
    public function confirmDelete(Request $request, Response $response): Response
    {
        // display page
        $this->view->render(
            $response,
            'modals/confirm_removal.html.twig',
            $this->getconfirmDeleteParams($request)
        );
        return $response;
    }

    /**
     * Removal confirmation parameters, can be override
     *
     * @return array<string,mixed>
     */
    protected function getconfirmDeleteParams(Request $request): array
    {
        $args = $this->getArgs($request);
        $post = $request->getParsedBody();
        $data = [
            'id'            => $this->getIdsToRemove($args, $post),
            'redirect_uri'  => $this->redirectUri($args)
        ];

        return [
            'mode'          => ($this->isAjax($request)) ? 'ajax' : '',
            'page_title'    => $this->confirmRemoveTitle($args),
            'form_url'      => $this->formUri($args),
            'cancel_uri'    => $this->cancelUri($args),
            'data'          => $data
        ];
    }

    /**
     * Get ID to remove
     *
     * In simple cases, we get the ID in the route arguments; but for
     * batchs, it should be found elsewhere.
     * In post values, we look for id key, as well as all entries_sel keys
     *
     * @param array<string,mixed>  $args Request arguments
     * @param ?array<string,mixed> $post POST values
     *
     * @return null|int|int[]
     */
    protected function getIdsToRemove(array &$args, ?array $post): int|array|null
    {
        /** @var  null|array<int>|string $ids */
        $ids = null;
        if (isset($post['id'])) {
            $ids = $post['id'];
        } elseif (isset($args['id'])) {
            $ids = $args['id'];
        }

        if ($ids === null) {
            $filter_name = null;
            $filter_args = ['suffix' => 'delete'];
            if (isset($args['type'])) {
                $filter_args['type'] = $args['type'];
                $filter_name = $this->getFilterName($args['type'], $filter_args);
            } elseif (method_exists($this, 'getDefaultFilterName')) {
                $filter_name = $this->getFilterName($this->getDefaultFilterName(), $filter_args);
            }
            if ($filter_name !== null) {
                $filters = $this->session->$filter_name;
                $ids = $filters->selected;
            }
        }

        //type
        if (is_array($ids)) {
            $ids = array_map(intval(...), $ids);
        } elseif (is_string($ids)) {
            $ids = (int)$ids;
        }

        //add to $args if needed
        if (is_array($ids)) {
            $args['ids'] = $ids;
        } elseif (!isset($args['id']) && $ids) {
            $args['id'] = $ids;
        }

        return $ids;
    }

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    abstract public function redirectUri(array $args): string;

    /**
     * Get cancel URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function cancelUri(array $args): string
    {
        return $this->redirectUri($args);
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    abstract public function formUri(array $args): string;

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    abstract public function confirmRemoveTitle(array $args): string;

    /**
     * Removal — handled by every concrete CRUD subclass via inheritance.
     */
    #[Route(
        name: 'doRemoveContribution',
        pattern: '/{type:contributions|transactions}/remove[/{id}]',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveScheduledPayment',
        pattern: '/scheduled-payment/remove[/{id}]',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveGroup',
        pattern: '/group/remove/{id:\d+}',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveMailing',
        pattern: '/mailings/remove/{id:\d+}',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveTitle',
        pattern: '/titles/remove/{id:\d+}',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveContributionType',
        pattern: '/contributions-types/remove/{id:\d+}',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveStatus',
        pattern: '/status/remove/{id:\d+}',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveDynamicField',
        pattern: '/fields/dynamic/remove/{form_name:adh|contrib|trans|prefs}/{id:\d+}',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemovePaymentType',
        pattern: '/payment-type/remove/{id:\d+}',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveDocument',
        pattern: '/document/remove/{id:\d+}',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveMember',
        pattern: '/member/remove[/{id:\d+}]',
        methods: ['POST']
    )]
    #[Route(
        name: 'doRemoveSearch',
        pattern: '/search/remove[/{id:\d+}]',
        methods: ['POST']
    )]
    public function delete(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $args = $this->getArgs($request);
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = $post['redirect_uri'] ?? $this->redirectUri($args);

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            try {
                $this->getIdsToRemove($args, $post);
                $res = $this->doDelete($args, $post);
                if ($res === true) {
                    $this->flash->addMessage(
                        'success_detected',
                        _T('Successfully deleted!')
                    );
                    $success = true;
                }
            } catch (Throwable $e) {
                Analog::log(
                    'An error occurred on delete | ' . $e->getMessage(),
                    Analog::ERROR
                );

                $this->flash->addMessage(
                    'error_detected',
                    _T('An error occurred trying to delete :(')
                );
            }
        }

        if (!$ajax) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $uri);
        } else {
            return $this->withJson($response, ['success'   => $success]);
        }
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    abstract protected function doDelete(array $args, array $post): bool;
    // /CRUD - Delete
}
