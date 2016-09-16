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
    private $_cache_filename = '%feed.cache';
    private $_show = 10;
    //number of hours until cache will be invalid
    private $_cache_timeout = 24;
    private $_feed_url = null;
    private $_posts;

    /**
     * Default constructor
     *
     * @param string  $url     Feed URL
     * @param boolean $nocache Do not try to cache
     */
    public function __construct($url, $nocache = false)
    {
        $this->_feed_url = $url;

        //only if cache should be used
        if ( $nocache === false ) {
            if ( GALETTE_MODE === 'DEV' || !$this->_checkCache() ) {
                $this->_makeCache();
            } else {
                $this->_loadCache();
            }
        }
    }

    /**
     * Check if cache is valid
     *
     * @return boolean
     */
    private function _checkCache()
    {

        $cfile = $this->_getCacheFilename();
        if (file_exists($cfile) ) {
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
                    new \DateInterval('PT' . $this->_cache_timeout . 'H')
                );
                $now = new \DateTime();
                $has_expired = $now > $expire;
                return !$has_expired;
            } catch ( \Exception $e ) {
                Analog::log(
                    'Unable chack cache expiracy. Are you sure you have ' .
                    'properly configured PHP timezone settings on your server?',
                    Analog::WARNING
                );
            }
        } else {
            return false;
        }
    }

    /**
     * Creates/update the cache
     *
     * @param boolean $load Load cache from web
     *
     * @return boolean
     */
    private function _makeCache($load = true)
    {
        if ( $load === true ) {
            $this->_parseFeed();
        }

        $cfile = $this->_getCacheFilename();
        $stream = fopen($cfile, 'w+');
        fwrite(
            $stream,
            serialize(
                $this->_posts
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
    private function _loadCache()
    {
        $cfile = $this->_getCacheFilename();
        $data = unserialize(file_get_contents($cfile));

        $refresh_cache = false;
        $this->_posts = $data;
        //check if posts were cached
        if ( !is_array($this->_posts) || count($this->_posts) == 0 ) {
            $this->_parseFeed();
            $refresh_cache = true;
        }

        if ( $refresh_cache === true ) {
            $this->_makeCache(false);
        }
    }

    /**
     * Complete path to cache file
     *
     * @return string
     */
    private function _getCacheFilename()
    {
        return GALETTE_CACHE_DIR .str_replace(
            '%feed',
            md5($this->_feed_url),
            $this->_cache_filename
        );
    }

    /**
     * Parse feed
     *
     * @return void
     */
    private function _parseFeed()
    {

        try {
            $xml = simplexml_load_file($this->_feed_url);

            if ( !$xml ) {
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
                    if (count($posts) == $this->_show) {
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
                    if (count($posts) == $this->_show) {
                        break;
                    }
                }
            } else {
                throw new \RuntimeException(
                    'Unknown feed type!'
                );
            }
            $this->_posts = $posts;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to load feed from "' . $this->_feed_url  .
                '" :( | ' . $e->getMessage(),
                Analog::ERROR
            );
            $this->_tweets = array();
        }
    }


    /**
     * Get tweets
     *
     * @return array
     */
    public function getPosts()
    {
        return $this->_posts;
    }
}
