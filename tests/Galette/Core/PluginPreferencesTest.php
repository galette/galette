<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\Preferences;
use Galette\Core\PreferencesSchema;
use Galette\Tests\GaletteTestCase;

/**
 * A plugin declaring preferences stores them among Galette's own, under a
 * prefixed name. The fixture plugin-test1 declares three, so loading plugins
 * is enough to exercise the whole path.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginPreferencesTest extends GaletteTestCase
{
    /**
     * Declared entries join the schema, and say who owns them
     */
    public function testEntriesJoinTheSchema(): void
    {
        $this->assertTrue(PreferencesSchema::has('pref_plugin1_label'));
        $this->assertSame('plugin1', PreferencesSchema::getOwner('pref_plugin1_label'));

        //core describes itself without them
        $this->assertArrayNotHasKey('pref_plugin1_label', PreferencesSchema::getCore());
        $this->assertNull(PreferencesSchema::getOwner('pref_nom'));
    }

    /**
     * They are read with the type they declare, not as raw strings
     */
    public function testValuesAreTyped(): void
    {
        $this->preferences->load();

        $this->assertSame('plugin one', $this->preferences->getPluginValue('pref_plugin1_label'));
        $this->assertSame(3, $this->preferences->getPluginValue('pref_plugin1_count'));
        $this->assertFalse($this->preferences->getPluginValue('pref_plugin1_enabled'));
    }

    /**
     * Their rows are created, even though preferences were built before any
     * plugin was known
     */
    public function testRowsAreCreated(): void
    {
        $this->preferences->load();

        $this->assertContains('pref_plugin1_count', $this->preferences->getFieldsNames());
    }

    /**
     * They are writable through the regular API, and validated
     */
    public function testValuesAreWritable(): void
    {
        $this->preferences->load();

        $this->assertTrue(
            $this->preferences->setValue('pref_plugin1_count', 7, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertSame(7, $this->preferences->getPluginValue('pref_plugin1_count'));

        //value really reached database
        $prefs = new Preferences($this->zdb);
        $this->assertSame(7, $prefs->getPluginValue('pref_plugin1_count'));

        //the schema constraints apply
        $this->assertFalse($this->preferences->setValue('pref_plugin1_count', 42, $this->login));

        $this->assertTrue($this->preferences->resetValue('pref_plugin1_count', $this->login));
        $this->assertSame(3, $this->preferences->getPluginValue('pref_plugin1_count'));
    }

    /**
     * Saving the core settings form leaves them alone
     *
     * The form never renders a plugin preference, so the missing field would
     * otherwise be taken for an emptied one and blank every plugin setting on
     * each save.
     */
    public function testCoreFormDoesNotBlankThem(): void
    {
        $this->preferences->load();
        $this->preferences->setValue('pref_plugin1_label', 'kept', $this->login);

        //what the core settings form posts: core preferences, and nothing else
        $values = array_map(
            fn(array $entry): bool|int|string => $entry['default'],
            PreferencesSchema::getCore()
        );
        $values['pref_nom'] = 'Galette';

        $this->preferences->check($values, $this->login);

        $this->assertSame('kept', $this->preferences->getPluginValue('pref_plugin1_label'));

        $this->preferences->resetValue('pref_plugin1_label', $this->login);
    }

    /**
     * Once the plugin is gone the value stays, unknown and read-only
     */
    public function testUnregisteredValueSurvives(): void
    {
        $this->preferences->load();
        $this->preferences->setValue('pref_plugin1_label', 'still here', $this->login);

        PreferencesSchema::unregister('plugin1');

        $this->assertFalse(PreferencesSchema::has('pref_plugin1_label'));
        $this->assertNull(PreferencesSchema::getOwner('pref_plugin1_label'));

        //readable, as the string it is stored as
        $prefs = new Preferences($this->zdb);
        $this->assertSame('still here', $prefs->getPluginValue('pref_plugin1_label'));

        //but no longer writable
        $this->assertFalse($prefs->setValue('pref_plugin1_label', 'nope', $this->login));
        $this->assertSame(
            ["Unknown preference 'pref_plugin1_label'!"],
            $prefs->getErrors()
        );
    }

    /**
     * A malformed declaration is dropped, not honoured, and not fatal
     */
    public function testMalformedEntriesAreDropped(): void
    {
        global $galette_log_var;

        PreferencesSchema::register('plugin1', [
            'pref_plugin1_ok' => ['type' => PreferencesSchema::TYPE_STRING, 'default' => 'y'],
            'unprefixed' => ['type' => PreferencesSchema::TYPE_STRING, 'default' => 'x'],
            'pref_plugin1_untyped' => ['default' => 'x'],
            'pref_plugin1_nodefault' => ['type' => PreferencesSchema::TYPE_STRING],
            'pref_nom' => ['type' => PreferencesSchema::TYPE_STRING, 'default' => 'hijacked'],
        ]);

        $this->assertTrue(PreferencesSchema::has('pref_plugin1_ok'));
        $this->assertFalse(PreferencesSchema::has('unprefixed'));
        $this->assertFalse(PreferencesSchema::has('pref_plugin1_untyped'));
        $this->assertFalse(PreferencesSchema::has('pref_plugin1_nodefault'));

        //a core preference cannot be taken over: no core name carries a plugin prefix
        $this->assertNull(PreferencesSchema::getOwner('pref_nom'));
        $this->assertSame('Galette', PreferencesSchema::getCore()['pref_nom']['default']);

        //every rejection was reported
        foreach (['unprefixed', 'pref_plugin1_untyped', 'pref_plugin1_nodefault', 'pref_nom'] as $rejected) {
            $this->assertStringContainsString(
                sprintf('Plugin "plugin1" declares an invalid preference: "%s"', $rejected),
                (string)$galette_log_var
            );
        }

        //drained here, or they would surface as stray entries in the next test
        $galette_log_var = null;
        \Analog\Analog::handler(
            \Analog\Handler\LevelName::init(\Analog\Handler\Variable::init($galette_log_var))
        );
    }
}
