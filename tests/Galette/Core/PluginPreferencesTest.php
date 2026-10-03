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
use Galette\Enums\PublicPageVisibility;
use Galette\Tests\GaletteTestCase;

/**
 * A plugin declaring preferences stores them among Galette's own, under a
 * prefixed name. The fixture plugin-test1 declares three, and a public page,
 * so loading plugins is enough to exercise the whole path.
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
            print_r($this->preferences->getErrors(), return: true)
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

    /**
     * A declared public page gets a visibility, which the core recognises
     */
    public function testPublicPageJoinsTheSchema(): void
    {
        $name = 'pref_plugin1_publicpages_visibility_page';

        $this->assertSame($name, PreferencesSchema::getPublicPageName('plugin1', 'page'));
        $this->assertTrue(PreferencesSchema::has($name));
        $this->assertSame('plugin1', PreferencesSchema::getOwner($name));
        $this->assertTrue(PreferencesSchema::isPublicPage($name));
        $this->assertSame(PublicPageVisibility::Inherit->value, PreferencesSchema::get($name)['default']);
        $this->assertSame([$name], array_keys(PreferencesSchema::getPluginPublicPages()));

        //ordinary plugin preferences are not public pages, core visibilities are
        $this->assertFalse(PreferencesSchema::isPublicPage('pref_plugin1_label'));
        $this->assertTrue(PreferencesSchema::isPublicPage('pref_publicpages_visibility_documents'));
        $this->assertFalse(PreferencesSchema::isPublicPage('pref_nom'));

        //routes lead to it, provided they live under the plugin path
        $this->assertSame($name, PreferencesSchema::getPublicPageRight('plugin1_public_page'));
        $this->assertSame(
            $name,
            PreferencesSchema::getPublicPageRight('plugin1_public_page', '/plugins/plugin1/public/page')
        );
        $this->assertNull(
            PreferencesSchema::getPublicPageRight('plugin1_public_page', '/plugins/plugin2/public/page')
        );
        $this->assertNull(PreferencesSchema::getPublicPageRight('plugin1_public_other'));
        $this->assertNull(PreferencesSchema::getPublicPageRight('publicMembersList'));

        //and they go with the plugin
        PreferencesSchema::unregister('plugin1');
        $this->assertNull(PreferencesSchema::getPublicPageRight('plugin1_public_page'));
    }

    /**
     * Its row is created, with the declared default
     */
    public function testPublicPageRowIsCreated(): void
    {
        $this->preferences->load();

        $this->assertContains('pref_plugin1_publicpages_visibility_page', $this->preferences->getFieldsNames());
        $this->assertSame(
            PublicPageVisibility::Inherit->value,
            $this->preferences->getPluginValue('pref_plugin1_publicpages_visibility_page')
        );
    }

    /**
     * Unlike other plugin preferences, the core settings form saves it
     */
    public function testCoreFormSavesPublicPage(): void
    {
        $name = 'pref_plugin1_publicpages_visibility_page';
        $this->preferences->load();

        //what the core settings form posts, the plugin page included
        $values = array_map(
            fn(array $entry): bool|int|string => $entry['default'],
            PreferencesSchema::getCore()
        );
        $values[$name] = (string)PublicPageVisibility::Hidden->value;

        $this->assertTrue(
            $this->preferences->check($values, $this->login),
            print_r($this->preferences->getErrors(), return: true)
        );
        $this->assertSame(PublicPageVisibility::Hidden->value, $this->preferences->getPluginValue($name));

        //a form without it keeps what is stored: the plugin may have been off
        //when the page was rendered
        unset($values[$name]);
        $this->assertTrue($this->preferences->check($values, $this->login));
        $this->assertSame(PublicPageVisibility::Hidden->value, $this->preferences->getPluginValue($name));

        //and its value is validated like the core ones
        $values[$name] = '7';
        $this->assertFalse($this->preferences->check($values, $this->login));
        $this->assertContains(
            "- Unknown visibility for '" . $name . "'!",
            $this->preferences->getErrors()
        );

        $this->preferences->load();
    }

    /**
     * A plugin cannot flag a preference of its own as a public page
     *
     * The core form would otherwise save it, and a declared route would lead to
     * whatever it holds.
     */
    public function testReservedKeysAreDropped(): void
    {
        PreferencesSchema::register('plugin1', [
            'pref_plugin1_sneaky' => [
                'type' => PreferencesSchema::TYPE_INT,
                'default' => 0,
                'public_page' => true,
                'routes' => ['publicMembersList'],
                'plugin' => 'other',
            ],
        ]);

        $this->assertTrue(PreferencesSchema::has('pref_plugin1_sneaky'));
        $this->assertSame('plugin1', PreferencesSchema::getOwner('pref_plugin1_sneaky'));
        $this->assertFalse(PreferencesSchema::isPublicPage('pref_plugin1_sneaky'));
        $this->assertNull(PreferencesSchema::getPublicPageRight('publicMembersList'));
    }

    /**
     * A malformed public page is dropped, reported, and not fatal
     */
    public function testMalformedPublicPagesAreDropped(): void
    {
        global $galette_log_var;

        PreferencesSchema::register(
            'plugin1',
            [],
            [
                'ok' => ['routes' => ['plugin1_ok'], 'default' => PublicPageVisibility::StaffOnly->value],
                'Bad-Id' => ['routes' => ['plugin1_bad']],
                'noroutes' => [],
                'emptyroutes' => ['routes' => []],
                'badroute' => ['routes' => [42]],
                'baddefault' => ['routes' => ['plugin1_baddefault'], 'default' => 9],
            ]
        );

        $this->assertSame(
            ['pref_plugin1_publicpages_visibility_ok'],
            array_keys(PreferencesSchema::getPluginPublicPages())
        );
        $this->assertSame(
            PublicPageVisibility::StaffOnly->value,
            PreferencesSchema::get('pref_plugin1_publicpages_visibility_ok')['default']
        );

        $expected = [
            '"Bad-Id" is not a valid identifier',
            '"noroutes" has no routes',
            '"emptyroutes" has no routes',
            '"badroute" has an invalid route name',
            '"baddefault" has an unknown default visibility',
        ];
        foreach ($expected as $rejected) {
            $this->assertStringContainsString(
                'Plugin "plugin1" declares an invalid public page: ' . $rejected,
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
