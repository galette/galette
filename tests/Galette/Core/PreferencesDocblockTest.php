<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\PreferencesSchema;
use Galette\Tests\GaletteTestCase;
use ReflectionClass;

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
     *
     * Core preferences only: a plugin declares its own into the schema, and
     * naming them on this class would have core depend on its plugins.
     */
    public function testDocblockMatchesPreferences(): void
    {
        $documented = $this->getDocumentedProperties();
        $declared = array_keys(\Galette\Core\PreferencesSchema::getCore());

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

        foreach (array_keys(\Galette\Core\PreferencesSchema::getCore()) as $name) {
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
     * The types the schema declares, for the preferences that get cast
     *
     * @return array<string, string> Preference name => cast type
     */
    private function getCastTypes(): array
    {
        $casts = [];

        foreach (array_keys(PreferencesSchema::getAll()) as $name) {
            $type = PreferencesSchema::getType($name);
            if (in_array($type, [PreferencesSchema::TYPE_INT, PreferencesSchema::TYPE_BOOL], true)) {
                $casts[$name] = $type;
            }
        }
        $this->assertNotEmpty($casts, 'the schema casts nothing');

        return $casts;
    }
}
