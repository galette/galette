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

namespace Galette\IO;

use Galette\Core\Galette;
use Galette\Features\Cacheable;
use Galette\IO\News\Post;
use Galette\Util\Text;
use Throwable;
use Analog\Analog;

/**
 * News class from rss feed for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class News
{
    use Cacheable;

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
     * @param string  $url     Feed URL
     * @param boolean $nocache Do not try to cache
     */
    public function __construct(string $url, bool $nocache = false)
    {
        $this->feed_url = $this->getFeedURL($url);

        //only if cache should be used
        if ($nocache === false && !Galette::isDebugEnabled()) {
            $this->handleCache($nocache);
        } else {
            $this->parseFeed();
        }
    }

    /**
     * Get data to cache
     *
     * @return string
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
     *
     * @return bool
     */
    protected function cacheLoaded(mixed $contents): bool
    {
        if (Galette::isSerialized($contents)) {
            //legacy cache format
            $this->posts = unserialize($contents);
        } else {
            $this->posts = Galette::jsonDecode($contents);
        }

        //check if posts were cached
        if (count($this->posts) == 0) {
            $this->parseFeed();
            return false;
        }

        return true;
    }

    /**
     * Complete path to cache file
     *
     * @return string
     */
    protected function getCacheFilename(): string
    {
        return GALETTE_CACHE_DIR . str_replace(
            '%feed',
            md5((string) $this->feed_url),
            $this->cache_filename
        );
    }

    /**
     * Parse feed
     *
     * @return void
     */
    private function parseFeed(): void
    {
        try {
            if (!$this->allowURLFOpen()) {
                throw new \RuntimeException(
                    'allow_url_fopen is set to false; cannot load news.'
                );
            }

            $context = stream_context_create($this->stream_opts);
            $data = file_get_contents($this->feed_url, false, $context);
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
                'Unable to load feed from "' . $this->feed_url
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
     *
     * @return string
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

        try {
            $galette_website_langs = $url . '/langs.json';
            $context = stream_context_create($this->stream_opts);
            $langs = json_decode(file_get_contents($galette_website_langs, false, $context));

            if ($i18n->getAbbrev() != 'en' && in_array($i18n->getAbbrev(), $langs)) {
                $url .= '/' . $i18n->getAbbrev();
            }
            $url .= '/feed.xml';
        } catch (Throwable $e) {
            Analog::log(
                'Unable to load feed languages from "' . $url
                . '" :( | ' . $e->getMessage(),
                Analog::ERROR
            );
        }

        return $url;
    }

    /**
     * Check if allow_url_fopen is enabled
     *
     * @return boolean
     */
    protected function allowURLFOpen(): bool
    {
        return (bool)ini_get('allow_url_fopen');
    }

    /**
     * Ensure data to cache are present
     *
     * @return void
     */
    protected function prepareForCache(): void
    {
        $this->parseFeed();
    }
}
