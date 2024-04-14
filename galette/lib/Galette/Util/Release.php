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

namespace Galette\Util;

use Analog\Analog;
use Galette\Features\Cacheable;
use GuzzleHttp\Client;

/**
 * Check for new Galette release
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Release
{
    use Cacheable;

    protected string $cache_filename = 'newrelease.cache';

    /** @var array<string, mixed> */
    private array $default_options = [
        'timeout' => 2.0,
        'verify' => false
    ];
    private ?string $latest = null;

    /**
     * Constructor
     * @param bool $nocache Do not try to cache
     */
    public function __construct(bool $nocache = false)
    {
        //only if cache should be used
        $this->handleCache($nocache);
    }

    /**
     * Set ups Guzzle client
     *
     * @return Client
     */
    public function setupClient(): Client
    {
        return new Client(
            $this->getDefaultOptions()
        );
    }

    /**
     * Get default options
     *
     * @return array<string, mixed>
     */
    public function getDefaultOptions(): array
    {
        return $this->default_options;
    }

    /**
     * Get the latest release
     *
     * @return ?string
     */
    public function getLatestRelease(): ?string
    {
        if (!isset($this->latest)) {
            $this->latest = $this->findLatestRelease();
        }
        if ($this->latest === null) {
            //disable caching, no version has been found
            $this->nocache = true;
        }
        return $this->latest;
    }

    /**
     * Get the latest release
     *
     *  @param bool $nocache Do not try to cache
     *
     * @return ?string
     */
    public function findLatestRelease(bool $nocache = false): ?string
    {
        if (isset($this->latest)) {
            return $this->getLatestRelease();
        }

        try {
            $client = $this->setupClient();
            $response = $client->request('GET', $this->getReleasesURL());
            $contents = $response->getBody()->getContents();

            $releases = [];
            preg_match_all(
                '/href="(galette-.[^"]+\.tar\.bz2)"/',
                $contents,
                $releases
            );

            $latest = null;
            foreach ($releases[1] as $release) {
                $release = str_replace('galette-', '', $release);
                $release = str_replace('.tar.bz2', '', $release);
                if ($release === 'dev') {
                    continue;
                }
                if (version_compare($release, $latest ?? 0, '>')) {
                    $latest = $release;
                }
            }

            return $latest;
        } catch (\Throwable $e) {
            Analog::log(
                'Error while trying to get latest release: ' . $e->getMessage(),
                Analog::ERROR
            );
            return null;
        }
    }

    /**
     * Check if a new release is available
     *
     * @return bool
     */
    public function checkNewRelease(): bool
    {
        $current = $this->getCurrentRelease();
        if (str_ends_with($current, '-dev')) {
            //current version is a dev version
            return false;
        }

        $this->latest = $this->getLatestRelease();
        if ($this->latest === null) {
            return false;
        }

        return version_compare($this->latest, ltrim($this->getCurrentRelease(), 'v'), '>');
    }

    /**
     * Get the current release
     *
     * @return string
     */
    public function getCurrentRelease(): string
    {
        return GALETTE_VERSION;
    }

    /**
     * Get the URL to download releases
     *
     * @return string
     */
    public function getReleasesURL(): string
    {
        return GALETTE_DOWNLOADS_URI;
    }

    /**
     * Get data to cache
     *
     * @return string
     */
    protected function getDataTocache(): string
    {
        return $this->latest;
    }

    /**
     * Called once cache has been loaded.
     *
     * @param mixed $content Content from cache
     *
     * @return bool
     */
    protected function cacheLoaded(mixed $content): bool
    {
        if ($content === null) {
            return false;
        }

        $this->latest = $content;
        return true;
    }

    /**
     * Complete path to cache file
     *
     * @return string
     */
    protected function getCacheFilename(): string
    {
        return GALETTE_CACHE_DIR . $this->cache_filename;
    }

    /**
     * Ensure data to cache are present
     *
     * @return void
     */
    protected function prepareForCache(): void
    {
        if (!isset($this->latest)) {
            $this->latest = $this->findLatestRelease();
        }
    }
}
