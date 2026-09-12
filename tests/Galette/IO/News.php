<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\IO;

use Galette\Tests\BaseGaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Safe\Exceptions\InfoException;

use function Safe\file_put_contents;
use function Safe\filemtime;
use function Safe\ini_get;
use function Safe\ini_set;
use function Safe\realpath;
use function Safe\touch;
use function Safe\unlink;

/**
 * News tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class News extends BaseGaletteTestCase
{
    private string $local_url;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->local_url = 'file:///' . realpath(GALETTE_ROOT . '../tests/feed.xml');
    }

    /**
     * Test news loading
     */
    public function testLoadNews(): void
    {
        //ensure allow_url_fopen is on
        try {
            if (!ini_get('allow_url_fopen')) {
                ini_set('allow_url_fopen', value: true);
            }
        } catch (InfoException) {
            $this->markTestSkipped('allow_url_fopen cannot be set to true, skipping test');
        }
        //load news without caching
        $news = new \Galette\IO\News($this->local_url, nocache: true);
        $posts = $news->getPosts();
        $this->assertGreaterThan(0, count($posts));
    }

    /**
     * Test news loading
     */
    public function testLoadRSSNews(): void
    {
        //ensure allow_url_fopen is on
        try {
            if (!ini_get('allow_url_fopen')) {
                ini_set('allow_url_fopen', value: true);
            }
        } catch (InfoException) {
            $this->markTestSkipped('allow_url_fopen cannot be set to true, skipping test');
        }
        //load news without caching
        $news = new \Galette\IO\News('file:///' . realpath(GALETTE_ROOT . '../tests/rss.xml'), nocache: true);
        $posts = $news->getPosts();
        $this->assertCount(10, $posts);

        $first_post = $posts[0];
        $second_post = $posts[1];

        $this->assertSame('Test post', $first_post->getTitle());
        $this->assertSame('https://galette.eu/post1', $first_post->getUrl());

        $this->assertSame('This is a test post description without title, so Galette…', $second_post->getTitle());
        $this->assertSame('https://galette.eu/post2', $second_post->getUrl());
    }

    /**
     * Test news caching
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

        //posts read back from cache must be complete
        $cached = new \Galette\IO\News($this->local_url);
        $cached_posts = $cached->getPosts();
        $this->assertCount(count($posts), $cached_posts);
        foreach ($posts as $key => $post) {
            $this->assertInstanceOf(\Galette\IO\News\Post::class, $cached_posts[$key]);
            $this->assertSame($post->getTitle(), $cached_posts[$key]->getTitle());
            $this->assertSame($post->getUrl(), $cached_posts[$key]->getUrl());
            $this->assertSame($post->getDate(), $cached_posts[$key]->getDate());
        }

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
        touch($file, $expired->getTimestamp());

        new \Galette\IO\News($this->local_url);
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
     * Test an unusable cache is rebuilt from the feed
     */
    public function testUnusableCache(): void
    {
        $file = GALETTE_CACHE_DIR . md5($this->local_url) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }

        $reference = (new \Galette\IO\News($this->local_url, nocache: true))->getPosts();
        $this->assertGreaterThan(0, count($reference));

        //cache written by a Galette version that did not serialize post properties
        file_put_contents($file, \Galette\Core\Galette::jsonEncode(array_fill(0, count($reference), new \stdClass())));

        $news = new \Galette\IO\News($this->local_url);
        $posts = $news->getPosts();

        $this->assertCount(count($reference), $posts);
        $this->assertSame($reference[0]->getTitle(), $posts[0]->getTitle());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Unable to load news from cache');

        //and the broken cache has been replaced
        $this->assertSame(
            $reference[0]->getTitle(),
            (new \Galette\IO\News($this->local_url))->getPosts()[0]->getTitle()
        );

        unlink($file);
    }

    /**
     * Test news loading with allow_url_fopen off
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testLoadNewsWExeption(): void
    {
        $news = $this->getMockBuilder(\Galette\IO\News::class)
            ->setConstructorArgs([$this->local_url, true])
            ->onlyMethods(['allowURLFOpen'])
            ->getMock();
        $news->method('allowURLFOpen')->willReturn(false);

        $this->assertCount(0, $news->getPosts());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'allow_url_fopen is set to false; cannot load news.');
    }
}
