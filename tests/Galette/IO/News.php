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
use Safe\DateTime;
use Safe\Exceptions\InfoException;

use function Safe\file_get_contents;
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
     * Cache file a feed is stored in
     *
     * @param string|null $url Feed URL, defaults to the local one
     */
    private function cacheFile(?string $url = null): string
    {
        global $i18n;

        return GALETTE_CACHE_DIR . md5(($url ?? $this->local_url) . '|' . $i18n->getAbbrev()) . '.cache';
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
        $file = $this->cacheFile();

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
        $file = $this->cacheFile();
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
     * Test a warm cache resolves no feed URL
     *
     * Resolving one asks the Galette website for its languages, and that is a
     * network call the dashboard used to pay on every single display.
     */
    public function testWarmCacheResolvesNothing(): void
    {
        $file = $this->cacheFile();
        if (file_exists($file)) {
            unlink($file);
        }

        $counting = new class ($this->local_url) extends \Galette\IO\News {
            public int $resolved = 0;

            /**
             * Get feed url, counting how many times it was resolved
             *
             * @param string $url Requested URL
             */
            public function getFeedURL(string $url): string
            {
                ++$this->resolved;
                return parent::getFeedURL($url);
            }
        };

        //cold cache: the feed has to be resolved, then read
        $this->assertSame(1, $counting->resolved);
        $this->assertGreaterThan(0, count($counting->getPosts()));
        $this->assertTrue(file_exists($file));

        $counting = new class ($this->local_url) extends \Galette\IO\News {
            public int $resolved = 0;

            /**
             * Get feed url, counting how many times it was resolved
             *
             * @param string $url Requested URL
             */
            public function getFeedURL(string $url): string
            {
                ++$this->resolved;
                return parent::getFeedURL($url);
            }
        };

        //warm cache: nothing is resolved, and the posts still come back
        $this->assertSame(0, $counting->resolved);
        $this->assertGreaterThan(0, count($counting->getPosts()));

        unlink($file);
    }

    /**
     * Test a feed that could not be read is cached, and retried before a full one
     */
    public function testUnreadableFeedIsCached(): void
    {
        $url = 'file:///' . GALETTE_TESTS_PATH . '/no-such-feed.xml';
        $file = $this->cacheFile($url);
        if (file_exists($file)) {
            unlink($file);
        }

        $news = new \Galette\IO\News($url);
        $this->assertCount(0, $news->getPosts());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to load feed from');

        //the failure itself is cached...
        $this->assertTrue(file_exists($file));
        $this->assertSame('[]', file_get_contents($file));

        //...so the next page does not ask the feed again - it would log anew
        $cached = new \Galette\IO\News($url);
        $this->assertCount(0, $cached->getPosts());
        $this->expectNoLogEntry();

        //but it is asked again well before the 24 hours a full cache lives
        touch($file, (new DateTime('-2 hours'))->getTimestamp());
        new \Galette\IO\News($url);
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to load feed from');

        unlink($file);
    }

    /**
     * Test website languages cannot make the feed URL unusable
     */
    public function testWebsiteLangs(): void
    {
        $news = new \Galette\IO\News($this->local_url, nocache: true);

        //an unreadable list is not fatal, the caller keeps the plain feed URL
        $this->assertSame([], $news->getWebsiteLangs('file:///' . GALETTE_TESTS_PATH . '/no-such-place'));
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to load feed languages');
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
