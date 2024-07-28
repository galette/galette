<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
