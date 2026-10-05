<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Twig;

use Galette\Access\AccessControl;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension to check permissions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AccessControlExtension extends AbstractExtension
{
    /**
     * Constructor
     *
     * @param AccessControl $accessControl Access control instance
     */
    public function __construct(private readonly AccessControl $accessControl)
    {
    }

    /**
     * Get functions
     *
     * @return array<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('can', $this->can(...)),
        ];
    }

    /**
     * Is permission granted to current login?
     *
     * @param string $permission Permission name, "domain:action"
     * @param mixed  $subject    Object the permission applies to, if any
     */
    public function can(string $permission, mixed $subject = null): bool
    {
        return $this->accessControl->can($permission, $subject);
    }
}
