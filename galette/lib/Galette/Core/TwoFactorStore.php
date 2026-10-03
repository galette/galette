<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

/**
 * Where a second factor is kept.
 *
 * Members have a row of their own, keyed on their identifier. The super
 * administrator has none -- it is not a member, it lives in the preferences --
 * so the verification, the replay protection and the policy are written against
 * this interface rather than against one storage.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface TwoFactorStore
{
    /**
     * Is a second factor loaded?
     */
    public function isLoaded(): bool;

    /**
     * Is the loaded second factor usable, the owner having confirmed it?
     */
    public function isEnabled(): bool;

    /**
     * Shared secret of the loaded second factor
     */
    public function getSecret(): string;

    /**
     * Last accepted time slice, null when none has been recorded yet
     */
    public function getLastTimeslice(): ?int;

    /**
     * Remember the last accepted time slice, so a code cannot be replayed
     *
     * @param int $timeslice Accepted time slice
     */
    public function setLastTimeslice(int $timeslice): bool;

    /**
     * Who the loaded second factor belongs to, for logs
     */
    public function getOwner(): string;
}
