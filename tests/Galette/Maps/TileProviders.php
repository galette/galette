<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Maps;

use Galette\Tests\GaletteTestCase;
use Galette\Maps\TileProviders as Providers;

/**
 * Background map tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TileProviders extends GaletteTestCase
{
    protected int $seed = 20240517214956;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        foreach (array_keys(Providers::getSchema()) as $name) {
            $this->preferences->resetValue($name, $this->login);
        }
        parent::tearDown();
    }

    /**
     * Every provider describes itself the same way
     */
    public function testPresetsAreComplete(): void
    {
        foreach (Providers::getPresets() as $id => $preset) {
            foreach (['vector', 'url', 'attribution', 'maxzoom', 'subdomains'] as $key) {
                $this->assertArrayHasKey($key, $preset, $id . ' has no ' . $key);
            }
            $this->assertNotSame('', $preset['url'], $id . ' has no address');
            //a raster provider has to credit its source itself, a vector one
            //carries it in the style it serves
            if ($preset['vector'] === false) {
                $this->assertNotSame('', $preset['attribution'], $id . ' credits nobody');
            }
            $this->assertNotSame($id, Providers::getLabel($id), $id . ' has no name');
        }

        $this->assertArrayHasKey(Providers::DEFAULT, Providers::getPresets());
        $this->assertArrayHasKey(Providers::RASTER_FALLBACK, Providers::getPresets());
        $this->assertFalse(Providers::getPresets()[Providers::RASTER_FALLBACK]['vector']);
    }

    /**
     * Out of the box, the vector default applies
     */
    public function testDefault(): void
    {
        //without this the rest passes vacuously: an undeclared preference reads
        //as false, and resolve() then falls back to the default anyway
        $this->assertTrue(
            \Galette\Core\PreferencesSchema::has(Providers::PREF_PROVIDER),
            'background map preferences are not declared'
        );
        $this->assertNull(\Galette\Core\PreferencesSchema::getOwner(Providers::PREF_PROVIDER));

        $tiles = Providers::resolve($this->preferences);

        $this->assertSame(Providers::DEFAULT, $tiles['id']);
        $this->assertTrue($tiles['vector']);
        $this->assertFalse($tiles['fallback']['vector']);
    }

    /**
     * A preset applies as it is declared
     */
    public function testPreset(): void
    {
        $this->preferences->setValue(Providers::PREF_PROVIDER, 'osmfr', $this->login);

        $tiles = Providers::resolve($this->preferences);

        $this->assertSame('osmfr', $tiles['id']);
        $this->assertFalse($tiles['vector']);
        $this->assertSame('abc', $tiles['subdomains']);
        $this->assertSame(20, $tiles['maxzoom']);
    }

    /**
     * A provider that no longer exists falls back to the default
     *
     * Providers do get retired, and a map showing nothing would be worse than
     * a map showing something else.
     */
    public function testRetiredProvider(): void
    {
        $this->preferences->setValue(Providers::PREF_PROVIDER, 'cartodb-light', $this->login);

        $this->assertSame(Providers::DEFAULT, Providers::resolve($this->preferences)['id']);
    }

    /**
     * Own values apply
     */
    public function testOwnValues(): void
    {
        $this->preferences->setValue(Providers::PREF_PROVIDER, Providers::CUSTOM, $this->login);
        $this->preferences->setValue(
            Providers::PREF_VECTOR,
            value: false,
            login: $this->login
        );
        //the {s} token is why the address is stored as a string: Galette's URL
        //validation rejects a host that starts with a brace
        $this->assertTrue(
            $this->preferences->setValue(
                Providers::PREF_URL,
                'https://{s}.tile.example.org/{z}/{x}/{y}.png',
                $this->login
            ),
            print_r($this->preferences->getErrors(), return: true)
        );
        $this->preferences->setValue(Providers::PREF_SUBDOMAINS, 'abc', $this->login);
        $this->preferences->setValue(Providers::PREF_MAXZOOM, 17, $this->login);

        $tiles = Providers::resolve($this->preferences);

        $this->assertSame(Providers::CUSTOM, $tiles['id']);
        $this->assertFalse($tiles['vector']);
        $this->assertSame('https://{s}.tile.example.org/{z}/{x}/{y}.png', $tiles['url']);
        $this->assertSame('abc', $tiles['subdomains']);
        $this->assertSame(17, $tiles['maxzoom']);
    }

    /**
     * Own values without an address display nothing, so the default applies
     */
    public function testOwnValuesWithoutAddress(): void
    {
        $this->preferences->setValue(Providers::PREF_PROVIDER, Providers::CUSTOM, $this->login);

        $this->assertSame(Providers::DEFAULT, Providers::resolve($this->preferences)['id']);
    }
}
