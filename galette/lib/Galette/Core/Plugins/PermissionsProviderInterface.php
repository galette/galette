<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

use Galette\Access\Permission;

/**
 * Permissions provider interface
 *
 * A plugin implementing this adds its own permissions to the core catalog:
 * they are checked with `AccessControl::can()` or the `can()` Twig function,
 * can be required by routes in place of an access level in the plugin ACLs,
 * and are listed on the roles administration page.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface PermissionsProviderInterface
{
    /**
     * Get the permissions the plugin declares
     *
     * Permissions belong to the plugin domain, named after its route:
     * "events:create" for a plugin whose route is "events"; others are
     * ignored. Names are stored in roles, so they must not change once
     * released.
     *
     * Level, groups managers and members describe what system roles get when
     * the permission is first known: staff members and administrators from
     * the level, groups managers on their groups, every member.
     *
     * This is called once translations are loaded: labels can be translated
     * in the plugin translation domain.
     *
     * @return array<Permission>
     */
    public function getPermissions(): array;
}
