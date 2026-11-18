<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Defaults;

use Galette\Entity\Adherent;

/**
 * Default members fields categories
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class MembersFieldsCategories
{
    /**
     * Get definitions
     *
     * Labels are source strings, stored as is and translated when displayed.
     *
     * @return array<int,array<string,string|int>>
     */
    public static function get(): array
    {
        return [
            [
                'id'         => 1,
                'table_name' => Adherent::TABLE,
                'category'   => "Identity:",
                'position'   => 1
            ],
            [
                'id'         => 2,
                'table_name' => Adherent::TABLE,
                'category'   => "Galette-related data:",
                'position'   => 3
            ],
            [
                'id'         => 3,
                'table_name' => Adherent::TABLE,
                'category'   => "Contact information:",
                'position'   => 2
            ]
        ];
    }
}
