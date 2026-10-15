<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Maps;

use Galette\Tests\GaletteTestCase;
use Galette\Maps\Precision as MapsPrecision;

/**
 * Positions precision tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Precision extends GaletteTestCase
{
    /**
     * Coordinates are snapped to the grid of the precision
     */
    public function testSnap(): void
    {
        $this->assertSame('50.36', MapsPrecision::snap('50.362038', 0.01));
        $this->assertSame('3.47', MapsPrecision::snap(3.472998, 0.01));
        $this->assertSame('50.4', MapsPrecision::snap('50.362038', 0.1));
        $this->assertSame('50.362', MapsPrecision::snap('50.362038', 0.001));
        $this->assertSame('50', MapsPrecision::snap('50.362038', 1.0));
        //2 degrees steps: 50.36 is closer to 50 than to 52
        $this->assertSame('50', MapsPrecision::snap('50.362038', 2.0));
        $this->assertSame('52', MapsPrecision::snap('51.1', 2.0));
        $this->assertSame('-0.78', MapsPrecision::snap('-0.780029', 0.01));
        //no negative zero
        $this->assertSame('0.00', MapsPrecision::snap('-0.001', 0.01));
        $this->assertSame('0', MapsPrecision::snap('-0.4', 1.0));
    }

    /**
     * Every precision offered has a step and a zoom, except exact position
     */
    public function testChoices(): void
    {
        $choices = array_keys(MapsPrecision::getSelectValues());
        $this->assertSame(['100km', '50km', '5km', '500m', '50m', MapsPrecision::EXACT], $choices);
        foreach ($choices as $precision) {
            $this->assertTrue(MapsPrecision::isKnown($precision));
            if ($precision === MapsPrecision::EXACT) {
                $this->assertNull(MapsPrecision::getStep($precision));
                $this->assertNull(MapsPrecision::getMaxZoom($precision));
            } else {
                $this->assertGreaterThan(0, MapsPrecision::getStep($precision));
                $this->assertGreaterThan(0, MapsPrecision::getMaxZoom($precision));
            }
        }
        $this->assertFalse(MapsPrecision::isKnown('1km'));
    }

    /**
     * Default precision, and fallback for an unknown stored value
     */
    public function testResolve(): void
    {
        $this->assertSame(MapsPrecision::DEFAULT, MapsPrecision::resolve($this->preferences));

        $this->assertTrue($this->preferences->setValue(MapsPrecision::PREF, 'retired', $this->login));
        $this->assertSame(MapsPrecision::DEFAULT, MapsPrecision::resolve($this->preferences));

        $this->assertTrue($this->preferences->setValue(MapsPrecision::PREF, MapsPrecision::EXACT, $this->login));
        $this->assertSame(MapsPrecision::EXACT, MapsPrecision::resolve($this->preferences));
        $this->assertTrue($this->preferences->setValue(MapsPrecision::PREF, MapsPrecision::DEFAULT, $this->login));
    }
}
