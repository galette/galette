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

namespace Galette\Entity\Attributes;

use Attribute;

/**
 * Marks a property as a database column that should be persisted.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public const string TYPE_STRING = 'string';
    public const string TYPE_INT = 'int';
    public const string TYPE_BOOL = 'bool';
    public const string TYPE_DATE = 'date';
    public const string TYPE_DATETIME = 'datetime';
    public const string TYPE_TIMESTAMP = 'timestamp';
    public const string TYPE_FLOAT = 'float';
    public const string TYPE_JSON = 'json';

    /**
     * @param string|null  $name       Column name in database (if different from property name)
     * @param self::TYPE_* $type       Expected column type
     * @param bool         $insertable Whether this column should be included in INSERT statements
     * @param bool         $updatable  Whether this column should be included in UPDATE statements
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly bool $insertable = true,
        public readonly bool $updatable = true
    ) {
    }

    /**
     * Get the database column name
     *
     * @param string $propertyName The property name to use if no custom name is set
     */
    public function getColumnName(string $propertyName): string
    {
        return $this->name ?? $propertyName;
    }

    /**
     * Get type
     */
    public function getType(): ?string
    {
        return $this->type;
    }
}
