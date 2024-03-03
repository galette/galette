<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Features;

use Galette\Entity\FieldsConfig;
use Throwable;
use Analog\Analog;
use Galette\Core\L10n;
use Laminas\Db\Sql\Expression;

/**
 * Permissions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait Permissions
{
    protected ?int $permission = null;
    protected bool $can_public = false;

    /* FIXME/ requires PHP 8.2
    public const NOBODY = 0;
    public const USER_WRITE = 1;
    public const ADMIN = 2;
    public const STAFF = 3;
    public const MANAGER = 4;
    public const USER_READ = 5;
    public const ALL = 10;*/

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
            FieldsConfig::NOBODY => _T("Inaccessible"),
        ];

        if ($can_public) {
            $list += [FieldsConfig::ALL => _T("Public")];
        }

        $list += [
            FieldsConfig::USER_READ => _T("Read only"),
            FieldsConfig::USER_WRITE => _T("Read/Write"),
            FieldsConfig::MANAGER => _T("Group manager"),
            FieldsConfig::STAFF => _T("Staff member"),
            FieldsConfig::ADMIN => _T("Administrator"),
        ];

        return $list;
    }

    /**
     * Get permission name
     *
     * @return string
     */
    public function getPermissionName(): string
    {
        $perms = self::getPermissionsList($this->can_public);
        return $perms[$this->getPermission()];
    }

    /**
     * Get current permissions
     *
     * @return integer|null
     */
    public function getPermission(): ?int
    {
        return $this->permission;
    }
}
