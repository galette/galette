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
 * News post tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Post extends TestCase
{
    /**
     * Test post
     *
     * @return void
     */
    public function testPost(): void
    {
        $title = 'Post title';
        $url = 'https://example.com/post';
        $date = '2025-08-15';

        $post = new \Galette\IO\News\Post($title);
        $this->assertSame($title, $post->getTitle());
        $this->assertNull($post->getUrl());
        $this->assertNull($post->getDate());

        $post = new \Galette\IO\News\Post($title, $url, $date);
        $this->assertSame($title, $post->getTitle());
        $this->assertSame($url, $post->getUrl());
        $this->assertSame($date, $post->getDate());

        // Test with empty title but URL provided
        $post = new \Galette\IO\News\Post('', $url);
        $this->assertSame($url, $post->getTitle());

        // Test with empty title and URL
        $this->expectException(\InvalidArgumentException::class);
        new \Galette\IO\News\Post('');
    }
}
