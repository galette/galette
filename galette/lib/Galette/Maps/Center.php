<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Maps;

use Galette\Core\Preferences;
use Galette\Core\PreferencesSchema;

/**
 * Where maps open when they have nothing else to show
 *
 * Administrators set it once, usually from the association town; without
 * it, maps show the whole of France.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Center
{
    public const string PREF_LATITUDE = 'pref_maps_center_latitude';
    public const string PREF_LONGITUDE = 'pref_maps_center_longitude';

    /** Zoom on the association town */
    public const int ZOOM = 10;

    private const array DEFAULT = [
        'lat' => 46.830133640447386,
        'lng' => 2.4609375,
        'zoom' => 6,
    ];

    /**
     * Get preferences schema entries
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSchema(): array
    {
        return [
            self::PREF_LATITUDE => [
                'type' => PreferencesSchema::TYPE_STRING,
                'default' => '',
            ],
            self::PREF_LONGITUDE => [
                'type' => PreferencesSchema::TYPE_STRING,
                'default' => '',
            ],
        ];
    }

    /**
     * Check a position, and give it as stored
     *
     * Both coordinates are set, or none of them.
     *
     * @param string $latitude  Latitude
     * @param string $longitude Longitude
     *
     * @return array{string, string}|null Normalized coordinates, empty strings to unset them; null when invalid
     */
    public static function normalize(string $latitude, string $longitude): ?array
    {
        $latitude = trim($latitude);
        $longitude = trim($longitude);
        if ($latitude === '' && $longitude === '') {
            return ['', ''];
        }

        $lat = filter_var($latitude, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($longitude, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }

        return [self::format($lat), self::format($lng)];
    }

    /**
     * Get the center maps open on
     *
     * @param Preferences $preferences Preferences instance
     *
     * @return array{lat: float, lng: float, zoom: int}
     */
    public static function resolve(Preferences $preferences): array
    {
        $position = self::normalize(
            (string)$preferences->{self::PREF_LATITUDE},
            (string)$preferences->{self::PREF_LONGITUDE}
        );
        if ($position === null || $position[0] === '') {
            return self::getDefault();
        }

        return [
            'lat' => (float)$position[0],
            'lng' => (float)$position[1],
            'zoom' => self::ZOOM,
        ];
    }

    /**
     * Get the center maps open on, when none is set
     *
     * @return array{lat: float, lng: float, zoom: int}
     */
    public static function getDefault(): array
    {
        return self::DEFAULT;
    }

    /**
     * Format a coordinate with the precision members positions are stored with
     *
     * @param float $value Coordinate
     */
    private static function format(float $value): string
    {
        return number_format(num: $value, decimals: 6, decimal_separator: '.', thousands_separator: '');
    }
}
