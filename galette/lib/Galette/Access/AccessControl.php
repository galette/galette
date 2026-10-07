<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use Galette\Core\Login;
use Galette\Interfaces\PermissionResolverInterface;

/**
 * Access control: is a permission granted?
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AccessControl
{
    /**
     * Constructor
     *
     * @param Permissions                 $permissions Permissions catalog
     * @param PermissionResolverInterface $resolver    Permissions resolver
     * @param Login                       $login       Current login
     */
    public function __construct(
        private readonly Permissions $permissions,
        private readonly PermissionResolverInterface $resolver,
        private readonly Login $login
    ) {
    }

    /**
     * Is permission granted?
     *
     * @param string $permission Permission name, "domain:action"
     * @param mixed  $subject    Object the permission applies to, if any
     * @param ?Login $login      Login to check, current one if null
     *
     * @throws \InvalidArgumentException when permission does not exist
     */
    public function can(string $permission, mixed $subject = null, ?Login $login = null): bool
    {
        return $this->resolver->isGranted(
            $login ?? $this->login,
            $this->permissions->get($permission),
            $subject
        );
    }

    /**
     * Get the groups a permission is granted on, to restrict lists
     *
     * @param string $permission Permission name, "domain:action"
     * @param ?Login $login      Login to check, current one if null
     *
     * @return ?array<int> Null when granted on every group, groups identifiers otherwise;
     *                     empty when not granted at all
     *
     * @throws \InvalidArgumentException when permission does not exist
     */
    public function getGroupScope(string $permission, ?Login $login = null): ?array
    {
        return $this->resolver->getGroupScope(
            $login ?? $this->login,
            $this->permissions->get($permission)
        );
    }
}
