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

namespace GaletteNews;

use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Core\GalettePlugin;
use Galette\IO\News\Entry;
use Galette\IO\News\Post;
use GaletteEvents\Filters\EventsList;
use GaletteEvents\Repository\Events;

/**
 * Galette News plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PluginGaletteNews extends GalettePlugin
{
    /**
     * Extra menus entries
     *
     * @return array<string, string|array<string, mixed>>
     */
    public static function getMenusContents(): array
    {
        return [];
    }

    /**
     * Extra public menus entries
     *
     * @return array<int, string|array<string, mixed>>
     */
    public static function getPublicMenusItemsList(): array
    {
        return [];
    }

    /**
     * Get dashboards contents
     *
     * @return array<int, string|array<string, mixed>>
     */
    public static function getDashboardsContents(): array
    {
        return [];
    }

    /**
     * Get actions contents
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string, mixed>>
     */
    public static function getListActionsContents(Adherent $member): array
    {
        return [];
    }

    /**
     * Get detailed actions contents
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string, mixed>>
     */
    public static function getDetailedActionsContents(Adherent $member): array
    {
        return static::getListActionsContents($member);
    }

    /**
     * Get batch actions contents
     *
     * @return array<int, string|array<string, mixed>>
     */
    public static function getBatchActionsContents(): array
    {
        return [];
    }

    /**
     * Get current logged-in user dashboards contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getMyDashboardsContents(): array
    {
        return [];
    }

    /**
     * Get plugin news
     *
     * @return ?Entry
     */
    public function getNews(): ?Entry
    {
        $posts = [
            new Post(
                title: 'A news',
                date: date('Y-m-d H:i:s'),
            ),
            new Post(
                title: 'Older news',
                date: date('Y-m-d H:i:s', strtotime('-1 day')),
            ),
        ];

        return new Entry(
            title: 'Test plugin news',
            posts: $posts,
            position: 42
        );
    }

    /**
     * Is the plugin fully installed (including database, extra configuration, etc)?
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return false;
    }
}
