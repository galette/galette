<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Interfaces;

use Galette\Access\Permission;
use Galette\Core\Login;

/**
 * Decides whether a permission is granted
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface PermissionResolverInterface
{
    /**
     * Is permission granted?
     *
     * @param Login      $login      Login to check
     * @param Permission $permission Permission
     * @param mixed      $subject    Object the permission applies to, if any
     */
    public function isGranted(Login $login, Permission $permission, mixed $subject = null): bool;

    /**
     * Get the groups a permission is granted on
     *
     * Used to restrict lists. What login owns is not part of it.
     *
     * @param Login      $login      Login to check
     * @param Permission $permission Permission
     *
     * @return ?array<int> Null when granted on every group, groups identifiers otherwise;
     *                     empty when not granted at all
     */
    public function getGroupScope(Login $login, Permission $permission): ?array;
}
