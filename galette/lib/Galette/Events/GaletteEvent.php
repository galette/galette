<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Event name
 *
 * PHP version 5
 *
 * Copyright © 2023 The Galette Team
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
 *
 * @category  Events
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 2023-01-10
 */

namespace Galette\Events;

use League\Event\HasEventName;

/**
 * Event name
 *
 * @category  Events
 * @name      MemberListener
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 2023-01-10
 */
class GaletteEvent implements HasEventName
{
    /** @var string */
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
