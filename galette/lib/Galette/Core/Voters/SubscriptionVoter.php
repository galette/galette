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

namespace Galette\Core\Voters;

use Galette\Interfaces\VoterInterface;
use Galette\Core\Login;

/**
 * Voter that checks if member is up to date of his dues
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SubscriptionVoter implements VoterInterface
{
    /** @var array<string> Permissions that require an up-to-date subscription */
    private array $restricted = [
        'member:read',
        'contribution:write'
    ];

    /**
     * @inheritDoc
     */
    public function vote(Login $login, string $permission, mixed $subject = null): int
    {
        if (in_array($permission, $this->restricted)) {
            return $login->isUp2Date() ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
        }

        return self::ACCESS_ABSTAIN;
    }
}
