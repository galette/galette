<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
    /**
     * Default constructor
     *
     * @param string $title    Entry title
     * @param Post[] $posts    Posts
     * @param int    $position Position of entry in the list
     */
    public function __construct(
        private readonly string $title,
        private readonly array $posts,
        private readonly int $position = 0
    ) {
    }

    /**
     * Get entry title
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
