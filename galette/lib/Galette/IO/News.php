<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's news from RSS feed
 *
 * PHP version 5
 *
 * Copyright © 2014-2018 The Galette Team
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
 * @copyright 2014-2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-11-11
 */

namespace Galette\IO;

use Analog\Analog;
use Galette\Core\Cache;

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
class News extends Cache
{
    private $cache_filename = '%feed.cache';
    private $show = 10;
    private $feed_url = null;
    protected $posts = [];

    /**
     * Default constructor
     *
     * @param string  $url     Feed URL
     * @param boolean $nocache Do not try to cache
     */
    public function __construct($url, $nocache = false)
    {
        $this->feed_url = $url;
        parent::__construct($nocache);
    }

    /**
     * Complete path to cache file
     *
     * @return string
     */
    protected function getCacheFilename()
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
    protected function loadData()
    {
        try {
            if (!ini_get('allow_url_fopen')) {
                throw new \RuntimeException(
                    'allow_url_fopen is set to false; cannot load news.'
                );
            }

            $opts = [
                'http' => [
                    'timeout' => 5
                ]
            ];
            $context = stream_context_create($opts);
            $data = file_get_contents($this->feed_url, false, $context);
            if (!$data) {
                throw new \Exception();
            }

            $xml = simplexml_load_string($data);
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

    /**
     * Attribute name to store data
     *
     * @return string
     */
    protected function getAttributeName()
    {
        return 'posts';
    }
}
