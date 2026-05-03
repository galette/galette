<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
