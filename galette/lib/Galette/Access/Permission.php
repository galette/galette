<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Galette\Core\Authentication;
use Galette\Core\Preferences;

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
     * Groups managers and members are granted always (true), never (null), or
     * when a preference is on (its name) - or any of them (a list of names).
     *
     * @param string                         $name     Permission name, "domain:action"
     * @param string                         $label    Translated label
     * @param int                            $level    Lowest legacy access level granted,
     *                                                 one of Authentication::ACCESS_*
     * @param string|array<string>|bool|null $managers Whether groups managers are granted, on their groups
     * @param string|bool|null               $members  Whether any member is granted
     */
    public function __construct(
        public string $name,
        public string $label,
        public int $level = Authentication::ACCESS_ADMIN,
        public string|array|bool|null $managers = null,
        public string|bool|null $members = null
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

    /**
     * Are groups managers granted, on their groups, with current preferences?
     *
     * @param Preferences $preferences Preferences instance
     */
    public function isGrantedToManagers(Preferences $preferences): bool
    {
        return $this->isOn($this->managers, $preferences);
    }

    /**
     * Is any member granted, with current preferences?
     *
     * @param Preferences $preferences Preferences instance
     */
    public function isGrantedToMembers(Preferences $preferences): bool
    {
        return $this->isOn($this->members, $preferences);
    }

    /**
     * Is a grant on: always (true), or when its preference - or any of them - is on
     *
     * @param string|array<string>|bool|null $grant       Grant
     * @param Preferences                    $preferences Preferences instance
     */
    private function isOn(string|array|bool|null $grant, Preferences $preferences): bool
    {
        if (is_bool($grant) || $grant === null) {
            return $grant === true;
        }

        foreach ((array)$grant as $preference) {
            if ($preferences->$preference) {
                return true;
            }
        }
        return false;
    }
}
