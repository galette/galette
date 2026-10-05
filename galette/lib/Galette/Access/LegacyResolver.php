<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Interfaces\PermissionResolverInterface;

/**
 * Resolves permissions from legacy access levels and preferences
 *
 * This is how permissions are granted when the "acls" feature flag is off;
 * it must not change any existing behavior.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LegacyResolver implements PermissionResolverInterface
{
    /**
     * Constructor
     *
     * @param Preferences $preferences Preferences instance
     */
    public function __construct(
        private readonly Preferences $preferences
    ) {
    }

    /**
     * Is permission granted?
     *
     * @param Login      $login      Login to check
     * @param Permission $permission Permission
     * @param mixed      $subject    Object the permission applies to, if any
     */
    public function isGranted(Login $login, Permission $permission, mixed $subject = null): bool
    {
        if ($login->getAccessLevel() >= $permission->level) {
            return true;
        }

        return $this->isGrantedToManagers($login, $permission);
    }

    /**
     * Are groups managers granted, whatever group they manage?
     *
     * @param Login      $login      Login to check
     * @param Permission $permission Permission
     */
    private function isGrantedToManagers(Login $login, Permission $permission): bool
    {
        if ($permission->managers === null || $permission->managers === false || !$login->isGroupManager()) {
            return false;
        }

        return $permission->managers === true || (bool)$this->preferences->{$permission->managers};
    }
}
