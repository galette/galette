<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\PreferencesSchema as Schema;
use Galette\Tests\GaletteTestCase;

/**
 * Preferences schema tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PreferencesSchema extends GaletteTestCase
{
    //getErrorMessage() goes through _T(), which needs $translator and $l10n.
    //BaseGaletteTestCase::tearDown() nulls both, so a plain TestCase would
    //pass alone and fail whenever it runs after any GaletteTestCase.

    /**
     * Every entry must declare a known type and a scalar default
     */
    public function testEntriesAreWellFormed(): void
    {
        $known_types = [
            Schema::TYPE_STRING,
            Schema::TYPE_INT,
            Schema::TYPE_BOOL,
            Schema::TYPE_EMAIL,
            Schema::TYPE_EMAILS,
            Schema::TYPE_URL,
            Schema::TYPE_COLOR,
            Schema::TYPE_HTML,
            Schema::TYPE_PASSWORD,
            Schema::TYPE_LOGIN,
            Schema::TYPE_DATE_MD,
            Schema::TYPE_YEAR,
        ];

        $schema = Schema::getAll();
        $this->assertNotEmpty($schema);

        foreach ($schema as $name => $entry) {
            $this->assertStringStartsWith('pref_', $name, $name . ' is not a preference name');
            $this->assertArrayHasKey('type', $entry, $name . ' has no type');
            $this->assertContains($entry['type'], $known_types, $name . ' has an unknown type');
            $this->assertArrayHasKey('default', $entry, $name . ' has no default');
        }
    }

    /**
     * Defaults are exposed as the flat map Preferences expects
     */
    public function testDefaults(): void
    {
        $defaults = Schema::getDefaults();

        $this->assertSame(array_keys(Schema::getAll()), array_keys($defaults));
        $this->assertSame('Galette', $defaults['pref_nom']);
        $this->assertSame(30, $defaults['pref_numrows']);
        $this->assertFalse($defaults['pref_noindex']);
    }

    /**
     * Required preferences are derived from the schema
     */
    public function testRequired(): void
    {
        $required = Schema::getRequired();

        $this->assertArrayHasKey('pref_nom', $required);
        $this->assertArrayNotHasKey('pref_slogan', $required);

        foreach ($required as $name => $flag) {
            $this->assertSame(1, $flag);
            $this->assertTrue(Schema::has($name));
        }

        //the superadmin login is added at runtime, not declared as required
        $this->assertArrayNotHasKey('pref_admin_login', $required);
    }

    /**
     * Types drive the casts done on read
     */
    public function testGetType(): void
    {
        $this->assertSame(Schema::TYPE_INT, Schema::getType('pref_numrows'));
        $this->assertSame(Schema::TYPE_BOOL, Schema::getType('pref_noindex'));
        $this->assertSame(Schema::TYPE_EMAIL, Schema::getType('pref_email'));
        $this->assertSame(Schema::TYPE_EMAILS, Schema::getType('pref_email_newadh'));
        $this->assertSame(Schema::TYPE_COLOR, Schema::getType('pref_card_tcol'));
        $this->assertSame(Schema::TYPE_HTML, Schema::getType('pref_footer'));

        //a row left over by an old version or a plugin reads as a plain string
        $this->assertFalse(Schema::has('pref_does_not_exist'));
        $this->assertSame(Schema::TYPE_STRING, Schema::getType('pref_does_not_exist'));
        $this->assertNull(Schema::get('pref_does_not_exist'));
    }

    /**
     * Only the superadmin credentials require the superadmin level
     */
    public function testAcl(): void
    {
        $this->assertSame(Schema::ACL_SUPERADMIN, Schema::getAcl('pref_admin_login'));
        $this->assertSame(Schema::ACL_SUPERADMIN, Schema::getAcl('pref_admin_pass'));
        $this->assertSame(Schema::ACL_ADMIN, Schema::getAcl('pref_nom'));
        $this->assertSame(Schema::ACL_ADMIN, Schema::getAcl('pref_does_not_exist'));

        $superadmin = array_keys(
            array_filter(
                Schema::getAll(),
                fn(array $entry): bool => ($entry['acl'] ?? Schema::ACL_ADMIN) === Schema::ACL_SUPERADMIN
            )
        );
        $this->assertSame(['pref_admin_login', 'pref_admin_pass'], $superadmin);
    }

    /**
     * Secrets must never be rendered
     */
    public function testSensitive(): void
    {
        $this->assertTrue(Schema::isSensitive('pref_admin_pass'));
        $this->assertTrue(Schema::isSensitive('pref_mail_smtp_password'));
        $this->assertFalse(Schema::isSensitive('pref_nom'));
        $this->assertFalse(Schema::isSensitive('pref_does_not_exist'));
    }

    /**
     * Legacy behaviour constants a preference supersedes
     */
    public function testConstants(): void
    {
        $constants = Schema::getConstants();

        $this->assertNotEmpty($constants);
        $this->assertSame('GALETTE_URI', $constants['pref_galette_url'] ?? null);
        $this->assertSame('GALETTE_URI', Schema::getConstant('pref_galette_url'));
        $this->assertNull(Schema::getConstant('pref_nom'));

        foreach ($constants as $name => $constant) {
            $this->assertTrue(Schema::has($name));
            $this->assertStringStartsWith('GALETTE_', $constant);
        }
    }

    /**
     * Any bounded entry must carry a resolvable error message
     */
    public function testBoundedEntriesCarryAMessage(): void
    {
        $bounded = 0;
        foreach (Schema::getAll() as $name => $entry) {
            if (!isset($entry['min']) && !isset($entry['max']) && !isset($entry['minlength'])) {
                continue;
            }
            ++$bounded;
            $this->assertArrayHasKey('error', $entry, $name . ' is bounded but has no message');
            $this->assertNotEmpty(
                Schema::getErrorMessage($entry['error']),
                $name . ' message does not resolve'
            );
        }
        $this->assertGreaterThan(0, $bounded);
    }

    /**
     * A reusable message names the preference it is about
     *
     * This is what lets a new numeric preference be declared with a schema
     * entry only, and no new translatable string.
     */
    public function testMessagesNameTheirPreference(): void
    {
        $this->assertSame(
            "- Value for 'pref_something' must be a positive number!",
            Schema::getErrorMessage(Schema::ERR_POSITIVE_NUMBER, 'pref_something')
        );

        //without a preference, the placeholder is left alone
        $this->assertStringContainsString(
            '%field',
            Schema::getErrorMessage(Schema::ERR_POSITIVE_NUMBER)
        );

        //a message with no placeholder is untouched
        $this->assertSame(
            Schema::getErrorMessage(Schema::ERR_CARD_HEIGHT),
            Schema::getErrorMessage(Schema::ERR_CARD_HEIGHT, 'pref_card_vsize')
        );
    }

    /**
     * An unknown error identifier is a programming error
     */
    public function testUnknownErrorMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown error identifier "nope".');
        Schema::getErrorMessage('nope');
    }
}
