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
 * News tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class News extends TestCase
{
    private string $local_url;
    private \Galette\Core\I18n $i18n;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->i18n = new \Galette\Core\I18n();
        global $i18n;
        $i18n = $this->i18n;
        $this->local_url = 'file:///' . realpath(GALETTE_ROOT . '../tests/feed.xml');
    }

    /**
     * Test news loading
     *
     * @return void
     */
    public function testLoadNews(): void
    {
        //ensure allow_url_fopen is on
        ini_set('allow_url_fopen', true);
        //load news without caching
        $news = new \Galette\IO\News($this->local_url, true);
        $posts = $news->getPosts();
        $this->assertGreaterThan(0, count($posts));
    }

    /**
     * Test news loading
     *
     * @return void
     */
    public function testLoadRSSNews(): void
    {
        //ensure allow_url_fopen is on
        ini_set('allow_url_fopen', true);
        //load news without caching
        $news = new \Galette\IO\News('file:///' . realpath(GALETTE_ROOT . '../tests/rss.xml'), true);
        $posts = $news->getPosts();
        $this->assertCount(10, $posts);

        $first_post = $posts[0];
        $second_post = $posts[1];

        $this->assertSame('Test post', $first_post->getTitle());
        $this->assertSame('https://galette.eu/post1', $first_post->getUrl());

        $this->assertSame('This is a test post description without title, so Galette will use a truncated version of this de...', $second_post->getTitle());
        $this->assertSame('https://galette.eu/post2', $second_post->getUrl());
    }

    /**
     * Test news caching
     *
     * @return void
     */
    public function testCacheNews(): void
    {
        //will use default lang to build RSS URL
        $file = GALETTE_CACHE_DIR . md5($this->local_url) . '.cache';

        //ensure file does not exist
        $this->assertFalse(file_exists($file));

        //load news with caching
        $news = new \Galette\IO\News($this->local_url);

        $posts = $news->getPosts();
        $this->assertGreaterThan(0, count($posts));

        //ensure file does exists
        $this->assertTrue(file_exists($file));

        $dformat = 'Y-m-d H:i:s';
        $mdate = \DateTime::createFromFormat(
            $dformat,
            date(
                $dformat,
                filemtime($file)
            )
        );

        $expired = $mdate->sub(
            new \DateInterval('PT25H')
        );
        $touched = touch($file, $expired->getTimestamp());
        $this->assertTrue($touched);

        $news = new \Galette\IO\News($this->local_url);
        $mnewdate = \DateTime::createFromFormat(
            $dformat,
            date(
                $dformat,
                filemtime($file)
            )
        );
        $isnewdate = $mnewdate > $mdate;
        $this->assertTrue($isnewdate);

        //drop file finally
        unlink($file);
    }


    /**
     * Test news loading with allow_url_fopen off
     *
     * @return void
     */
    public function testLoadNewsWExeption(): void
    {
        $news = $this->getMockBuilder(\Galette\IO\News::class)
            ->setConstructorArgs(array($this->local_url, true))
            ->onlyMethods(array('allowURLFOpen'))
            ->getMock();
        $news->method('allowURLFOpen')->willReturn(false);

        $this->assertCount(0, $news->getPosts());
    }
}
