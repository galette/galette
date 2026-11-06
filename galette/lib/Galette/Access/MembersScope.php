<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Laminas\Db\Sql\Select;

/**
 * Members whose data a permission gives access to: everyone when the
 * permission is granted globally; otherwise current user, their children,
 * and members of the groups the permission is granted on.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class MembersScope
{
    /**
     * Constructor
     *
     * @param Db          $zdb    Database instance
     * @param Login       $login  Login instance
     * @param ?array<int> $groups Groups the permission is granted on, null for all
     */
    public function __construct(
        private Db $zdb,
        private Login $login,
        private ?array $groups
    ) {
    }

    /**
     * Is permission granted on every member?
     */
    public function isGlobal(): bool
    {
        return $this->groups === null;
    }

    /**
     * Is permission granted on some groups?
     */
    public function hasGroups(): bool
    {
        return $this->groups !== null && count($this->groups) > 0;
    }

    /**
     * Is permission granted on specified member?
     *
     * @param int $id_adh Member identifier
     */
    public function allows(int $id_adh): bool
    {
        if ($this->groups === null || $id_adh === (int)$this->login->id) {
            return true;
        }

        $member = new Adherent(
            $this->zdb,
            $id_adh,
            [
                'picture' => false,
                'groups' => $this->hasGroups(),
                'dues' => false,
                'parent' => true
            ]
        );

        if ($member->hasParent() && $member->parent->id == $this->login->id) {
            return true;
        }

        return $this->hasGroups()
            && count(array_intersect($this->groups, array_keys($member->getGroups()))) > 0;
    }

    /**
     * Members of the groups permission is granted on
     */
    public function getGroupsMembersSelect(): Select
    {
        //use a subquery rather than a join, so a member belonging to
        //several groups does not duplicate rows
        $select = $this->zdb->select(Group::GROUPSUSERS_TABLE, 'users_groups');
        $select->columns([Adherent::PK]);
        $select->where->in(
            'users_groups.' . Group::PK,
            $this->groups ?: [0]
        );
        return $select;
    }
}
