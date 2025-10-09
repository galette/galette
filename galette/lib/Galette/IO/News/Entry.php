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

namespace Galette\IO\News;

/**
 * News entry
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Entry
{
    private string $title;
    /** @var Post[] */
    private array $posts;
    private int $position = 0;

    /**
     * Default constructor
     *
     * @param string $title    Entry title
     * @param Post[] $posts    Posts
     * @param int    $position Position of entry in the list
     */
    public function __construct(string $title, array $posts, int $position = 0)
    {
        $this->title = $title;
        $this->posts = $posts;
        $this->position = $position;
    }

    /**
     * Get entry title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get posts
     *
     * @return Post[] Posts
     */
    public function getPosts(): array
    {
        return $this->posts;
    }

    /**
     * Get entry position
     *
     * @return int Position
     */
    public function getPosition(): int
    {
        return $this->position;
    }
}
