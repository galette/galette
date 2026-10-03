<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
