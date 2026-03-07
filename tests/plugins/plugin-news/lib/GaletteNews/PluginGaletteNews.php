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

namespace GaletteNews;

use Galette\Core\GalettePlugin;
use Galette\Core\Plugins\NewsProviderInterface;
use Galette\IO\News\Entry;
use Galette\IO\News\Post;

use function Safe\strtotime;

/**
 * Galette News plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PluginGaletteNews extends GalettePlugin implements NewsProviderInterface
{
    /**
     * Get plugin news
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
     * Is the plugin fully installed (including database, extra configuration, etc.)?
     */
    public function isInstalled(): bool
    {
        return true;
    }
}
