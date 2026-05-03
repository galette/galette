<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\DynamicFields;

use Galette\Core\Db;

/**
 * Line field type
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Line extends DynamicField
{
    /**
     * Default constructor
     *
     * @param Db   $zdb Database instance
     * @param ?int $id  Optional field id to load data
     */
    public function __construct(Db $zdb, ?int $id = null)
    {
        parent::__construct($zdb, $id);
        $this->has_data = true;
        $this->has_width = true;
        $this->has_min_size = true;
        $this->has_size = true;
        $this->multi_valued = true;
    }

    /**
     * Get field type
     */
    public function getType(): int
    {
        return self::LINE;
    }
}
