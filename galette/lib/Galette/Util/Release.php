<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Util;

use Analog\Analog;
use Galette\Core\Logs;
use Galette\Core\Plugins;
use Galette\Features\Cacheable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\ResponseInterface;

use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\json_encode;

/**
 * Check for new Galette and plugins releases, from GitHub releases
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @phpstan-type ReleaseInfo array{status: string, version?: string, url?: string}
 */
class Release
{
    use Cacheable;

    public const string CORE_REPOSITORY = 'galette/galette';

    public const string STATUS_FOUND = 'found';
    public const string STATUS_NOT_FOUND = 'not_found';
    public const string STATUS_ERROR = 'error';

    //number of hours until a check that failed is done again
    private const int RETRY_TIMEOUT = 1;

    protected string $cache_filename = 'releases.cache';

    /** @var array<string, mixed> */
    private array $default_options = [
        'timeout' => 2.0,
        'connect_timeout' => 1.0,
        'verify' => false,
        'http_errors' => true,
        'headers' => [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28'
        ]
    ];
    /** @var ?array<string, ReleaseInfo> */
    private ?array $releases = null;

    /**
     * Constructor
     *
     * @param bool     $nocache Do not try to cache
     * @param ?Plugins $plugins Plugins to check for new releases
     */
    public function __construct(bool $nocache = false, private ?Plugins $plugins = null)
    {
        //a check that failed is cached as well, so that an unreachable or
        //rate limited API is not asked again on every page; but it is
        //retried well before a check that did succeed.
        if ($this->hasCachedErrors()) {
            $this->cache_timeout = self::RETRY_TIMEOUT;
        }

        //only if cache should be used
        $this->handleCache($nocache);
    }

    /**
     * Handle cache
     *
     * Unlike other cached contents, releases are cached in debug mode as well:
     * GitHub API rate limits anonymous requests to 60 per hour, a few pages
     * would be enough to reach it.
     *
     * @param bool $nocache Do not try to cache
     */
    protected function handleCache(bool $nocache = false): void
    {
        if ($nocache === true) {
            return;
        }

        try {
            if (!$this->checkCache()) {
                $this->makeCache();
            } else {
                $this->loadCache();
            }
        } catch (\Throwable $e) {
            //releases check must never prevent a page to be displayed
            Logs::exception($e, 'Unable to use releases cache', Analog::WARNING);
        }
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
        $options = $this->default_options;
        //GitHub API rejects requests without user agent
        $options['headers']['User-Agent'] = 'Galette/' . ltrim($this->getCurrentRelease(), 'v');
        return $options;
    }

    /**
     * Get repositories to check
     *
     * @return array<string, string> repository => plugin id, or empty string for Galette itself
     */
    public function getRepositories(): array
    {
        $repositories = [self::CORE_REPOSITORY => ''];
        if ($this->plugins !== null) {
            foreach ($this->plugins->getModules() as $id => $module) {
                if (isset($module['repository'])) {
                    $repositories[$module['repository']] ??= $id;
                }
            }
        }
        return $repositories;
    }

    /**
     * Get repositories to check, sorted to be compared with cached ones
     *
     * @return array<string>
     */
    private function getSortedRepositories(): array
    {
        $repositories = array_keys($this->getRepositories());
        sort($repositories);
        return $repositories;
    }

    /**
     * Get the latest releases of all repositories
     *
     * @return array<string, ReleaseInfo>
     */
    public function getReleases(): array
    {
        $this->releases ??= $this->findLatestReleases(array_keys($this->getRepositories()));
        return $this->releases;
    }

    /**
     * Query GitHub for the latest releases of repositories, all at once
     *
     * @param array<string> $repositories Repositories, as owner/name
     *
     * @return array<string, ReleaseInfo>
     */
    public function findLatestReleases(array $repositories): array
    {
        $releases = [];
        try {
            $client = $this->setupClient();
            $promises = [];
            foreach ($repositories as $repository) {
                $promises[$repository] = $client->getAsync(
                    $this->getReleasesURL() . $repository . '/releases/latest'
                );
            }
            $results = Utils::settle($promises)->wait();
        } catch (\Throwable $e) {
            Logs::exception($e, 'Error while trying to get latest releases', Analog::ERROR);
            return array_fill_keys($repositories, ['status' => self::STATUS_ERROR]);
        }

        //keep requested order, results are sorted
        foreach ($repositories as $repository) {
            $result = $results[$repository];
            if ($result['state'] === 'fulfilled') {
                $releases[$repository] = $this->parseRelease($repository, $result['value']);
                continue;
            }

            $reason = $result['reason'];
            if ($reason instanceof ClientException && $reason->getResponse()->getStatusCode() === 404) {
                //no such repository, or no published release
                $this->logNotFound($repository);
                $releases[$repository] = ['status' => self::STATUS_NOT_FOUND];
                continue;
            }

            if ($reason instanceof \Throwable) {
                Logs::exception(
                    $reason,
                    sprintf('Error while trying to get latest release of %1$s', $repository),
                    Analog::ERROR
                );
            }
            $releases[$repository] = ['status' => self::STATUS_ERROR];
        }

        return $releases;
    }

    /**
     * Parse GitHub latest release response
     *
     * @param string            $repository Repository
     * @param ResponseInterface $response   GitHub response
     *
     * @return ReleaseInfo
     */
    private function parseRelease(string $repository, ResponseInterface $response): array
    {
        try {
            $release = json_decode($response->getBody()->getContents(), associative: true);
            if (!is_array($release) || !isset($release['tag_name']) || !is_string($release['tag_name'])) {
                throw new \UnexpectedValueException('Missing tag name');
            }
            return [
                'status' => self::STATUS_FOUND,
                'version' => ltrim($release['tag_name'], 'v'),
                'url' => is_string($release['html_url'] ?? null) ? $release['html_url'] : ''
            ];
        } catch (\Throwable $e) {
            Logs::exception(
                $e,
                sprintf('Unable to read latest release of %1$s', $repository),
                Analog::ERROR
            );
            return ['status' => self::STATUS_ERROR];
        }
    }

    /**
     * Log a repository that has no release
     *
     * @param string $repository Repository
     */
    private function logNotFound(string $repository): void
    {
        $plugin_id = $this->getRepositories()[$repository] ?? '';
        if ($plugin_id === '') {
            Analog::log(
                sprintf('No release found in %1$s repository.', $repository),
                Analog::WARNING
            );
            return;
        }

        Analog::log(
            sprintf(
                'Unable to check for new releases of plugin "%1$s": no release found in %2$s repository. '
                . 'If the plugin is published elsewhere, its "repository" must be declared in its plugin definition.',
                $plugin_id,
                $repository
            ),
            Analog::WARNING
        );
    }

    /**
     * Get the latest release
     */
    public function getLatestRelease(): ?string
    {
        return $this->getReleases()[self::CORE_REPOSITORY]['version'] ?? null;
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

        $latest = $this->getLatestRelease();
        if ($latest === null) {
            return false;
        }

        return version_compare($latest, ltrim($current, 'v'), '>');
    }

    /**
     * Get plugins for which a new release is available
     *
     * @return array<string, array{name: string, current: string, latest: string, url: string}>
     */
    public function getPluginsUpdates(): array
    {
        $updates = [];
        if ($this->plugins === null) {
            return $updates;
        }

        $releases = $this->getReleases();
        foreach ($this->plugins->getModules() as $id => $module) {
            $release = $releases[$module['repository'] ?? ''] ?? null;
            if ($release === null || !isset($release['version'])) {
                continue;
            }

            $current = ltrim($module['version'], 'v');
            if ($current === '' || str_ends_with($current, '-dev')) {
                continue;
            }

            if (version_compare($release['version'], $current, '>')) {
                $updates[$id] = [
                    'name' => $module['name'],
                    'current' => $module['version'],
                    'latest' => $release['version'],
                    'url' => $release['url'] ?? ''
                ];
            }
        }

        return $updates;
    }

    /**
     * Get plugins releases check status
     *
     * @return array<string, array{repository: string, status: string}>
     */
    public function getPluginsReleasesStatus(): array
    {
        $status = [];
        if ($this->plugins === null) {
            return $status;
        }

        $releases = $this->getReleases();
        foreach ($this->plugins->getModules() as $id => $module) {
            if (!isset($module['repository'])) {
                continue;
            }
            $status[$id] = [
                'repository' => $module['repository'],
                'status' => $releases[$module['repository']]['status'] ?? self::STATUS_ERROR
            ];
        }

        return $status;
    }

    /**
     * Get the current release
     */
    public function getCurrentRelease(): string
    {
        return GALETTE_VERSION;
    }

    /**
     * Get the URL of releases API
     */
    public function getReleasesURL(): string
    {
        return GALETTE_RELEASES_API_URI;
    }

    /**
     * Get data to cache
     */
    protected function getDataTocache(): string
    {
        return json_encode([
            'repositories' => $this->getSortedRepositories(),
            'releases' => $this->releases ?? []
        ]);
    }

    /**
     * Called once cache has been loaded.
     *
     * @param mixed $content Content from cache
     */
    protected function cacheLoaded(mixed $content): bool
    {
        if (!is_string($content)) {
            return false;
        }

        try {
            $cached = json_decode($content, associative: true);
        } catch (\Throwable) {
            // no log: an unreadable cache is just built again
            return false;
        }

        if (
            !is_array($cached)
            || !isset($cached['repositories'], $cached['releases'])
            || !is_array($cached['releases'])
            //a plugin has been added or removed since last check
            || $cached['repositories'] !== $this->getSortedRepositories()
        ) {
            return false;
        }

        /** @var array<string, ReleaseInfo> $releases */
        $releases = $cached['releases'];
        $this->releases = $releases;
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
        $this->getReleases();
    }

    /**
     * Whether the cached check has failed for at least one repository
     */
    private function hasCachedErrors(): bool
    {
        $cfile = $this->getCacheFilename();
        if (!file_exists($cfile)) {
            return false;
        }

        try {
            return str_contains(file_get_contents($cfile), '"status":"' . self::STATUS_ERROR . '"');
        } catch (\Throwable) {
            // no log: an unreadable cache is not a failed check, it is built again when loaded
            return false;
        }
    }
}
