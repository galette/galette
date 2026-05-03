<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\IO;

use Galette\Tests\BaseGaletteTestCase;

/**
 * News entry tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Entry extends BaseGaletteTestCase
{
    /**
     * Test entry
     */
    public function testEntry(): void
    {
        $title = 'Entry title';
        $posts = [];

        $entry = new \Galette\IO\News\Entry($title, $posts);
        $this->assertSame($title, $entry->getTitle());
        $this->assertSame($posts, $entry->getPosts());
        $this->assertSame(0, $entry->getPosition());

        $entry = new \Galette\IO\News\Entry($title, $posts, 10);
        $this->assertSame($title, $entry->getTitle());
        $this->assertSame($posts, $entry->getPosts());
        $this->assertSame(10, $entry->getPosition());

        $posts = [
            new \Galette\IO\News\Post('Post 1', 'https://example.com/post1', '2025-08-14'),
            new \Galette\IO\News\Post('Post 2', 'https://example.com/post2', '2025-08-15'),
        ];
        $entry = new \Galette\IO\News\Entry($title, $posts, 10);
        $this->assertSame($title, $entry->getTitle());
        $this->assertSame($posts, $entry->getPosts());
        $this->assertSame(10, $entry->getPosition());
    }
}
