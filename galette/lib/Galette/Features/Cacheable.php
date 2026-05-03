<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Features;

use Analog\Analog;
use DateInterval;
use Safe\DateTime;
use Galette\Core\Galette;
use RuntimeException;
use Throwable;

use function Safe\fclose;
use function Safe\file_get_contents;
use function Safe\filemtime;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\mkdir;

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
     * @param bool $nocache Do not try to cache
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
     */
    private function checkCache(): bool
    {
        $cfile = GALETTE_CACHE_DIR . $this->getCacheFilename();
        if (file_exists($cfile)) {
            try {
                $dformat = 'Y-m-d H:i:s';
                $mdate = DateTime::createFromFormat(
                    $dformat,
                    date(
                        $dformat,
                        filemtime($cfile)
                    )
                );
                $expire = $mdate->add(
                    new DateInterval('PT' . $this->cache_timeout . 'H')
                );
                $now = new DateTime();
                $has_expired = $now > $expire;
                return !$has_expired;
            } catch (Throwable) {
                Analog::log(
                    'Unable check cache expiry. Are you sure you have '
                    . 'properly configured PHP timezone settings on your server?',
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
     */
    abstract protected function getCacheFilename(): string;

    /**
     * Ensure data to cache are present
     */
    abstract protected function prepareForCache(): void;

    /**
     * Creates/update the cache
     */
    protected function makeCache(): void
    {
        if ($this->nocache === true) {
            //for some reason, we do not want to use cache
            return;
        }
        $this->prepareForCache();
        $cfile = $this->getCacheFilename();
        $cdir = dirname((string)$cfile);
        if (!file_exists($cdir)) {
            mkdir($cdir, 0o755, true);
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
     */
    protected function getDataTocache(): string
    {
        throw new RuntimeException('Method not implemented');
    }

    /**
     * Loads entries from cache
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
     */
    abstract protected function cacheLoaded(mixed $content): bool;
}
