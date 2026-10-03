<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO\News;

use InvalidArgumentException;
use JsonSerializable;

/**
 * News post
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Post implements JsonSerializable
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
     * Build a post from its cached representation
     *
     * @param array<string, ?string> $data Post data
     */
    public static function fromArray(array $data): self
    {
        if (!array_key_exists('title', $data)) {
            throw new InvalidArgumentException('Missing post title.');
        }

        return new self(
            (string)$data['title'],
            $data['url'] ?? null,
            $data['date'] ?? null
        );
    }

    /**
     * Get data to serialize
     *
     * Properties are private; without this, json_encode() would produce an empty object.
     *
     * @return array<string, ?string>
     */
    public function jsonSerialize(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'date' => $this->date
        ];
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
