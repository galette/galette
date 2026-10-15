<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Maps;

use Analog\Analog;
use Galette\Core\Preferences;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Towns GPS coordinates via nominatim
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class NominatimTowns
{
    private const string URI = 'https://nominatim.openstreetmap.org/search';

    private Preferences $preferences;
    private ClientInterface $client;

    /**
     * Constructor
     *
     * @param Preferences      $preferences Preferences instance
     * @param ?ClientInterface $client      HTTP client, a default one is built if null
     */
    public function __construct(Preferences $preferences, ?ClientInterface $client = null)
    {
        $this->preferences = $preferences;
        //a slow answer must not hold the page
        $this->client = $client ?? new Client(['timeout' => 5.0, 'connect_timeout' => 2.0]);
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
            $places = json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException | \JsonException $e) {
            throw new \RuntimeException(
                'Error on nominatim request for "' . $town . '": ' . $e->getMessage(),
                previous: $e
            );
        }

        if (!is_array($places)) {
            throw new \RuntimeException('Unexpected nominatim answer for "' . $town . '"');
        }

        $results = [];
        foreach ($places as $place) {
            $address = $place['address'] ?? [];
            $full_name = $address['city'] ?? $address['town'] ?? $address['village'] ?? null;
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

        return $results;
    }
}
