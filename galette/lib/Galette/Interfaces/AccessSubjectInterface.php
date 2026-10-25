<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Interfaces;

/**
 * An object permissions are checked on
 *
 * Core entities are known to permissions resolvers; other objects, from
 * plugins, implement this so that permissions granted to owners, groups
 * managers and roles given for a group apply to them.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface AccessSubjectInterface
{
    /**
     * Get the member the object belongs to, if any
     *
     * They, and their parent, are granted permissions marked for owners.
     */
    public function getOwnerId(): ?int;

    /**
     * Get identifiers of the groups the object belongs to
     *
     * Groups managers, and roles given for a group, are granted on them.
     *
     * @return ?array<int> Groups identifiers; null for those of the owner
     */
    public function getGroupsIds(): ?array;
}
