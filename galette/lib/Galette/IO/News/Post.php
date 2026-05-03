<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
