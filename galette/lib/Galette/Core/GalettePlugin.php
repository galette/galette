<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Galette\Entity\Adherent;

/**
 * Galette plugins
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * Designed to be extended by plugins; which can implement the following interfaces:
 * - MenuProviderInterface: if the plugin provides extra menu entries (public or private menus)
 * - DashboardProviderInterface: if the plugin provides extra dashboard entries
 * - MemberActionProviderInterface: if the plugin provides extra member actions
 * - NewsProviderInterface: if the plugin provides news to be displayed in the dashboard
 *
 * Note: a plugin can implement one or more of these interfaces, but it is not mandatory to implement all of them.
 * Methods are kept in the base class for backward compatibility, they will throw a deprecation warning if used
 */
abstract class GalettePlugin implements Plugins\InstallableInterface
{
    /**
     * Get plugins menus
     *
     * @return array<string, string|array<string,mixed>>
     * @deprecated 1.2.2
     */
    public function getMenus(): array
    {
        Analog::log(
            static::class . '::getMenusContents() is deprecated, please implement MenuProviderInterface',
            Analog::WARNING
        );
        /** @phpstan-ignore staticMethod.notFound */
        return static::getMenusContents();
    }

    /**
     * Get plugins public menus
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getPublicMenuItems(): array
    {
        global $preferences, $login;

        $menus = [];
        if ($preferences->showPublicPage($login, 'pref_publicpages_visibility_plugins')) {
            if ($this instanceof Plugins\MenuProviderInterface) {
                $menus = $this->getPublicMenus();
            } elseif (method_exists($this, 'getPublicMenusItemsList')) {
                Analog::log(
                    static::class . '::getPublicMenusItemsList() is deprecated, please implement MenuProviderInterface',
                    Analog::WARNING
                );
                /** @phpstan-ignore staticMethod.notFound */
                $menus = static::getPublicMenusItemsList();
            }
        }

        return $menus;
    }

    /**
     * Get plugins dashboards
     *
     * @return array<int, string|array<string,mixed>>
     * @deprecated 1.2.2
     */
    public function getDashboards(): array
    {
        Analog::log(
            static::class . '::getDashboardsContents() is deprecated, please implement DashboardProviderInterface',
            Analog::WARNING
        );
        /** @phpstan-ignore staticMethod.notFound */
        return static::getDashboardsContents();
    }

    /**
     * Get current logged-in user plugins dashboards
     *
     * @return array<int, string|array<string,mixed>>
     * @deprecated 1.2.2
     */
    public function getMyDashboards(): array
    {
        Analog::log(
            static::class . '::getMyDashboardsContents() is deprecated, please implement DashboardProviderInterface',
            Analog::WARNING
        );
        /** @phpstan-ignore staticMethod.notFound */
        return static::getMyDashboardsContents();
    }

    /**
     * Get member actions
     *
     * @param Adherent $member Current member
     *
     * @return array<int, string|array<string,mixed>>
     * @deprecated 1.2.2
     */
    public function getListActions(Adherent $member): array
    {
        Analog::log(
            static::class . '::getListActionsContents() is deprecated, please implement MemberActionProviderInterface',
            Analog::WARNING
        );
        /** @phpstan-ignore staticMethod.notFound */
        return static::getListActionsContents($member);
    }

    /**
     * Get detailed member actions
     *
     * @param Adherent $member Current member
     *
     * @return array<int, string|array<string,mixed>>
     * @deprecated 1.2.2
     */
    public function getDetailedActions(Adherent $member): array
    {
        Analog::log(
            static::class . '::getDetailedActionsContents() is deprecated, please implement MemberActionProviderInterface',
            Analog::WARNING
        );
        /** @phpstan-ignore staticMethod.notFound */
        return static::getDetailedActionsContents($member);
    }

    /**
     * Get member batch actions
     *
     * @return array<int, string|array<string,mixed>>
     * @deprecated 1.2.2
     */
    public function getBatchActions(): array
    {
        Analog::log(
            static::class . '::getBatchActionsContents() is deprecated, please implement MemberActionProviderInterface',
            Analog::WARNING
        );
        /** @phpstan-ignore staticMethod.notFound */
        return static::getBatchActionsContents();
    }

    /**
     * Is the plugin fully installed (including database, extra configuration, etc.)?
     */
    public function isInstalled(): bool;
        Analog::log(
            static::class . '::isInstalled() is deprecated, please implement InstallableInterface',
            Analog::WARNING
        );
        return true;
    }
}
