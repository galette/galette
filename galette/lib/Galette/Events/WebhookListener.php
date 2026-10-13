<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Events;

use Closure;
use Galette\Core\Logs;
use Galette\Repository\Webhooks;
use Galette\Webhooks\DeliveryQueue;
use Galette\Webhooks\Payload\Envelope;
use Galette\Webhooks\Payload\PayloadBuilder;
use Galette\Webhooks\ShutdownDispatcher;
use Galette\Webhooks\WebhookEvent;
use League\Event\ListenerRegistry;
use League\Event\ListenerSubscriber;
use Throwable;

/**
 * Queues webhooks deliveries for subscribed events
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class WebhookListener implements ListenerSubscriber
{
    /** @var list<int> Deliveries queued during current request */
    private array $enqueued = [];

    /**
     * Constructor
     *
     * @param Webhooks                 $webhooks        Webhooks repository
     * @param DeliveryQueue            $queue           Deliveries queue
     * @param Closure():PayloadBuilder $payload_builder Payload builder provider; the builder loads the
     *                                                  fields configuration, which is not needed on
     *                                                  most requests
     * @param ?ShutdownDispatcher      $shutdown        Sends queued deliveries at the end of the request
     */
    public function __construct(
        private readonly Webhooks $webhooks,
        private readonly DeliveryQueue $queue,
        private readonly Closure $payload_builder,
        private readonly ?ShutdownDispatcher $shutdown = null
    ) {
    }

    /**
     * Set up listeners
     *
     * @param ListenerRegistry $acceptor Listener
     */
    public function subscribeListeners(ListenerRegistry $acceptor): void
    {
        foreach (WebhookEvent::subscribable() as $event) {
            $acceptor->subscribeTo(
                (string)$event->getInternalName(),
                function (GaletteEvent $galette_event) use ($event): void {
                    $this->enqueue($event, $galette_event->getObject());
                }
            );
        }
    }

    /**
     * Queue deliveries for an event
     *
     * Errors are logged and never thrown: a webhook must not prevent the
     * change it reports.
     *
     * @param WebhookEvent $event   Public event
     * @param object       $subject Object carried by the internal event
     */
    public function enqueue(WebhookEvent $event, object $subject): void
    {
        try {
            $webhooks = $this->webhooks->getActiveFor($event);
            if ($webhooks === []) {
                return;
            }

            /** @var PayloadBuilder $builder */
            $builder = ($this->payload_builder)();
            $message_id = DeliveryQueue::generateMessageId();
            /** @var array<int, Envelope> $envelopes Envelopes per access level */
            $envelopes = [];
            foreach ($webhooks as $webhook) {
                $level = $webhook->getAccessLevel();
                $envelopes[$level] ??= $builder->build($event, $subject, $level);
                $this->enqueued[] = $this->queue->enqueue($webhook, $envelopes[$level], $message_id);
            }
            $this->shutdown?->schedule($this->getEnqueued(...));
        } catch (Throwable $e) {
            Logs::exception($e, sprintf('Unable to queue webhooks for %1$s event', $event->value));
        }
    }

    /**
     * Get deliveries queued during current request
     *
     * @return list<int>
     */
    public function getEnqueued(): array
    {
        return $this->enqueued;
    }
}
