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
 * Precision of members positions on the map
 *
 * Positions are snapped to a grid; the displayed marker is at most half a
 * step away from the stored position. Staff, administrators and members
 * themselves always get the exact position.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Precision
{
    public const string PREF = 'pref_maps_public_precision';

    public const string EXACT = 'exact';
    public const string DEFAULT = '500m';

    /** Grid step, in degrees */
    private const array STEPS = [
        '100km' => 2.0,
        '50km' => 1.0,
        '5km' => 0.1,
        '500m' => 0.01,
        '50m' => 0.001,
    ];

    /** Zoom beyond which a snapped position would suggest a precision it has not */
    private const array MAX_ZOOMS = [
        '100km' => 7,
        '50km' => 8,
        '5km' => 11,
        '500m' => 14,
        '50m' => 17,
    ];

    /**
     * Get the preference the precision is stored in
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSchema(): array
    {
        return [
            self::PREF => [
                'type' => PreferencesSchema::TYPE_STRING,
                'default' => self::DEFAULT,
            ],
        ];
    }

    /**
     * Is given precision known?
     *
     * @param string $precision Precision
     */
    public static function isKnown(string $precision): bool
    {
        return $precision === self::EXACT || isset(self::STEPS[$precision]);
    }

    /**
     * Get the configured precision; an unknown value falls back to the default
     *
     * @param Preferences $preferences Preferences instance
     */
    public static function resolve(Preferences $preferences): string
    {
        $precision = (string)$preferences->pref_maps_public_precision;
        return self::isKnown($precision) ? $precision : self::DEFAULT;
    }

    /**
     * Get the grid step of a precision, null for the exact position
     *
     * @param string $precision Precision
     */
    public static function getStep(string $precision): ?float
    {
        return self::STEPS[$precision] ?? null;
    }

    /**
     * Get the maximum zoom of a map showing positions with given precision
     *
     * @param string $precision Precision
     */
    public static function getMaxZoom(string $precision): ?int
    {
        return self::MAX_ZOOMS[$precision] ?? null;
    }

    /**
     * Snap a coordinate to the grid
     *
     * @param string|float $value Coordinate
     * @param float        $step  Grid step, in degrees
     */
    public static function snap(string|float $value, float $step): string
    {
        $decimals = max(0, (int)-floor(log10($step)));
        $snapped = round((float)$value / $step) * $step;
        //avoid a "-0.00" when rounding a small negative value
        if ($snapped == 0) {
            $snapped = 0.0;
        }
        return number_format($snapped, $decimals, '.', '');
    }

    /**
     * Get precisions as the id => label map a select expects, finest last
     *
     * @return array<string, string>
     */
    public static function getSelectValues(): array
    {
        return [
            '100km' => _T('About 100 km'),
            '50km' => _T('About 50 km'),
            '5km' => _T('About 5 km'),
            '500m' => _T('About 500 m'),
            '50m' => _T('About 50 m'),
            self::EXACT => _T('Exact position'),
        ];
    }
}
