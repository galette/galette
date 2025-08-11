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

namespace Galette\IO\test\units;

use PHPUnit\Framework\TestCase;

/**
 * News entry tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Entry extends TestCase
{
    /**
     * Test entry
     *
     * @return void
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
