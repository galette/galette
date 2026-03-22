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

namespace Galette\Core;

use Galette\Interfaces\VoterInterface;
use Analog\Analog;

/**
 * Centralized Access Control system
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AccessControl
{
    /** @var VoterInterface[] */
    private array $voters = [];

    /**
     * Constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(
        private readonly Db $zdb
    ) {
    }

    /**
     * Add a voter
     *
     * @param VoterInterface $voter Voter instance
     */
    public function addVoter(VoterInterface $voter): void
    {
        $this->voters[] = $voter;
    }

    /**
     * Check if user is granted permission
     *
     * @param string $permission Permission name (domain:action)
     * @param mixed  $subject    Subject to check
     * @param ?Login $user       User to check (current logged user if null)
     */
    public function can(string $permission, mixed $subject = null, ?Login $user = null): bool
    {
        if ($user === null) {
            return false;
        }

        // Priority 1: Super-Admin bypass
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Priority 2: Account state
        if (!$user->isActive()) {
            return false;
        }

        // Priority 3: RBAC check (static roles/permissions)
        if ($this->hasPermissionInRoles($user->id, $permission)) {
            return true;
        }

        // Priority 4: Voters (dynamic rules)
        foreach ($this->voters as $voter) {
            $vote = $voter->vote($user, $permission, $subject);
            if ($vote === VoterInterface::ACCESS_GRANTED) {
                return true;
            }
            if ($vote === VoterInterface::ACCESS_DENIED) {
                return false;
            }
        }

        return false;
    }

    /**
     * Check if permission exists in user's roles via SQL
     *
     * @param int    $userId     User ID
     * @param string $permission Permission name
     */
    private function hasPermissionInRoles(int $userId, string $permission): bool
    {
        try {
            $select = $this->zdb->select('adherent_roles', 'ar');
            $select->join(
                    ['rp' => PREFIX_DB . 'role_permissions'],
                    'ar.id_role = rp.id_role',
                    []
                )
                ->join(
                    ['p' => PREFIX_DB . 'permissions'],
                    'rp.id_perm = p.id_perm',
                    []
                )
                ->where([
                    'ar.id_adh' => $userId,
                    'p.nom_perm' => $permission
                ]);

            $results = $this->zdb->execute($select);
            return $results->count() > 0;
        } catch (\Throwable $e) {
            Analog::log('RBAC check failed: ' . $e->getMessage(), Analog::ERROR);
            return false;
        }
    }
}
