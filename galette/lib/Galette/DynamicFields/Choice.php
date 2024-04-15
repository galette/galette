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

namespace Galette\DynamicFields;

use Analog\Analog;
use Galette\Core\Db;

/**
 * Choice dynamic field
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Choice extends DynamicField
{
    /**
     * Default constructor
     *
     * @param Db   $zdb Database instance
     * @param ?int $id  Optional field id to load data
     */
    public function __construct(Db $zdb, int $id = null)
    {
        parent::__construct($zdb, $id);
        $this->has_data = true;
        $this->fixed_values = true;
    }

    /**
     * Get field type
     *
     * @return integer
     */
    public function getType(): int
    {
        return self::CHOICE;
    }
}
