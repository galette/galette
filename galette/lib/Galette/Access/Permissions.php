<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

/**
 * Permissions catalog
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Permissions
{
    /** @var array<string, Permission> */
    private array $permissions;

    /**
     * Get a permission
     *
     * @param string $name Permission name
     *
     * @throws \InvalidArgumentException when permission does not exist
     */
    public function get(string $name): Permission
    {
        $permissions = $this->getAll();
        if (!isset($permissions[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown permission "%s"', $name));
        }
        return $permissions[$name];
    }

    /**
     * Does permission exist?
     *
     * @param string $name Permission name
     */
    public function has(string $name): bool
    {
        return isset($this->getAll()[$name]);
    }

    /**
     * Get all permissions
     *
     * @return array<string, Permission>
     */
    public function getAll(): array
    {
        if (!isset($this->permissions)) {
            $this->permissions = [];
            foreach ($this->getCorePermissions() as $permission) {
                $this->permissions[$permission->name] = $permission;
            }
        }
        return $this->permissions;
    }

    /**
     * Core permissions
     *
     * Built on first use, so labels are translated in the current language.
     *
     * @return array<Permission>
     */
    protected function getCorePermissions(): array
    {
        return [];
    }
}
