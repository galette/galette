<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Entity\Adherent;

//TODO: find a better way.
//phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used on file inclusion
$members_fields_cats = [
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
