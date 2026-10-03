<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

/**
 * Menu provider interface
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface MenuProviderInterface
{
    /**
     * Get plugins menus
     *
     * @return array<string, string|array<string,mixed>>
     */
    public function getMenus(): array;

    /**
     * Get plugins public menus
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getPublicMenus(): array;
}
