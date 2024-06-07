<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Events;

use League\Event\HasEventName;

/**
 * Event name
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GaletteEvent implements HasEventName
{
    private string $name;
    private object $object;

    /**
     * Constructor
     *
     * @param string $name   Event name
     * @param object $object Event object
     */
    public function __construct(string $name, object $object)
    {
        $this->name = $name;
        $this->object = $object;
    }

    /**
     * Get event name
     *
     * @return string
     */
    public function eventName(): string
    {
        return $this->name;
    }

    /**
     * Get event object
     *
     * @return object
     */
    public function getObject(): object
    {
        return $this->object;
    }
}
