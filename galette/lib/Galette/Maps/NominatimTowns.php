<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Maps;

use Analog\Analog;
use Galette\Core\Logs;
use Galette\Core\Preferences;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\filemtime;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\mkdir;

/**
 * Towns GPS coordinates via nominatim
 *
 * Answers are kept a few days: a member page searching for the same town
 * again does not query the service, its usage policy asks for few requests.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class NominatimTowns
{
    private const string URI = 'https://nominatim.openstreetmap.org/search';
    /** How long answers are kept, in seconds */
    public const int CACHE_TTL = 7 * 24 * 3600;

    private readonly ClientInterface $client;
    private readonly string $cache_dir;

    /**
     * Constructor
     *
     * @param Preferences      $preferences Preferences instance
     * @param ?ClientInterface $client      HTTP client, a default one is built if null
     * @param ?string          $cache_dir   Where answers are kept: null for Galette cache, empty not to keep them
     */
    public function __construct(
        private readonly Preferences $preferences,
        ?ClientInterface $client = null,
        ?string $cache_dir = null
    ) {
        //a slow answer must not hold the page
        $this->client = $client ?? new Client(['timeout' => 5.0, 'connect_timeout' => 2.0]);
        $this->cache_dir = $cache_dir ?? rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'nominatim';
    }

    /**
     * Search a town by its name
     *
     * @param string  $town    Town name
     * @param ?string $country Country name (optional)
     *
     * @return array<int, array<string, string>>
     *
     * @throws \RuntimeException when the service cannot be queried
     */
    public function search(string $town, ?string $country = null): array
    {
        if (trim($town) === '') {
            return [];
        }

        $cache_file = $this->getCacheFile($town, $country);
        $cached = $this->readCache($cache_file);
        if ($cached !== null) {
            return $cached;
        }

        $query = [
            'format'            => 'jsonv2',
            'addressdetails'    => '1',
            'city'              => $town
        ];
        if ($country !== null && trim($country) !== '') {
            $query['country'] = $country;
        }

        try {
            $response = $this->client->request(
                'GET',
                self::URI,
                [
                    'query' => $query,
                    'headers' => [
                        //usage policy requires to identify the application
                        'User-Agent' => sprintf(
                            'Galette (%s; %s)',
                            $this->preferences->pref_nom,
                            $this->preferences->getURL()
                        )
                    ]
                ]
            );
            $places = json_decode((string)$response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException | \JsonException $e) {
            throw new \RuntimeException('Error on nominatim request for "' . $town . '": ' . $e->getMessage(), $e->getCode(), previous: $e);
        }

        if (!is_array($places)) {
            throw new \RuntimeException('Unexpected nominatim answer for "' . $town . '"');
        }

        $results = [];
        foreach ($places as $place) {
            $address = $place['address'] ?? [];
            //settlement labels, from the largest to the smallest
            $full_name = $address['city'] ?? $address['town'] ?? $address['municipality']
                ?? $address['village'] ?? $address['hamlet'] ?? null;
            if ($full_name === null) {
                Analog::log(
                    'Nominatim result "' . ($place['display_name'] ?? '') . '" is not a town',
                    Analog::INFO
                );
                continue;
            }

            $latitude = (string)$place['lat'];
            $longitude = (string)$place['lon'];
            foreach ($results as $elt) {
                if ($elt['latitude'] === $latitude && $elt['longitude'] === $longitude) {
                    Analog::log('Town is already in list, ignore.', Analog::INFO);
                    continue 2;
                }
            }

            $results[] = [
                'full_name' => (string)$full_name,
                'latitude'  => $latitude,
                'longitude' => $longitude
            ];
        }

        $this->writeCache($cache_file, $results);
        return $results;
    }

    /**
     * Get the file a search answer is kept in
     *
     * @param string  $town    Town name
     * @param ?string $country Country name
     *
     * @return ?string null when answers are not kept
     */
    private function getCacheFile(string $town, ?string $country): ?string
    {
        if ($this->cache_dir === '') {
            return null;
        }
        $key = mb_strtolower(trim($town)) . "\n" . mb_strtolower(trim($country ?? ''));
        return $this->cache_dir . DIRECTORY_SEPARATOR . hash('xxh128', $key) . '.json';
    }

    /**
     * Read a kept answer
     *
     * @param ?string $file Cache file
     *
     * @return ?array<int, array<string, string>> null when there is none, or it is too old
     */
    private function readCache(?string $file): ?array
    {
        if ($file === null || !file_exists($file) || filemtime($file) < time() - self::CACHE_TTL) {
            return null;
        }
        try {
            $results = json_decode(file_get_contents($file), associative: true);
        } catch (Throwable $e) {
            //unreadable: searched again, then written over
            Logs::exception($e, 'Unable to read kept nominatim answer', Analog::INFO);
            return null;
        }
        return is_array($results) ? $results : null;
    }

    /**
     * Keep an answer
     *
     * @param ?string                           $file    Cache file
     * @param array<int, array<string, string>> $results Towns found
     */
    private function writeCache(?string $file, array $results): void
    {
        if ($file === null) {
            return;
        }
        try {
            if (!is_dir($this->cache_dir)) {
                mkdir($this->cache_dir, 0o755, recursive: true);
            }
            file_put_contents($file, json_encode($results));
        } catch (Throwable $e) {
            //searching again next time is all it costs
            Logs::exception($e, 'Unable to keep nominatim answer', Analog::WARNING);
        }
    }
}
