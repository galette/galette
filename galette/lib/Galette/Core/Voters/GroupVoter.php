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
use Galette\Entity\Adherent;
use Galette\Repository\Groups;
use Galette\Core\AccessControl;

/**
 * Voter that checks if a group manager is acting on a member of his group
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GroupVoter implements VoterInterface
{
    /**
     * Constructor
     *
     * @param AccessControl $accessControl AccessControl instance
     */
    public function __construct(private readonly AccessControl $accessControl)
    {
    }

    /**
     * @inheritDoc
     */
    public function vote(Login $login, string $permission, mixed $subject = null): int
    {
        // Only applies to member and group domains
        if (
            !str_starts_with($permission, 'member:') &&
            !str_starts_with($permission, 'group:')
        ) {
            return self::ACCESS_ABSTAIN;
        }

        $roles = $this->accessControl->getUserRoles($login->id);

        // Admins can do everything on groups/members
        if (in_array('Admin', $roles)) {
            return self::ACCESS_GRANTED;
        }

        // Group Managers have restricted access
        if (in_array('GroupManager', $roles)) {
            $managedGroups = $login->getManagedGroups();

            // If the subject is a member, check if he belongs to a managed group
            if ($subject instanceof Adherent) {
                $memberGroups = Groups::loadGroups($subject->id, false, false);

                foreach ($memberGroups as $groupId) {
                    if (in_array($groupId, $managedGroups)) {
                        return self::ACCESS_GRANTED;
                    }
                }
            }

            // If the subject is a group, check if it is managed
            if ($subject instanceof \Galette\Entity\Group) {
                if (in_array($subject->id, $managedGroups)) {
                    return self::ACCESS_GRANTED;
                }
            }
        }

        return self::ACCESS_ABSTAIN;
    }
}
