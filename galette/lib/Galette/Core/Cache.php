<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's cache abstract class
 *
 * PHP version 5
 *
 * Copyright © 2018 The Galette Team
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
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-11-11
 */

namespace Galette\Core;

use Analog\Analog;

/**
 * Abstract cache
 *
 * @category  Core
 * @name      Cache
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.2 - 2018-05-15
 */
abstract class Cache
{
    //number of hours until cache will be invalid
    private $cache_timeout = 24;

    /**
     * Default constructor
     *
     * @param boolean $nocache Do not try to cache
     */
    public function __construct($nocache = false)
    {
        //only if cache should be used
        if ($nocache === false && GALETTE_MODE !== 'DEV') {
            if (!$this->checkCache()) {
                $this->makeCache();
            } else {
                $this->loadCache();
            }
        } else {
            $this->loadData();
        }
    }

    /**
     * Check if cache is valid
     *
     * @return boolean
     */
    protected function checkCache()
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
        $this->loadData();
        $cfile = $this->getCacheFilename();
        $stream = fopen($cfile, 'w+');
        $attribute = $this->getAttributeName();
        fwrite(
            $stream,
            serialize(
                $this->$attribute
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
        $attribute = $this->getAttributeName();
        $this->$attribute = $data;
        //check if data were cached
        if (!is_array($this->$attribute) || count($this->$attribute) == 0) {
            $this->loadData();
            $refresh_cache = true;
        }

        if ($refresh_cache === true) {
            $this->makeCache(false);
        }
    }

    /**
     * Method to call to load data to cache
     *
     * @return void
     */
    abstract protected function loadData();

    /**
     * Attribute name to store data
     *
     * @return string
     */
    abstract protected function getAttributeName();

    /**
     * Complete path to cache file
     *
     * @return string
     */
    abstract protected function getCacheFilename();
}
