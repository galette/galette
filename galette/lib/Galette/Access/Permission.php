<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Galette\Core\Authentication;

use function Safe\preg_match;

/**
 * A permission, named "domain:action"
 *
 * Level and groups managers describe what legacy access levels grant; they
 * are the defaults when there is no more specific rule.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class Permission
{
    /**
     * Constructor
     *
     * @param string           $name     Permission name, "domain:action"
     * @param string           $label    Translated label
     * @param int              $level    Lowest legacy access level granted, one of Authentication::ACCESS_*
     * @param string|bool|null $managers Whether groups managers are granted, on their groups:
     *                                   always (true), never (null) or if a preference is on (its name)
     */
    public function __construct(
        public string $name,
        public string $label,
        public int $level = Authentication::ACCESS_ADMIN,
        public string|bool|null $managers = null
    ) {
        if (!preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-z0-9_-]*$/', $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid permission name "%s"', $name));
        }
    }

    /**
     * Get permission domain
     */
    public function getDomain(): string
    {
        return explode(':', $this->name, 2)[0];
    }
}
