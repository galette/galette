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
 * News post tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Post extends BaseGaletteTestCase
{
    /**
     * Test post
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
