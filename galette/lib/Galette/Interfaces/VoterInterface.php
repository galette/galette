<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Interfaces;

use Galette\Core\Login;

/**
 * ACL Voter interface
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface VoterInterface
{
    public const ACCESS_GRANTED = 1;
    public const ACCESS_ABSTAIN = 0;
    public const ACCESS_DENIED = -1;

    /**
     * Vote on a permission
     *
     * @param Login  $login      Login instance
     * @param string $permission Permission name
     * @param mixed  $subject    Subject to check
     *
     * @return int
     */
    public function vote(Login $login, string $permission, mixed $subject = null): int;
}
