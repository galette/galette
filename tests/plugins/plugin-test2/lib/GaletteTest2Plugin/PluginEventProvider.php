<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteTest2Plugin;

use Galette\Core\Db;
use Galette\Events\GaletteEvent;
use League\Event\ListenerRegistry;
use League\Event\ListenerSubscriber;

/**
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginEventProvider implements ListenerSubscriber
{
    /** @var array<int, object> */
    public static array $received = [];

    /**
     * Constructor
     *
     * @param Db $zdb Db instance, injected from the container
     */
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Set up listeners
     *
     * @param ListenerRegistry $acceptor Listener
     */
    public function subscribeListeners(ListenerRegistry $acceptor): void
    {
        $acceptor->subscribeTo(
            'plugin2.test',
            function (GaletteEvent $event): void {
                self::$received[] = $event->getObject();
            }
        );
    }

    /**
     * Get Db instance
     */
    public function getDb(): Db
    {
        return $this->zdb;
    }
}
