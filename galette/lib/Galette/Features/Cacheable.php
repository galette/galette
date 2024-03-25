<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Features;

use Analog\Analog;
use Galette\Core\Galette;
use Throwable;

/**
 * Cacheable objects trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait Cacheable
{
    //number of hours until cache will be invalid
    protected int $cache_timeout = 24;
    protected bool $nocache = false;

    /**
     * Handle cache
     *
     * @param boolean $nocache Do not try to cache
     *
     * @return void
     */
    protected function handleCache(bool $nocache = false): void
    {
        if ($nocache === false && !Galette::isDebugEnabled()) {
            if (!$this->checkCache()) {
                $this->makeCache();
            } else {
                $this->loadCache();
            }
        }
    }

    /**
     * Check if cache is valid
     *
     * @return boolean
     */
    private function checkCache(): bool
    {
        $cfile = GALETTE_CACHE_DIR . $this->getCacheFilename();
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
            } catch (Throwable $e) {
                Analog::log(
                    'Unable check cache expiry. Are you sure you have ' .
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
     * Complete path to cache file
     *
     * @return string
     */
    abstract protected function getCacheFilename(): string;

    /**
     * Ensure data to cache are present
     *
     * @return void
     */
    abstract protected function prepareForCache(): void;

    /**
     * Creates/update the cache
     *
     * @return void
     */
    protected function makeCache(): void
    {
        if ($this->nocache === true) {
            //for some reason, we do not want to use cache
            return;
        }
        $this->prepareForCache();
        $cfile = $this->getCacheFilename();
        $cdir = dirname($cfile);
        if (!file_exists($cdir)) {
            mkdir($cdir, 0755, true);
        }
        $stream = fopen($cfile, 'w+');
        fwrite(
            $stream,
            $this->getDataTocache()
        );
        fclose($stream);
    }

    /**
     * Get data to cache
     *
     * @return string
     */
    protected function getDataTocache(): string
    {
        throw new \RuntimeException('Method not implemented');
    }

    /**
     * Loads entries from cache
     *
     * @return void
     */
    protected function loadCache(): void
    {
        $cfile = $this->getCacheFilename();
        $fcontents = file_get_contents($cfile);

        if (!$this->cacheLoaded($fcontents)) {
            $this->makeCache();
        }
    }

    /**
     * Called once cache has been loaded.
     *
     * @param mixed $content Content from cache
     *
     * @return bool
     */
    abstract protected function cacheLoaded($content): bool;
}
