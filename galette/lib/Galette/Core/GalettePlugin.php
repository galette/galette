<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

use Galette\Entity\Adherent;

/**
 * Galette application instance
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class GalettePlugin
{
    /**
     * Get all menus
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getAllMenus(): array
    {
        return static::getMenus(true);
    }

    /**
     * Get plugins menus
     *
     * @param bool $public Include public menus. Defaults to false
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getMenus(bool $public = false): array
    {
        return static::getMenusContents();
    }

    /**
     * Get plugins public menus
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getPublicMenuItems(): array
    {
        global $preferences, $login;

        $menus = [];
        if ($preferences->showPublicPage($login, 'pref_publicpages_visibility_plugins')) {
            $menus = static::getPublicMenusItemsList();
        }

        return $menus;
    }

    /**
     * Get plugins dashboards
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getDashboards(): array
    {
        return static::getDashboardsContents();
    }

    /**
     * Get current logged-in user plugins dashboards
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getMyDashboards(): array
    {
        return static::getMyDashboardsContents();
    }

    /**
     * Extra menus entries
     *
     * @return array<string, string|array<string,mixed>>
     */
    abstract public static function getMenusContents(): array;

    /**
     * Extra public menus entries
     *
     * @return array<int, string|array<string,mixed>>
     */
    abstract public static function getPublicMenusItemsList(): array;

    /**
     * Get dashboards contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    abstract public static function getDashboardsContents(): array;

    /**
     * Get current logged-in user dashboards contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getMyDashboardsContents(): array
    {
        //FIXME: should be abstract since 1.1.4, but would require a Galette bump version in plugins
        return [];
    }

    /**
     * Get member actions
     *
     * @param Adherent $member Current member
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getListActions(Adherent $member): array
    {
        return static::getListActionsContents($member);
    }

    /**
     * Get detailed member actions
     *
     * @param Adherent $member Current member
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getDetailedActions(Adherent $member): array
    {
        return static::getDetailedActionsContents($member);
    }

    /**
     * Get member batch actions
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getBatchActions(): array
    {
        return static::getBatchActionsContents();
    }

    /**
     * Get actions contents
     *
     * @param Adherent $member Current member
     *
     * @return array<int, string|array<string,mixed>>
     */
    abstract public static function getListActionsContents(Adherent $member): array;

    /**
     * Get batch actions contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    abstract public static function getBatchActionsContents(): array;

    /**
     * Get detailed actions contents
     *
     * @param Adherent $member Current member
     *
     * @return array<int, string|array<string,mixed>>
     */
    abstract public static function getDetailedActionsContents(Adherent $member): array;
}
