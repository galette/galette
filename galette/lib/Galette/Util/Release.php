<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Util;

use Analog\Analog;
use Galette\Features\Cacheable;
use GuzzleHttp\Client;

use function Safe\preg_match_all;

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
        'connect_timeout' => 1.0,
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
     */
    public function findLatestRelease(): ?string
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
                if (version_compare($release, (string)($latest ?? 0), '>')) {
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
     */
    public function getCurrentRelease(): string
    {
        return GALETTE_VERSION;
    }

    /**
     * Get the URL to download releases
     */
    public function getReleasesURL(): string
    {
        return GALETTE_DOWNLOADS_URI;
    }

    /**
     * Get data to cache
     */
    protected function getDataTocache(): string
    {
        return $this->latest ?? '';
    }

    /**
     * Called once cache has been loaded.
     *
     * @param mixed $content Content from cache
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
     */
    protected function getCacheFilename(): string
    {
        return GALETTE_CACHE_DIR . $this->cache_filename;
    }

    /**
     * Ensure data to cache are present
     */
    protected function prepareForCache(): void
    {
        if (!isset($this->latest)) {
            $this->latest = $this->findLatestRelease();
        }
    }
}
