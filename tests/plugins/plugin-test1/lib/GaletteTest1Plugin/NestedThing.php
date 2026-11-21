<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteTest1Plugin;

use Galette\Core\Db;
use Galette\Core\Login;

/**
 * An object of the test plugin getting dynamic fields from a trait of the plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class NestedThing
{
    use ThingFields;

    /** @var array<string> */
    private array $errors = [];

    /**
     * Constructor
     *
     * @param Db     $zdb   Database instance
     * @param ?Login $login Login instance, the global one when null
     * @param ?int   $id    Thing identifier
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly ?Login $login = null,
        private readonly ?int $id = null
    ) {
    }
}
