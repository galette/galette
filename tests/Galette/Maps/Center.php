<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Maps;

use Galette\Tests\GaletteTestCase;
use Galette\Maps\Center as MapsCenter;

/**
 * Maps center tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Center extends GaletteTestCase
{
    protected int $seed = 20261112214512;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        foreach (array_keys(MapsCenter::getSchema()) as $name) {
            $this->preferences->resetValue($name, $this->login);
        }
        parent::tearDown();
    }

    /**
     * Both coordinates are set, in their range, or none of them
     */
    public function testNormalize(): void
    {
        $this->assertSame(['', ''], MapsCenter::normalize('', ' '));
        $this->assertSame(['44.841225', '-0.580036'], MapsCenter::normalize(' 44.8412250', '-0.5800364 '));
        $this->assertSame(['-90.000000', '180.000000'], MapsCenter::normalize('-90', '180'));

        $this->assertNull(MapsCenter::normalize('44.84', ''));
        $this->assertNull(MapsCenter::normalize('', '-0.58'));
        $this->assertNull(MapsCenter::normalize('90.1', '0'));
        $this->assertNull(MapsCenter::normalize('0', '-180.5'));
        $this->assertNull(MapsCenter::normalize('north', '0'));
        $this->assertNull(MapsCenter::normalize('44,84', '0'));
    }

    /**
     * Maps open on the set center, or on the default one
     */
    public function testResolve(): void
    {
        //without this the rest passes vacuously: an undeclared preference reads as false
        $this->assertTrue(\Galette\Core\PreferencesSchema::has(MapsCenter::PREF_LATITUDE));
        $this->assertTrue(\Galette\Core\PreferencesSchema::has(MapsCenter::PREF_LONGITUDE));

        $default = MapsCenter::getDefault();
        $this->assertSame($default, MapsCenter::resolve($this->preferences));

        $this->assertTrue($this->preferences->setValue(MapsCenter::PREF_LATITUDE, '44.841225', $this->login));
        $this->assertTrue($this->preferences->setValue(MapsCenter::PREF_LONGITUDE, '-0.580036', $this->login));
        $this->assertSame(
            ['lat' => 44.841225, 'lng' => -0.580036, 'zoom' => MapsCenter::ZOOM],
            MapsCenter::resolve($this->preferences)
        );

        //a broken stored value is ignored
        $this->assertTrue($this->preferences->setValue(MapsCenter::PREF_LONGITUDE, '', $this->login));
        $this->assertSame($default, MapsCenter::resolve($this->preferences));
    }
}
