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
 * Level, groups managers and members describe what access levels and
 * rights settings of Galette before 1.4 granted: system roles get their
 * default permissions from them.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class Permission
{
    /**
     * Constructor
     *
     * Groups managers and members are granted always (true), never (null), or
     * when a former rights setting was on (its name) - or any of them (a list
     * of names).
     *
     * @param string                         $name     Permission name, "domain:action"
     * @param string                         $label    Translated label
     * @param int                            $level    Lowest access level granted,
     *                                                 one of Authentication::ACCESS_*
     * @param string|array<string>|bool|null $managers Whether groups managers are granted, on their groups
     * @param string|bool|null               $members  Whether any member is granted
     * @param bool                           $owner    Whether members are granted on what they own,
     *                                                 or what their children own
     */
    public function __construct(
        public string $name,
        public string $label,
        public int $level = Authentication::ACCESS_ADMIN,
        public string|array|bool|null $managers = null,
        public string|bool|null $members = null,
        public bool $owner = false
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
     * Are groups managers granted, on their groups, with given rights settings?
     *
     * @param array<string, bool> $rights Former rights settings values
     */
    public function isGrantedToManagers(array $rights): bool
    {
        return $this->isOn($this->managers, $rights);
    }

    /**
     * Is any member granted, with given rights settings?
     *
     * @param array<string, bool> $rights Former rights settings values
     */
    public function isGrantedToMembers(array $rights): bool
    {
        return $this->isOn($this->members, $rights);
    }

    /**
     * Is a grant on: always (true), or when its setting - or any of them - is on
     *
     * @param string|array<string>|bool|null $grant  Grant
     * @param array<string, bool>            $rights Former rights settings values
     */
    private function isOn(string|array|bool|null $grant, array $rights): bool
    {
        if (is_bool($grant) || $grant === null) {
            return $grant === true;
        }

        foreach ((array)$grant as $setting) {
            if ($rights[$setting] ?? false) {
                return true;
            }
        }
        return false;
    }
}
