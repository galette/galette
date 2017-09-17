<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's news from RSS feed
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  News
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-11-11
 */

namespace Galette\IO;

use Analog\Analog;

/**
 * News class from rss feed for galette
 *
 * @category  News
 * @name      News
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-11-11
 */
class News
{
    private $cache_filename = '%feed.cache';
    private $show = 10;
    //number of hours until cache will be invalid
    private $cache_timeout = 24;
    private $feed_url = null;
    private $posts = [];

    /**
     * Default constructor
     *
     * @param string  $url     Feed URL
     * @param boolean $nocache Do not try to cache
     */
    public function __construct($url, $nocache = false)
    {
        $this->feed_url = $url;

        //only if cache should be used
        if ($nocache === false && GALETTE_MODE !== 'DEV') {
            if (!$this->checkCache()) {
                $this->makeCache();
            } else {
                $this->loadCache();
            }
        } else {
            $this->parseFeed();
        }
    }

    /**
     * Check if cache is valid
     *
     * @return boolean
     */
    private function checkCache()
    {
        $cfile = $this->getCacheFilename();
        if (file_exists($cfile)) {
            try {
                $dformat = 'Y-m-d H:i:s';
                $mdate = \DateTime::createFromFormat(
                    $dformat,
                    date(
                        $dformat,
                        filemtime($cfile)
                    )
                );
                $expire = $mdate->add(
                    new \DateInterval('PT' . $this->cache_timeout . 'H')
                );
                $now = new \DateTime();
                $has_expired = $now > $expire;
                return !$has_expired;
            } catch (\Exception $e) {
                Analog::log(
                    'Unable check cache expiracy. Are you sure you have ' .
                    'properly configured PHP timezone settings on your server?',
                    Analog::WARNING
                );
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Creates/update the cache
     *
     * @return boolean
     */
    private function makeCache()
    {
        $this->parseFeed();
        $cfile = $this->getCacheFilename();
        $stream = fopen($cfile, 'w+');
        fwrite(
            $stream,
            serialize(
                $this->posts
            )
        );
        fclose($stream);
        return false;
    }

    /**
     * Loads entries from cache
     *
     * @return void
     */
    private function loadCache()
    {
        $cfile = $this->getCacheFilename();
        $data = unserialize(file_get_contents($cfile));

        $refresh_cache = false;
        $this->posts = $data;
        //check if posts were cached
        if (!is_array($this->posts) || count($this->posts) == 0) {
            $this->parseFeed();
            $refresh_cache = true;
        }

        if ($refresh_cache === true) {
            $this->makeCache(false);
        }
    }

    /**
     * Complete path to cache file
     *
     * @return string
     */
    private function getCacheFilename()
    {
        return GALETTE_CACHE_DIR .str_replace(
            '%feed',
            md5($this->feed_url),
            $this->cache_filename
        );
    }

    /**
     * Parse feed
     *
     * @return void
     */
    private function parseFeed()
    {
        try {
            if (!ini_get('allow_url_fopen')) {
                throw new \RuntimeException(
                    'allow_url_fopen is set to false; cannot load news.'
                );
            }
            $xml = simplexml_load_file($this->feed_url);

            if (!$xml) {
                throw new \Exception();
            }

            $posts = array();

            if (isset($xml->entry)) {
                //Reading an atom feed
                foreach ($xml->entry as $post) {
                    $posts[] = array(
                        'title' => (string)$post->title,
                        'url'   => (string)$post->link['href'],
                        'date'  => (string)$post->published
                    );
                    if (count($posts) == $this->show) {
                        break;
                    }
                }
            } elseif (isset($xml->channel->item)) {
                //Reading a RSS feed
                foreach ($xml->channel->item as $post) {
                    $posts[] = array(
                        'title' => (string)$post->title,
                        'url'   => (string)$post->link,
                        'date'  => (string)$post->pubDate
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
        } catch (\Exception $e) {
            Analog::log(
                'Unable to load feed from "' . $this->feed_url  .
                '" :( | ' . $e->getMessage(),
                Analog::ERROR
            );
        }
    }


    /**
     * Get posts
     *
     * @return array
     */
    public function getPosts()
    {
        return $this->posts;
    }
}
