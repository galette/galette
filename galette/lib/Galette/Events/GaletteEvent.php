<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Events;

use League\Event\HasEventName;

/**
 * Event name
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GaletteEvent implements HasEventName
{
    /**
     * Constructor
     *
     * @param string $name   Event name
     * @param object $object Event object
     */
    public function __construct(
        private readonly string $name,
        private readonly object $object
    ) {
    }

    /**
     * Get event name
     */
    public function eventName(): string
    {
        return $this->name;
    }

    /**
     * Get event object
     */
    public function getObject(): object
    {
        return $this->object;
    }
}
