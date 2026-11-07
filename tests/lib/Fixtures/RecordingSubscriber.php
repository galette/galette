<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures;

use ArrayObject;
use Galette\Events\GaletteEvent;
use League\Event\ListenerRegistry;
use League\Event\ListenerSubscriber;

/**
 * Listener subscriber recording names of the events it receives.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RecordingSubscriber implements ListenerSubscriber
{
    /**
     * Constructor
     *
     * @param list<string>             $events   Events to subscribe to
     * @param ArrayObject<int, string> $received Received events names, shared between subscribers
     */
    public function __construct(
        private readonly array $events,
        private readonly ArrayObject $received
    ) {
    }

    /**
     * Subscribe to configured events
     *
     * @param ListenerRegistry $acceptor Listener registry
     */
    public function subscribeListeners(ListenerRegistry $acceptor): void
    {
        foreach ($this->events as $name) {
            $acceptor->subscribeTo($name, $this->record(...));
        }
    }

    /**
     * Record received event
     *
     * @param GaletteEvent $event Received event
     */
    public function record(GaletteEvent $event): void
    {
        $this->received->append($event->eventName());
    }
}
