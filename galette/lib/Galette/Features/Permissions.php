<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Features;

/**
 * Permissions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait Permissions
{
    protected ?int $permission = null;
    protected bool $can_public = false;

    public const int NOBODY = 0;
    public const int USER_WRITE = 1;
    public const int ADMIN = 2;
    public const int STAFF = 3;
    public const int MANAGER = 4;
    public const int USER_READ = 5;
    public const int ALL = 10;

    /**
     * Get permissions list
     *
     * @param bool $can_public Can have "public" permission
     *
     * @return array<int, string>
     */
    public static function getPermissionsList(bool $can_public = false): array
    {
        $list = [
            self::NOBODY => _T("Inaccessible"),
        ];

        if ($can_public) {
            $list += [self::ALL => _T("Public")];
        }

        $list += [
            self::USER_READ => _T("User, read only"),
            self::USER_WRITE => _T("User, read/write"),
            self::MANAGER => _T("Group manager"),
            self::STAFF => _T("Staff member"),
            self::ADMIN => _T("Administrator"),
        ];

        return $list;
    }

    /**
     * Get permission name
     */
    public function getPermissionName(): string
    {
        $perms = self::getPermissionsList($this->can_public);
        return $perms[$this->getPermission()];
    }

    /**
     * Get current permissions
     */
    public function getPermission(): ?int
    {
        return $this->permission;
    }
}
