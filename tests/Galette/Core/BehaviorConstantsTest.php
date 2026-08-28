<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\BehaviorConstants;
use Galette\Core\PreferencesSchema;
use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Safe\define;
use function Safe\file_get_contents;

/**
 * Behavior constants tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class BehaviorConstantsTest extends GaletteTestCase
{
    /**
     * Every entry is described, and reports whether it is set
     */
    public function testStatus(): void
    {
        $status = BehaviorConstants::getStatus();
        $this->assertNotEmpty($status);

        foreach ($status as $constant) {
            $this->assertStringStartsWith('GALETTE_', $constant['name']);
            $this->assertNotEmpty($constant['description'], $constant['name'] . ' has no description');
            $this->assertSame(defined($constant['name']), $constant['defined']);
            $this->assertArrayHasKey('replaced_by', $constant);

            if (!$constant['defined']) {
                $this->assertSame('', $constant['value']);
            }
        }

        $names = array_column($status, 'name');
        //always defined, so its value is always rendered
        $this->assertContains('GALETTE_MODE', $names);
        $this->assertSame(
            GALETTE_MODE,
            $status[array_search('GALETTE_MODE', $names, true)]['value']
        );
    }

    /**
     * A superseded constant only shows up while it is declared
     *
     * Undefined, the setting replacing it is the only thing that applies, and
     * it is already listed above with its value.
     */
    public function testSupersededConstantsShowOnlyWhenDeclared(): void
    {
        $superseded = PreferencesSchema::getConstants();
        $this->assertNotEmpty($superseded);

        $listed = array_column(BehaviorConstants::getStatus(), null, 'name');

        foreach ($superseded as $preference => $constant) {
            if (defined($constant)) {
                $this->assertArrayHasKey($constant, $listed, $constant . ' is declared but not listed');
                $this->assertSame($preference, $listed[$constant]['replaced_by']);
                $this->assertStringContainsString($preference, $listed[$constant]['description']);
                continue;
            }

            $this->assertArrayNotHasKey(
                $constant,
                $listed,
                $constant . ' is not declared and has nothing to say'
            );
        }

        //the ones with no replacement are never flagged
        foreach ($listed as $constant) {
            if (in_array($constant['name'], $superseded, true)) {
                continue;
            }
            $this->assertNull($constant['replaced_by'], $constant['name'] . ' has no replacement');
        }
    }

    /**
     * Declared, it is listed with the value it forces
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDeclaredSupersededConstantShowsItsValue(): void
    {
        $this->assertFalse(defined('GALETTE_URI'));
        $this->assertArrayNotHasKey('GALETTE_URI', array_column(BehaviorConstants::getStatus(), null, 'name'));

        define('GALETTE_URI', 'https://from-the-file.example.com');

        $listed = array_column(BehaviorConstants::getStatus(), null, 'name');
        $this->assertArrayHasKey('GALETTE_URI', $listed);
        $this->assertTrue($listed['GALETTE_URI']['defined']);
        $this->assertSame('https://from-the-file.example.com', $listed['GALETTE_URI']['value']);
        $this->assertSame('pref_galette_url', $listed['GALETTE_URI']['replaced_by']);
    }

    /**
     * Every constant Galette reads is documented in the shipped example
     *
     * Covers the deprecated ones too, which the page only lists once declared
     * but the file still accepts.
     */
    public function testConstantsAreDocumented(): void
    {
        //not GALETTE_CONFIG_PATH, which points at the test configuration
        $dist = file_get_contents(GALETTE_ROOT . 'config/behavior.inc.php.dist');

        $names = array_merge(
            array_column(BehaviorConstants::getStatus(), 'name'),
            array_values(PreferencesSchema::getConstants())
        );

        foreach (array_unique($names) as $name) {
            $this->assertStringContainsString(
                "define('" . $name . "'",
                $dist,
                $name . ' is not documented in behavior.inc.php.dist'
            );
        }
    }
}
