<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Entity\DTO;

use Galette\Entity\Attributes\Column;

/**
 * Data Transfer Object representing a mapping between an entity property and its database column.
 * Used internally by AbstractEntity for reflection-based column mapping.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class ColumnMapping
{
    public const int SCOPE_NONE = 0;
    public const int SCOPE_ALL = 1;
    public const int SCOPE_INSERT = 2;
    public const int SCOPE_UPDATE = 3;


    /**
     * Default constructor
     * @param string         $propertyName The name of the entity property
     * @param string         $columnName   The name of the database column
     * @param mixed          $value        The current value of the property (null if not initialized)
     * @param Column::TYPE_* $type         The data type of the column (one of Column::TYPE_*)
     */
    public function __construct(
        public string $propertyName,
        public string $columnName,
        public mixed $value = null,
        public string $type = Column::TYPE_STRING,
    ) {
    }
}
