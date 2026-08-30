<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;
use ReflectionClass;

use function Safe\file_get_contents;
use function Safe\preg_match;
use function Safe\preg_match_all;

/**
 * Preferences are magic properties: PHPStan only knows about them through the
 * property annotations on the class. Nothing keeps that block in step with the
 * preferences actually declared, nor with the casts __get() applies, so these
 * tests do.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PreferencesDocblockTest extends GaletteTestCase
{
    /**
     * Properties annotated without a matching preference
     *
     * Listed explicitly so that adding one stays a deliberate act.
     *
     * @var array<string>
     */
    private const array VIRTUALS = ['vpref_email_newadh'];

    /**
     * Every preference is annotated, and nothing else is
     */
    public function testDocblockMatchesPreferences(): void
    {
        $documented = $this->getDocumentedProperties();
        $declared = array_keys($this->preferences->getDefaults());

        foreach ($declared as $name) {
            $this->assertArrayHasKey(
                $name,
                $documented,
                $name . ' is declared but carries no property annotation'
            );
        }

        foreach (array_keys($documented) as $name) {
            if (in_array($name, self::VIRTUALS, true)) {
                continue;
            }
            $this->assertContains(
                $name,
                $declared,
                $name . ' is annotated but is not a declared preference'
            );
        }
    }

    /**
     * A preference is documented once
     */
    public function testDocblockHasNoDuplicate(): void
    {
        $names = $this->getDocumentedPropertyNames();

        $this->assertSame(
            array_unique($names),
            $names,
            'duplicated property annotations: ' . implode(
                ', ',
                array_unique(array_diff_assoc($names, array_unique($names)))
            )
        );
    }

    /**
     * The documented type is the one __get() actually returns
     *
     * A preference absent from both cast lists is read as the string it is
     * stored as, so it must be documented as one.
     */
    public function testDocblockTypesMatchCasts(): void
    {
        $documented = $this->getDocumentedProperties();
        $casts = $this->getCastTypes();

        foreach (array_keys($this->preferences->getDefaults()) as $name) {
            $expected = $casts[$name] ?? 'string';

            $this->assertSame(
                $expected,
                $documented[$name],
                sprintf(
                    '%s is documented as %s while __get() returns %s',
                    $name,
                    $documented[$name],
                    $expected
                )
            );
        }
    }

    /**
     * Both cast lists only mention preferences that exist
     */
    public function testCastListsHoldNoStrayEntry(): void
    {
        $declared = array_keys($this->preferences->getDefaults());

        foreach (array_keys($this->getCastTypes()) as $name) {
            $this->assertContains(
                $name,
                $declared,
                $name . ' is cast by __get() but is not a declared preference'
            );
        }
    }

    /**
     * Read the property annotations off the class docblock
     *
     * @return array<string, string> Property name => documented type
     */
    private function getDocumentedProperties(): array
    {
        $reflection = new ReflectionClass(\Galette\Core\Preferences::class);
        $docblock = $reflection->getDocComment();
        $this->assertIsString($docblock, 'Preferences has no class docblock');

        $matches = [];
        preg_match_all(
            pattern: '/@property(?:-read|-write)?\s+(\S+)\s+\$(\w+)/',
            subject: $docblock,
            matches: $matches,
            flags: PREG_SET_ORDER
        );
        $this->assertNotEmpty($matches, 'no property annotation found');

        $properties = [];
        foreach ($matches as $match) {
            $properties[$match[2]] = $match[1];
        }

        return $properties;
    }

    /**
     * Same, keeping duplicates
     *
     * @return array<int, string>
     */
    private function getDocumentedPropertyNames(): array
    {
        $reflection = new ReflectionClass(\Galette\Core\Preferences::class);
        $matches = [];
        preg_match_all(
            '/@property(?:-read|-write)?\s+\S+\s+\$(\w+)/',
            (string)$reflection->getDocComment(),
            $matches
        );

        return $matches[1];
    }

    /**
     * Read the int and bool cast lists out of __get()
     *
     * They are a local variable, so there is no way to it but the source.
     *
     * @return array<string, string> Preference name => cast type
     */
    private function getCastTypes(): array
    {
        $reflection = new ReflectionClass(\Galette\Core\Preferences::class);
        $file = (string)$reflection->getFileName();
        $source = file_get_contents($file);

        $block = [];
        $found = preg_match('/\$types = \[(.*?)\n        \];/s', $source, $block);
        $this->assertSame(1, $found, 'cast lists not found in __get()');

        $casts = [];
        foreach (['int', 'bool'] as $type) {
            $list = [];
            if (preg_match("/'" . $type . "' => \[(.*?)\]/s", $block[1], $list) !== 1) {
                continue;
            }
            $names = [];
            preg_match_all("/'(\w+)'/", $list[1], $names);
            foreach ($names[1] as $name) {
                $casts[$name] = $type;
            }
        }
        $this->assertNotEmpty($casts, 'no cast list found');

        return $casts;
    }
}
