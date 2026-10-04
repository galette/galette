<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Analog\Analog;
use DI\Attribute\Inject;
use Galette\Controllers\Attributes\Route;
use Galette\Controllers\CrudController;
use Galette\Core\Galette;
use Galette\Entity\Webhook;
use Galette\Exception\EntityException;
use Galette\Repository\Webhooks;
use Galette\Webhooks\DeliveryQueue;
use Galette\Webhooks\DeliveryResult;
use Galette\Webhooks\Dispatcher;
use Galette\Webhooks\Payload\PayloadBuilder;
use Galette\Webhooks\WebhookEvent;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Webhooks controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class WebhooksController extends CrudController
{
    /** Session key holding values of a form that could not be stored */
    private const string FORM_SESSION_KEY = 'webhook_form';

    #[Inject]
    protected Dispatcher $dispatcher;
    #[Inject]
    protected DeliveryQueue $queue;
    #[Inject]
    protected PayloadBuilder $payload_builder;

    // CRUD - Create

    /**
     * Add page
     */
    #[Route(
        name: 'addWebhook',
        pattern: '/webhooks/add',
        methods: ['GET']
    )]
    public function add(Request $request, Response $response): Response
    {
        return $this->renderForm($response, new Webhook(), _T('New webhook'));
    }

    /**
     * Add action
     */
    #[Route(
        name: 'doAddWebhook',
        pattern: '/webhooks/add',
        methods: ['POST']
    )]
    public function doAdd(Request $request, Response $response): Response
    {
        return $this->store($request, $response, new Webhook());
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * Webhooks list page
     *
     * @param string|null     $option Unused
     * @param int|string|null $value  Unused
     */
    #[Route(
        name: 'webhooks',
        pattern: '/webhooks',
        methods: ['GET']
    )]
    public function list(Request $request, Response $response, ?string $option = null, int|string|null $value = null): Response
    {
        $this->view->render(
            $response,
            'pages/webhooks_list.html.twig',
            [
                'page_title' => _T('Webhooks'),
                'webhooks' => (new Webhooks($this->zdb))->getList(),
                'summaries' => $this->queue->getSummaries(),
            ]
        );
        return $response;
    }

    /**
     * Webhooks filtering
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
     * @param int $id Webhook identifier
     */
    #[Route(
        name: 'editWebhook',
        pattern: '/webhooks/edit/{id:\d+}',
        methods: ['GET']
    )]
    public function edit(Request $request, Response $response, int $id): Response
    {
        $webhook = $this->loadWebhook($id);
        if ($webhook === null) {
            return $this->notFound($response);
        }
        return $this->renderForm(
            $response,
            $webhook,
            sprintf(
                //TRANS: %1$s is the webhook name
                _T('Edit webhook %1$s'),
                $webhook->getName()
            )
        );
    }

    /**
     * Edit action
     *
     * @param int $id Webhook identifier
     */
    #[Route(
        name: 'doEditWebhook',
        pattern: '/webhooks/edit/{id:\d+}',
        methods: ['POST']
    )]
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        $webhook = $this->loadWebhook($id);
        if ($webhook === null) {
            return $this->notFound($response);
        }
        return $this->store($request, $response, $webhook);
    }

    /**
     * Replace secret with a new one
     *
     * @param int $id Webhook identifier
     */
    #[Route(
        name: 'regenerateWebhookSecret',
        pattern: '/webhooks/{id:\d+}/secret',
        methods: ['POST']
    )]
    public function regenerateSecret(Request $request, Response $response, int $id): Response
    {
        $webhook = $this->loadWebhook($id);
        if ($webhook === null) {
            return $this->notFound($response);
        }

        $webhook->regenerateSecret();
        $stored = $webhook->save();
        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('editWebhook', ['id' => (string)$id]),
            successes: $stored
                ? [_T('A new secret has been generated, update it on the receiver side.')]
                : [],
            errors: $stored ? [] : [_T('An error occurred generating a new secret.')]
        );
    }

    /**
     * Deliveries log page
     *
     * @param int $id Webhook identifier
     */
    #[Route(
        name: 'webhookDeliveries',
        pattern: '/webhooks/{id:\d+}/deliveries',
        methods: ['GET']
    )]
    public function deliveries(Request $request, Response $response, int $id): Response
    {
        $webhook = $this->loadWebhook($id);
        if ($webhook === null) {
            return $this->notFound($response);
        }

        $this->view->render(
            $response,
            'pages/webhook_deliveries.html.twig',
            [
                'page_title' => sprintf(
                    //TRANS: %1$s is the webhook name
                    _T('Deliveries of webhook %1$s'),
                    $webhook->getName()
                ),
                'webhook' => $webhook,
                'deliveries' => $this->queue->getForWebhook($id),
                'max_attempts' => Dispatcher::MAX_ATTEMPTS,
            ]
        );
        return $response;
    }

    /**
     * Send a delivery again, right away
     *
     * @param int $id Delivery identifier
     */
    #[Route(
        name: 'retryWebhookDelivery',
        pattern: '/webhooks/deliveries/{id:\d+}/retry',
        methods: ['POST']
    )]
    public function retryDelivery(Request $request, Response $response, int $id): Response
    {
        $webhook_id = $this->queue->getWebhookId($id);
        if ($webhook_id === null) {
            return $this->notFound($response);
        }

        return $this->reportResult(
            $response,
            $this->dispatcher->sendNow($id),
            $this->routeparser->urlFor('webhookDeliveries', ['id' => (string)$webhook_id])
        );
    }

    /**
     * Send a test event, right away
     *
     * @param int $id Webhook identifier
     */
    #[Route(
        name: 'pingWebhook',
        pattern: '/webhooks/{id:\d+}/ping',
        methods: ['POST']
    )]
    public function ping(Request $request, Response $response, int $id): Response
    {
        $webhook = $this->loadWebhook($id);
        if ($webhook === null) {
            return $this->notFound($response);
        }

        $delivery = $this->queue->enqueue($webhook, $this->payload_builder->ping(), DeliveryQueue::generateMessageId());
        return $this->reportResult(
            $response,
            $this->dispatcher->sendNow($delivery),
            $this->routeparser->urlFor('webhookDeliveries', ['id' => (string)$id])
        );
    }

    /**
     * Redirect with a delivery attempt outcome
     *
     * @param ?DeliveryResult $result       Attempt result, null if another worker was sending it
     * @param string          $redirect_url Where to go next
     */
    private function reportResult(Response $response, ?DeliveryResult $result, string $redirect_url): Response
    {
        if ($result === null) {
            return $this->redirect(
                response: $response,
                redirect_url: $redirect_url,
                warnings: [_T('This delivery is being sent already.')]
            );
        }

        if ($result->isSuccess()) {
            return $this->redirect(
                response: $response,
                redirect_url: $redirect_url,
                successes: [
                    sprintf(
                        //TRANS: %1$d is the HTTP status code
                        _T('Delivery succeeded, the receiver answered %1$d.'),
                        (int)$result->status_code
                    )
                ]
            );
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            errors: [
                sprintf(
                    //TRANS: %1$s is the error message
                    _T('Delivery failed: %1$s'),
                    (string)$result->error
                )
            ]
        );
    }

    /**
     * Store webhook from posted values
     *
     * @param Webhook $webhook Webhook to store
     */
    private function store(Request $request, Response $response, Webhook $webhook): Response
    {
        $post = (array)$request->getParsedBody();
        $is_new = $webhook->isNew();

        if (isset($post['cancel'])) {
            return $this->redirect(
                response: $response,
                redirect_url: $this->cancelUri($this->getArgs($request))
            );
        }

        $this->hydrate($webhook, $post);
        $errors = $webhook->validate();

        if ($errors === []) {
            try {
                if (!$webhook->save()) {
                    $errors[] = _T('An error occurred storing the webhook :(');
                }
            } catch (EntityException $e) {
                Analog::log('Unable to store webhook | ' . $e->getMessage(), Analog::ERROR);
                if (Galette::isDebugEnabled()) {
                    throw $e;
                }
                $errors[] = _T('An error occurred storing the webhook :(');
            }
        }

        if ($errors !== []) {
            //values are kept, not the object: it holds a database connection
            $this->session->{self::FORM_SESSION_KEY} = $post;
            return $this->redirect(
                response: $response,
                redirect_url: $is_new
                    ? $this->routeparser->urlFor('addWebhook')
                    : $this->routeparser->urlFor('editWebhook', ['id' => (string)$webhook->getId()]),
                errors: $errors
            );
        }

        $warnings = [];
        if ($webhook->isInsecure()) {
            $warnings[] = _T('This webhook does not use https: data will be sent unencrypted.');
        }
        return $this->redirect(
            response: $response,
            //a new webhook is shown again, for its secret to be copied
            redirect_url: $is_new
                ? $this->routeparser->urlFor('editWebhook', ['id' => (string)$webhook->getId()])
                : $this->redirectUri([]),
            successes: [
                sprintf(
                    //TRANS: %1$s is the webhook name
                    _T('Webhook %1$s has been stored.'),
                    $webhook->getName()
                )
            ],
            warnings: $warnings
        );
    }

    /**
     * Set posted values on webhook
     *
     * @param Webhook              $webhook Webhook
     * @param array<string, mixed> $post    Posted values
     */
    private function hydrate(Webhook $webhook, array $post): void
    {
        $events = [];
        foreach ((array)($post['events'] ?? []) as $value) {
            $event = is_string($value) ? WebhookEvent::tryFrom($value) : null;
            if ($event !== null) {
                $events[] = $event;
            }
        }

        $webhook
            ->setName((string)($post['name'] ?? ''))
            ->setUrl((string)($post['url'] ?? ''))
            ->setEvents($events)
            ->setAccessLevel((int)($post['access_level'] ?? 0))
            ->setActive(isset($post['active']));
    }

    /**
     * Display form
     *
     * @param Webhook $webhook    Webhook
     * @param string  $page_title Page title
     */
    private function renderForm(Response $response, Webhook $webhook, string $page_title): Response
    {
        //values of a form that could not be stored take precedence
        $post = $this->session->{self::FORM_SESSION_KEY} ?? null;
        if (is_array($post)) {
            $this->hydrate($webhook, $post);
        }
        unset($this->session->{self::FORM_SESSION_KEY});

        $this->view->render(
            $response,
            'pages/webhook_form.html.twig',
            [
                'page_title' => $page_title,
                'webhook' => $webhook,
                'is_new' => $webhook->isNew(),
                'grouped_events' => WebhookEvent::grouped(),
                'access_levels' => Webhook::getAccessLevels(),
            ]
        );
        return $response;
    }

    /**
     * Load a webhook
     *
     * @param int $id Webhook identifier
     */
    private function loadWebhook(int $id): ?Webhook
    {
        try {
            return new Webhook($id);
        } catch (EntityException) {
            return null;
        }
    }

    /**
     * Redirect to list for an unknown webhook
     */
    private function notFound(Response $response): Response
    {
        return $this->redirect(
            response: $response,
            redirect_url: $this->redirectUri([]),
            errors: [_T('This webhook does not exist.')]
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
        return $this->routeparser->urlFor('webhooks');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveWebhook',
            ['id' => (string)$args['id']]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        $webhook = $this->loadWebhook((int)$args['id']);
        return sprintf(
            //TRANS: %1$s is the webhook name
            _T('Remove webhook %1$s'),
            $webhook?->getName() ?? ''
        );
    }

    /**
     * Remove object, along with its deliveries
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        $webhook = $this->loadWebhook((int)$args['id']);
        return $webhook !== null && $webhook->delete();
    }

    // /CRUD - Delete
}
