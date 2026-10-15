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
 * Background maps members positions can be displayed on
 *
 * Tile URLs die: CARTO started watermarking its anonymous basemaps with "API
 * KEY REQUIRED" without changing a status code. Only an identifier is stored,
 * so retiring a provider here moves every instance to the default one, and
 * administrators can still set their own values.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class TileProviders
{
    public const string PREF_PROVIDER = 'pref_maps_tiles_provider';
    public const string PREF_VECTOR = 'pref_maps_tiles_vector';
    public const string PREF_URL = 'pref_maps_tiles_url';
    public const string PREF_ATTRIBUTION = 'pref_maps_tiles_attribution';
    public const string PREF_MAXZOOM = 'pref_maps_tiles_maxzoom';
    public const string PREF_SUBDOMAINS = 'pref_maps_tiles_subdomains';

    /** Own values rather than one of the presets */
    public const string CUSTOM = 'custom';
    public const string DEFAULT = 'openfreemap-positron';
    /** Served to browsers without WebGL 2, which cannot render vector tiles */
    public const string RASTER_FALLBACK = 'osm';

    private const string OSM_ATTRIBUTION
        = '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>';

    /**
     * Get every known provider
     *
     * A vector provider carries its attribution in the style it serves, so it
     * declares none here: the Leaflet control picks it up once the style loads.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getPresets(): array
    {
        return [
            'openfreemap-positron' => [
                'vector' => true,
                'url' => 'https://tiles.openfreemap.org/styles/positron',
                'attribution' => '',
                'maxzoom' => 20,
                'subdomains' => '',
            ],
            'openfreemap-liberty' => [
                'vector' => true,
                'url' => 'https://tiles.openfreemap.org/styles/liberty',
                'attribution' => '',
                'maxzoom' => 20,
                'subdomains' => '',
            ],
            'osm' => [
                'vector' => false,
                'url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                'attribution' => self::OSM_ATTRIBUTION,
                'maxzoom' => 19,
                'subdomains' => '',
            ],
            'osmfr' => [
                'vector' => false,
                'url' => 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png',
                'attribution' => self::OSM_ATTRIBUTION . ' &mdash; OSM-FR',
                'maxzoom' => 20,
                'subdomains' => 'abc',
            ],
            'hot' => [
                'vector' => false,
                'url' => 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
                'attribution' => self::OSM_ATTRIBUTION
                    . ' &mdash; <a href="https://www.hotosm.org/">HOT</a>',
                'maxzoom' => 20,
                'subdomains' => 'abc',
            ],
            'osmde' => [
                //stops at 18: beyond that the server answers a 404
                'vector' => false,
                'url' => 'https://tile.openstreetmap.de/{z}/{x}/{y}.png',
                'attribution' => self::OSM_ATTRIBUTION,
                'maxzoom' => 18,
                'subdomains' => '',
            ],
            'esri-gray' => [
                //note the unusual {z}/{y}/{x} order, and the JPEG tiles
                'vector' => false,
                'url' => 'https://services.arcgisonline.com/ArcGIS/rest/services/'
                    . 'Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
                'attribution' => 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ',
                'maxzoom' => 16,
                'subdomains' => '',
            ],
        ];
    }

    /**
     * Get the translated name of a provider
     *
     * Kept apart from the structural list so `_T()` only runs when a name is
     * actually displayed.
     *
     * @param string $id Provider identifier
     */
    public static function getLabel(string $id): string
    {
        return match ($id) {
            'openfreemap-positron' => _T('OpenFreeMap, light grey'),
            'openfreemap-liberty' => _T('OpenFreeMap, colours'),
            'osm' => _T('OpenStreetMap'),
            'osmfr' => _T('OpenStreetMap France'),
            'hot' => _T('Humanitarian OSM Team'),
            'osmde' => _T('OpenStreetMap Germany'),
            'esri-gray' => _T('Esri, light grey'),
            self::CUSTOM => _T('Your own values'),
            default => $id,
        };
    }

    /**
     * Get providers as the id => label map a select expects
     *
     * @return array<string, string>
     */
    public static function getSelectValues(): array
    {
        $values = [];
        foreach (array_keys(self::getPresets()) as $id) {
            $values[$id] = self::getLabel($id);
        }
        $values[self::CUSTOM] = self::getLabel(self::CUSTOM);

        return $values;
    }

    /**
     * Get the background map preferences
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSchema(): array
    {
        return [
            self::PREF_PROVIDER => [
                'type' => PreferencesSchema::TYPE_STRING,
                'default' => self::DEFAULT,
            ],
            self::PREF_VECTOR => [
                'type' => PreferencesSchema::TYPE_BOOL,
                'default' => true,
            ],
            //not TYPE_URL: its validation rejects the {s} subdomain token
            //several providers rely on
            self::PREF_URL => [
                'type' => PreferencesSchema::TYPE_STRING,
                'default' => '',
            ],
            self::PREF_ATTRIBUTION => [
                'type' => PreferencesSchema::TYPE_HTML,
                'default' => '',
            ],
            self::PREF_MAXZOOM => [
                'type' => PreferencesSchema::TYPE_INT,
                'default' => 19,
                'min' => 0,
                'max' => 22,
                'error' => PreferencesSchema::ERR_POSITIVE_NUMBER,
            ],
            self::PREF_SUBDOMAINS => [
                'type' => PreferencesSchema::TYPE_STRING,
                'default' => '',
            ],
        ];
    }

    /**
     * Get the background map to display
     *
     * An unknown identifier falls back to the default: providers get retired,
     * and a map showing nothing is worse than a map showing something else.
     *
     * @param Preferences $preferences Preferences instance
     *
     * @return array<string, mixed>
     */
    public static function resolve(Preferences $preferences): array
    {
        $id = (string)$preferences->pref_maps_tiles_provider;

        if ($id === self::CUSTOM) {
            $tiles = [
                'vector' => (bool)$preferences->pref_maps_tiles_vector,
                'url' => (string)$preferences->pref_maps_tiles_url,
                'attribution' => (string)$preferences->pref_maps_tiles_attribution,
                'maxzoom' => (int)$preferences->pref_maps_tiles_maxzoom,
                'subdomains' => (string)$preferences->pref_maps_tiles_subdomains,
            ];
            //an empty URL would display nothing at all
            if ($tiles['url'] !== '') {
                return $tiles + ['id' => self::CUSTOM, 'fallback' => self::rasterFallback()];
            }
            $id = self::DEFAULT;
        }

        $tiles = self::getPresets()[$id] ?? self::getPresets()[self::DEFAULT];
        $tiles['id'] = isset(self::getPresets()[$id]) ? $id : self::DEFAULT;
        $tiles['fallback'] = self::rasterFallback();

        return $tiles;
    }

    /**
     * Get the raster provider to use when the browser cannot render vector tiles
     *
     * @return array<string, mixed>
     */
    private static function rasterFallback(): array
    {
        return self::getPresets()[self::RASTER_FALLBACK];
    }
}
