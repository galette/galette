<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Features;

use Galette\Access\AccessControl;
use Galette\Core\Login;

/**
 * Objects whose access is checked against permissions, and lists restricted by them
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait AccessControlled
{
    /**
     * Is permission granted on current object?
     *
     * @param string $permission Permission name, "domain:action"
     * @param Login  $login      Login to check
     */
    protected function isGranted(string $permission, Login $login): bool
    {
        global $container;

        /** @var AccessControl $access */
        $access = $container->get(AccessControl::class);
        return $access->can($permission, $this, $login);
    }

    /**
     * Get the groups a permission is granted on, to restrict lists
     *
     * @param string $permission Permission name, "domain:action"
     * @param Login  $login      Login to check
     *
     * @return ?array<int> Null when granted on every group, groups identifiers otherwise
     */
    protected function getGroupScope(string $permission, Login $login): ?array
    {
        global $container;

        /** @var AccessControl $access */
        $access = $container->get(AccessControl::class);
        return $access->getGroupScope($permission, $login);
    }
}
