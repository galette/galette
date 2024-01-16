<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette application instance
 *
 * PHP version 5
 *
 * Copyright © 2022 The Galette Team
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 1.0.0-dev - 2022-05-28
 */

namespace Galette\Core;

use Galette\Entity\Adherent;

/**
 * Galette application instance
 *
 * @category  Core
 * @name      Galette
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 1.0.0-dev - 2022-05-28
 */
abstract class GalettePlugin
{
    /**
     * Get all menus
     *
     * @return array
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
     * @return array
     */
    public static function getMenus(bool $public = false): array
    {
        $menus = static::getMenusContents();
        return $menus;
    }

    /**
     * Get plugins public menus
     *
     * @return array
     */
    public static function getPublicMenuItems(): array
    {
        global $preferences, $login;

        $menus = [];
        if ($preferences->showPublicPages($login)) {
            $menus = static::getPublicMenusItemsList();
        }

        return $menus;
    }

    /**
     * Get plugins dashboards
     *
     * @return array|array[]
     */
    public static function getDashboards(): array
    {
        $dashboards = static::getDashboardsContents();
        return $dashboards;
    }

    /**
     * Extra menus entries
     *
     * @return array|array[]
     */
    abstract public static function getMenusContents(): array;

    /**
     * Extra public menus entries
     *
     * @return array|array[]
     */
    abstract public static function getPublicMenusItemsList(): array;

    /**
     * Get dashboards contents
     *
     * @return array|array[]
     */
    abstract public static function getDashboardsContents(): array;

    /**
     * Get member actions
     *
     * @param Adherent $member Current member
     *
     * @return array|array[]
     */
    public static function getListActions(Adherent $member): array
    {
        $actions = static::getListActionsContents($member);
        return $actions;
    }

    /**
     * Get detailed member actions
     *
     * @param Adherent $member Current member
     *
     * @return array|array[]
     */
    public static function getDetailedActions(Adherent $member): array
    {
        $actions = static::getDetailedActionsContents($member);
        return $actions;
    }

    /**
     * Get member batch actions
     *
     * @return array|array[]
     */
    public static function getBatchActions(): array
    {
        $actions = static::getBatchActionsContents();
        return $actions;
    }

    /**
     * Get actions contents
     *
     * @param Adherent $member Current member
     *
     * @return array|array[]
     */
    abstract public static function getListActionsContents(Adherent $member): array;

    /**
     * Get batch actions contents
     *
     * @return array|array[]
     */
    abstract public static function getBatchActionsContents(): array;

    /**
     * Get detailed actions contents
     *
     * @param Adherent $member Current member
     *
     * @return array|array[]
     */
    abstract public static function getDetailedActionsContents(Adherent $member): array;
}
