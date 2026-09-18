<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use Galette\Core\Galette;
use Galette\Features\Cacheable;
use Galette\IO\News\Post;
use Galette\Util\Text;
use Throwable;
use Analog\Analog;

use function Safe\file_get_contents;
use function Safe\ini_get;
use function Safe\json_decode;
use function Safe\realpath;
use function Safe\simplexml_load_string;

/**
 * News class from rss feed for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class News
{
    use Cacheable;

    //number of hours until a feed that could not be read is asked again
    private const int RETRY_TIMEOUT = 1;

    protected string $cache_filename = '%feed.cache';
    private int $show = 10;
    private ?string $feed_url = null;
    /** @var Post[] */
    private array $posts = [];
    /** @var array<string, array<string, int|string>> */
    private array $stream_opts = [
        'http' => [
            'timeout' => 5
        ]
    ];

    /**
     * Default constructor
     *
     * @param string $requested_url Feed URL
     * @param bool   $nocache       Do not try to cache
     */
    public function __construct(private string $requested_url, bool $nocache = false)
    {
        //a feed that answered nothing is cached as empty, so that an unreachable
        //host is not asked again on every page. It is retried well before a feed
        //that did answer, though: an outage must not cost a whole day of news.
        if ($this->isCacheEmpty()) {
            $this->cache_timeout = self::RETRY_TIMEOUT;
        }

        //only if cache should be used
        if ($nocache === false && !Galette::isDebugEnabled()) {
            $this->handleCache($nocache);
        } else {
            $this->parseFeed();
        }
    }

    /**
     * Get data to cache
     */
    protected function getDataTocache(): string
    {
        return Galette::jsonEncode(
            $this->posts
        );
    }

    /**
     * Called once cache has been loaded.
     *
     * @param mixed $contents Content from cache
     */
    protected function cacheLoaded(mixed $contents): bool
    {
        $posts = [];

        try {
            if (Galette::isSerialized($contents)) {
                //legacy cache format
                $posts = unserialize($contents);
                if (!is_array($posts)) {
                    throw new \RuntimeException('Unreadable legacy cache contents');
                }
            } else {
                foreach (Galette::jsonDecode($contents) as $post) {
                    $posts[] = Post::fromArray($post);
                }
            }
        } catch (Throwable $e) {
            //cache is unusable, it will be rebuilt from the feed
            Analog::log(
                'Unable to load news from cache :( | ' . $e->getMessage(),
                Analog::WARNING
            );
            $this->posts = [];
            return false;
        }

        //an empty feed is an answer like any other: rebuilding the cache here
        //would reach the feed again on every single page
        $this->posts = $posts;
        return true;
    }

    /**
     * Complete path to cache file
     *
     * Keyed on the requested URL and the current language: the Galette website
     * serves a different feed per language, and the key has to be known before
     * the feed URL is resolved - resolving it may reach the network.
     */
    protected function getCacheFilename(): string
    {
        global $i18n;

        return GALETTE_CACHE_DIR . str_replace(
            '%feed',
            md5($this->requested_url . '|' . $i18n->getAbbrev()),
            $this->cache_filename
        );
    }

    /**
     * Has the feed been cached without a single post?
     */
    private function isCacheEmpty(): bool
    {
        $cfile = $this->getCacheFilename();
        if (!file_exists($cfile)) {
            return false;
        }

        try {
            return trim(file_get_contents($cfile)) === '[]';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Parse feed
     */
    private function parseFeed(): void
    {
        try {
            if (!$this->allowURLFOpen()) {
                throw new \RuntimeException(
                    'allow_url_fopen is set to false; cannot load news.'
                );
            }

            //resolved here rather than in the constructor: it may reach the
            //network, and a warm cache must not pay for it
            $this->feed_url = $this->getFeedURL($this->requested_url);

            $context = stream_context_create($this->stream_opts);
            $data = file_get_contents($this->feed_url, use_include_path: false, context: $context);
            if (!$data) {
                throw new \Exception();
            }

            $xml = simplexml_load_string($data);
            if (!$xml) {
                throw new \Exception();
            }

            $posts = [];

            if (isset($xml->entry)) {
                //Reading an atom feed
                foreach ($xml->entry as $post) {
                    $posts[] = new Post(
                        (string)$post->title,
                        (string)$post->link['href'],
                        (string)$post->published
                    );
                    if (count($posts) == $this->show) {
                        break;
                    }
                }
            } elseif (isset($xml->channel->item)) {
                //Reading a RSS feed
                foreach ($xml->channel->item as $post) {
                    $title = (string)$post->title;
                    if (empty($title) && isset($post->description)) {
                        $title = Text::truncateOnWords((string)$post->description);
                    }
                    $posts[] = new Post(
                        $title,
                        (string)$post->link,
                        (string)$post->pubDate
                    );
                    if (count($posts) == $this->show) {
                        break;
                    }
                }
            } else {
                throw new \RuntimeException(
                    'Unknown feed type!'
                );
            }
            $this->posts = $posts;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to load feed from "' . ($this->feed_url ?? $this->requested_url)
                . '" :( | ' . $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Get posts
     *
     * @return Post[]
     */
    public function getPosts(): array
    {
        return $this->posts;
    }

    /**
     * Get feed url, handle Galette website to check available langs
     *
     * @param string $url Requested URL
     */
    public function getFeedURL(string $url): string
    {
        global $i18n;

        if (str_contains($url, 'galette.eu') || trim($url) == '') {
            $url = 'https://galette.eu/site';
        } elseif (str_contains($url, 'localhost:4000')) {
            $url = 'http://localhost:4000/site';
        } else {
            return $url;
        }

        if (defined('GALETTE_TESTS') || getenv('GALETTE_TESTS')) {
            // During tests, we use a local feed file to avoid depending on external resources, if URL is not explicitly set
            // The environment variable covers e2e, where the constant cannot be defined (see tests/router_e2e.php)
            return 'file:///' . realpath(GALETTE_TESTS_PATH . '/feed.xml');
        }

        $lang = $i18n->getAbbrev();
        if ($lang != 'en' && in_array($lang, $this->getWebsiteLangs($url))) {
            $url .= '/' . $lang;
        }

        //appended whatever happened above: the English feed still is a feed,
        //the website itself is not one
        return $url . '/feed.xml';
    }

    /**
     * Languages the Galette website is translated to
     *
     * An unreadable list is not fatal: the English feed is served from the
     * website root, so the caller just does not localize the feed URL.
     *
     * @param string $url Galette website URL
     *
     * @return array<int, string>
     */
    public function getWebsiteLangs(string $url): array
    {
        try {
            $context = stream_context_create($this->stream_opts);
            $langs = json_decode(
                file_get_contents($url . '/langs.json', use_include_path: false, context: $context)
            );

            return is_array($langs) ? $langs : [];
        } catch (Throwable $e) {
            Analog::log(
                'Unable to load feed languages from "' . $url
                . '" :( | ' . $e->getMessage(),
                Analog::ERROR
            );
            return [];
        }
    }

    /**
     * Check if allow_url_fopen is enabled
     */
    protected function allowURLFOpen(): bool
    {
        return (bool)ini_get('allow_url_fopen');
    }

    /**
     * Ensure data to cache are present
     */
    protected function prepareForCache(): void
    {
        $this->parseFeed();
    }
}
