<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

/**
 * Dashboard provider interface
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface DashboardProviderInterface
{
    /**
     * Get plugins dashboards
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getDashboards(): array;

    /**
     * Get current logged-in user plugins dashboards
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getMyDashboards(): array;
}
