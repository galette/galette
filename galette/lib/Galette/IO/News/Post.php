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

namespace Galette\IO\News;

use InvalidArgumentException;

/**
 * News post
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Post
{
    /**
     * Default constructor
     *
     * @param string  $title Post title
     * @param ?string $url   Post URL
     * @param ?string $date  Post date
     */
    public function __construct(
        private string $title,
        private readonly ?string $url = null,
        private readonly ?string $date = null
    ) {
        if (empty($title) && !empty($url)) {
            $title = $url;
        } elseif (empty($title) && empty($url)) {
            throw new InvalidArgumentException('Post title or URL must be provided.');
        }
        $this->title = $title;
    }

    /**
     * Get post title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get post URL
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Get post date
     */
    public function getDate(): ?string
    {
        return $this->date;
    }
}
