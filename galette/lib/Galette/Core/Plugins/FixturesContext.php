<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Core\Preferences;

/**
 * What a plugin gets to seed its fixtures with
 *
 * Login is the superadmin one. Members and groups are those core fixtures
 * created, in creation order: pick them by position rather than by name, core
 * fixtures may change.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class FixturesContext
{
    /**
     * Default constructor
     *
     * @param Db                 $zdb         Database instance
     * @param Login              $login       Logged in (superadmin) instance
     * @param Preferences        $preferences Preferences instance
     * @param History            $history     History instance
     * @param Plugins            $plugins     Plugins instance
     * @param array<string, int> $members     Fixture members identifiers, indexed by login
     * @param array<string, int> $groups      Fixture groups identifiers, indexed by name
     */
    public function __construct(
        public Db $zdb,
        public Login $login,
        public Preferences $preferences,
        public History $history,
        public Plugins $plugins,
        public array $members = [],
        public array $groups = [],
    ) {
    }

    /**
     * Get fixture member identifier at position, wrapping around
     *
     * @param int $position Position, from 0
     */
    public function getMemberId(int $position): ?int
    {
        return $this->pick($this->members, $position);
    }

    /**
     * Get fixture group identifier at position, wrapping around
     *
     * @param int $position Position, from 0
     */
    public function getGroupId(int $position): ?int
    {
        return $this->pick($this->groups, $position);
    }

    /**
     * Pick an identifier at position, wrapping around
     *
     * @param array<string, int> $ids      Identifiers
     * @param int                $position Position, from 0
     */
    private function pick(array $ids, int $position): ?int
    {
        if ($ids === []) {
            return null;
        }
        $ids = array_values($ids);
        return $ids[$position % count($ids)];
    }
}
