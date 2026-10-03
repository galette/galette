<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

/**
 * The super administrator's second factor.
 *
 * That account is not a member: its credentials live in the preferences, and so
 * does its second factor. It therefore gets no recovery codes -- the way back
 * in is the documented one for this account, clearing the preference in
 * database, which anybody able to lose it can reach.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwoFactorSuperAdmin implements TwoFactorStore
{
    /**
     * Default constructor
     *
     * @param Preferences $preferences Preferences instance
     */
    public function __construct(private readonly Preferences $preferences)
    {
    }

    /**
     * A secret is always readable, there is nothing to load
     */
    public function isLoaded(): bool
    {
        return $this->getSecret() !== '';
    }

    /**
     * Is the second factor confirmed and in use?
     */
    public function isEnabled(): bool
    {
        return $this->isLoaded() && (bool)$this->preferences->pref_2fa_superadmin_enabled;
    }

    /**
     * Shared secret
     */
    public function getSecret(): string
    {
        return (string)$this->preferences->pref_2fa_superadmin_secret;
    }

    /**
     * Last accepted time slice
     */
    public function getLastTimeslice(): ?int
    {
        $slice = (int)$this->preferences->pref_2fa_superadmin_timeslice;
        //0 is the default, and no real slice: the epoch is decades behind us
        return $slice > 0 ? $slice : null;
    }

    /**
     * Remember the last accepted time slice
     *
     * @param int $timeslice Accepted time slice
     */
    public function setLastTimeslice(int $timeslice): bool
    {
        $this->preferences->pref_2fa_superadmin_timeslice = $timeslice;
        return $this->preferences->store();
    }

    /**
     * Who this second factor belongs to
     */
    public function getOwner(): string
    {
        return 'super administrator';
    }

    /**
     * Store a freshly generated secret, not usable until confirmed
     *
     * @param string $secret Base32 shared secret
     */
    public function create(string $secret): bool
    {
        $this->preferences->pref_2fa_superadmin_secret = $secret;
        $this->preferences->pref_2fa_superadmin_enabled = false;
        $this->preferences->pref_2fa_superadmin_timeslice = 0;
        return $this->preferences->store();
    }

    /**
     * Turn it on, the secret having been proved held
     */
    public function enable(): bool
    {
        if (!$this->isLoaded()) {
            throw new \RuntimeException('No second factor loaded!');
        }

        $this->preferences->pref_2fa_superadmin_enabled = true;
        return $this->preferences->store();
    }

    /**
     * Drop it
     */
    public function remove(): bool
    {
        $this->preferences->pref_2fa_superadmin_secret = '';
        $this->preferences->pref_2fa_superadmin_enabled = false;
        $this->preferences->pref_2fa_superadmin_timeslice = 0;
        return $this->preferences->store();
    }
}
