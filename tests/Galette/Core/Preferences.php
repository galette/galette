<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Safe\define;

/**
 * Preferences tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Preferences extends GaletteTestCase
{
    protected int $seed = 20240917074915;

    /**
     * Test preferences initialization
     */
    public function testInstallInit(): void
    {
        $result = $this->preferences->installInit(
            'en_US',
            'da_admin',
            password_hash('da_secret', PASSWORD_BCRYPT)
        );
        $this->assertTrue($result);

        //new object with values loaded from database to compare
        $prefs = new \Galette\Core\Preferences($this->zdb);

        foreach ($prefs->getDefaults() as $key => $expected) {
            $value = $prefs->$key;

            switch ($key) {
                case 'pref_admin_login':
                    $this->assertSame('da_admin', $value);
                    break;
                case 'pref_admin_pass':
                    $pw_checked = password_verify('da_secret', (string)$value);
                    $this->assertTrue($pw_checked);
                    break;
                case 'pref_lang':
                    $this->assertSame('en_US', $value);
                    break;
                case 'pref_card_year':
                    $this->assertSame(date('Y'), $value);
                    break;
                default:
                    $this->assertEquals($expected, $value, 'Wrong value for ' . $key);
                    break;
            }
        }

        //try to set and get a non-existent value
        $prefs->doesnotexists = 'that *does* not exists.'; // @phpstan-ignore property.notFound (class handle that)
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Trying to set a preference value which does not seem to exist (doesnotexists)'
        );
        $false_result = $prefs->doesnotexists;
        $this->assertFalse($false_result);

        //change slogan
        $slogan = 'One Galette to rule them all';
        $prefs->pref_slogan = $slogan;
        $check = $prefs->pref_slogan;
        $this->assertSame($slogan, $check);

        //change password
        $new_pass = 'anoth3er_s3cr3t';
        $prefs->pref_admin_pass = $new_pass;
        $pass = $prefs->pref_admin_pass;
        $pw_checked = password_verify($new_pass, $pass);
        $this->assertTrue($pw_checked);

        $this->preferences->pref_nom = 'Galette';
        $this->preferences->pref_ville = 'Avignon';
        $this->preferences->pref_cp = '84000';
        $this->preferences->pref_adresse = 'Palais des Papes';
        $this->preferences->pref_adresse2 = 'Au milieu';
        $this->preferences->pref_pays = 'France';

        $expected = "Galette\nPalais des Papes\nAu milieu\n84000 Avignon - France";
        $address = $this->preferences->getPostalAddress();

        $this->assertSame($expected, $address);

        $slogan = $this->preferences->pref_slogan;
        $this->assertEquals('', $slogan);

        $slogan = 'One Galette to rule them all';
        $this->preferences->pref_slogan = $slogan;
        $result = $this->preferences->store();

        $this->assertTrue($result);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $check_slogan = $prefs->pref_slogan;
        $this->assertEquals($slogan, $check_slogan);

        //reset database value...
        $this->preferences->pref_slogan = '';
        $this->preferences->store();
    }

    /**
     * Test writing a single preference
     */
    public function testSetValue(): void
    {
        $this->preferences->load();
        $original = $this->preferences->pref_numrows;

        $this->assertTrue(
            $this->preferences->setValue('pref_numrows', 42, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertSame(42, $this->preferences->pref_numrows);

        //value really reached database
        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame(42, $prefs->pref_numrows);

        //reset to default
        $this->assertTrue(
            $this->preferences->resetValue('pref_numrows', $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertSame(
            $this->preferences->getDefaults()['pref_numrows'],
            $this->preferences->pref_numrows
        );

        $this->preferences->setValue('pref_numrows', $original, $this->login);
    }

    /**
     * A single write goes through the per-field constraints of the schema
     */
    public function testSetValueChecksField(): void
    {
        $this->preferences->load();

        $this->assertFalse($this->preferences->setValue('pref_card_vsize', 12, $this->login));
        $this->assertSame(
            ['- The card height have to be an integer between 40 and 55!'],
            $this->preferences->getErrors()
        );

        //nothing was stored
        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertNotSame(12, $prefs->pref_card_vsize);
    }

    /**
     * A single write cannot break a relation between preferences either
     */
    public function testSetValueChecksRelations(): void
    {
        $this->preferences->load();
        $original = $this->preferences->pref_beg_membership;

        //default has an extension set, so a beginning of membership conflicts
        $this->assertNotSame('', $this->preferences->pref_membership_ext);
        $this->assertFalse($this->preferences->setValue('pref_beg_membership', '01/06', $this->login));
        $this->assertSame(
            ['- Default membership extension and beginning of membership are mutually exclusive.'],
            $this->preferences->getErrors()
        );

        $this->preferences->setValue('pref_beg_membership', $original, $this->login);
    }

    /**
     * Unknown preferences are refused rather than silently created
     */
    public function testSetValueRefusesUnknown(): void
    {
        $this->preferences->load();

        $this->assertFalse($this->preferences->setValue('pref_nope', 'x', $this->login));
        $this->assertSame(["Unknown preference 'pref_nope'!"], $this->preferences->getErrors());

        $this->assertFalse($this->preferences->resetValue('pref_nope', $this->login));
        $this->assertSame(["Unknown preference 'pref_nope'!"], $this->preferences->getErrors());
    }

    /**
     * Superadmin credentials require the superadmin level
     */
    public function testSetValueHonoursAcl(): void
    {
        $this->preferences->load();

        $this->assertFalse($this->preferences->setValue('pref_admin_login', 'someone', $this->login));
        $this->assertSame(
            ["You are not allowed to change preference 'pref_admin_login'!"],
            $this->preferences->getErrors()
        );

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertNotSame('someone', $prefs->pref_admin_login);
    }

    /**
     * A secret must not be resettable to a publicly known default
     */
    public function testResetValueRefusesSecrets(): void
    {
        $this->preferences->load();
        $stored = $this->preferences->pref_admin_pass;

        $this->logSuperAdmin();
        $this->assertFalse($this->preferences->resetValue('pref_admin_pass', $this->login));
        $this->assertSame(
            ["Preference 'pref_admin_pass' holds a secret and cannot be reset to its default!"],
            $this->preferences->getErrors()
        );

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $this->assertSame($stored, $prefs->pref_admin_pass);
    }

    /**
     * A preference with no legacy constant reads straight from database
     */
    public function testGetConfigValue(): void
    {
        $this->preferences->load();

        $this->assertNull(\Galette\Core\PreferencesSchema::getConstant('pref_numrows'));
        $this->assertSame(
            $this->preferences->pref_numrows,
            $this->preferences->getConfigValue('pref_numrows')
        );
    }

    /**
     * A defined legacy constant wins over the stored value, and says so once
     *
     * Isolated: define() cannot be undone, and the constant would otherwise
     * leak into every test running after this one in the same process.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConstantOverridesPreference(): void
    {
        $this->preferences->load();

        //pref_galette_url is the one superseding GALETTE_URI
        $constant = \Galette\Core\PreferencesSchema::getConstant('pref_galette_url');
        $this->assertSame('GALETTE_URI', $constant);

        if (defined($constant)) {
            $this->markTestSkipped($constant . ' is defined in this environment');
        }

        //not defined: the stored value is used, and nothing is logged
        $this->preferences->setValue('pref_galette_url', 'https://example.com', $this->login);
        $this->assertSame('https://example.com', $this->preferences->getConfigValue('pref_galette_url'));

        define('GALETTE_URI', 'https://from-the-file.example.com');

        $this->assertSame(
            'https://from-the-file.example.com',
            $this->preferences->getConfigValue('pref_galette_url')
        );
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Constant GALETTE_URI is defined and takes precedence over preference pref_galette_url.'
        );

        //reported once only, so reading it in a loop does not flood the log
        $this->preferences->getConfigValue('pref_galette_url');
        $this->expectNoLogEntry();

        $this->preferences->setValue('pref_galette_url', '', $this->login);
    }

    /**
     * Values Galette maintains itself are never writable from a payload
     */
    public function testReadOnlyPreferences(): void
    {
        $this->preferences->load();
        $this->logSuperAdmin();

        $readonly = [
            'pref_instance_uuid',
            'pref_registration_uuid',
            'pref_telemetry_date',
            'pref_registration_date',
            'pref_adhesion_form',
        ];

        foreach ($readonly as $name) {
            $this->assertTrue(
                \Galette\Core\PreferencesSchema::isReadOnly($name),
                $name . ' should be read-only'
            );

            $this->assertFalse($this->preferences->setValue($name, 'tampered', $this->login));
            $this->assertSame(
                ["Preference '" . $name . "' is maintained by Galette and cannot be changed!"],
                $this->preferences->getErrors()
            );

            $this->assertFalse($this->preferences->resetValue($name, $this->login));
        }

        //nor through the settings form
        $uuid = $this->preferences->pref_instance_uuid;
        $values = $this->preferences->getDefaults();
        $values['pref_nom'] = 'Galette';
        $values['pref_instance_uuid'] = 'tampered';
        $this->preferences->check($values, $this->login);
        $this->assertSame($uuid, $this->preferences->pref_instance_uuid);
    }

    /**
     * Galette still writes those values itself
     */
    public function testReadOnlyPreferencesAreStillMaintained(): void
    {
        $this->preferences->load();

        $uuid = $this->preferences->generateUUID('instance');
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]{40}$/', $uuid);
        $this->assertSame($uuid, $this->preferences->pref_instance_uuid);

        $this->assertTrue($this->preferences->updateTelemetryDate());
        $this->assertNotEmpty($this->preferences->pref_telemetry_date);
    }

    /**
     * Galette must never define a constant superseded by a preference
     *
     * behavior.inc.php is skipped under GALETTE_TESTS, so anything defined
     * here was defined by Galette's own bootstrap. That makes the setting look
     * overridden by the file on every instance, hides it behind a read-only
     * cell, and logs a bogus override warning on each read.
     */
    public function testGaletteDefinesNoSupersededConstant(): void
    {
        foreach (\Galette\Core\PreferencesSchema::getConstants() as $name => $constant) {
            $this->assertFalse(
                defined($constant),
                $constant . ' is defined by Galette itself, ' . $name . ' would look locked everywhere'
            );
        }
    }

    /**
     * Test fields names
     */
    public function testFieldsNames(): void
    {
        $this->preferences->load();
        $fields_names = $this->preferences->getFieldsNames();
        $expected = array_keys($this->preferences->getDefaults());

        sort($fields_names);
        sort($expected);

        $this->assertSame($expected, $fields_names);
    }

    /**
     * Test preferences updating when some are missing
     */
    public function testUpdate(): void
    {
        $delete = $this->zdb->delete(\Galette\Core\Preferences::TABLE);
        $delete->where(
            [
                'nom_pref' => 'pref_footer'
            ]
        );
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Core\Preferences::TABLE);
        $delete->where(
            [
                'nom_pref' => 'pref_new_contrib_script'
            ]
        );
        $this->zdb->execute($delete);

        $this->preferences->load();
        $footer = $this->preferences->pref_footer;
        $new_contrib_script = $this->preferences->pref_new_contrib_script;

        $this->assertFalse($footer);
        $this->assertFalse($new_contrib_script);

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $footer = $prefs->pref_footer;
        $new_contrib_script = $prefs->pref_new_contrib_script;

        $this->assertSame('', $footer);
        $this->assertSame('', $new_contrib_script);
    }

    /**
     * Test public pages visibility
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testPublicPagesVisibility(): void
    {
        $this->preferences->load();

        $visibilities = [
            'pref_publicpages_visibility_memberslist',
            'pref_publicpages_visibility_membersgallery',
            'pref_publicpages_visibility_stafflist',
            'pref_publicpages_visibility_staffgallery',
            'pref_publicpages_visibility_documents',
        ];

        foreach ($visibilities as $visibility) {
            $this->preferences->$visibility = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
        }

        $anon_login = new \Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );

        $this->preferences->pref_bool_publicpages = true;

        $superadmin_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isSuperAdmin', 'isAdmin'])
            ->getMock();
        $superadmin_login->method('isSuperAdmin')->willReturn(true);
        $superadmin_login->method('isAdmin')->willReturn(true);

        $admin_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isAdmin'])
            ->getMock();
        $admin_login->method('isAdmin')->willReturn(true);

        $user_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isUp2Date'])
            ->getMock();
        $user_login->method('isUp2Date')->willReturn(true);

        foreach ($visibilities as $visibility) {
            $visible = $this->preferences->showPublicPage($anon_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($admin_login, $visibility);
            $this->assertTrue($visible);

            $visible = $this->preferences->showPublicPage($user_login, $visibility);
            $this->assertTrue($visible);

            $visible = $this->preferences->showPublicPage($superadmin_login, $visibility);
            $this->assertTrue($visible, $visibility);
        }

        foreach ($visibilities as $visibility) {
            $this->preferences->$visibility = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;

            $visible = $this->preferences->showPublicPage($anon_login, $visibility);
            $this->assertTrue($visible);

            $visible = $this->preferences->showPublicPage($admin_login, $visibility);
            $this->assertTrue($visible);

            $visible = $this->preferences->showPublicPage($user_login, $visibility);
            $this->assertTrue($visible);

            $visible = $this->preferences->showPublicPage($superadmin_login, $visibility);
            $this->assertTrue($visible);
        }

        foreach ($visibilities as $visibility) {
            $this->preferences->$visibility = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE;

            $visible = $this->preferences->showPublicPage($anon_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($admin_login, $visibility);
            $this->assertTrue($visible);

            $visible = $this->preferences->showPublicPage($user_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($superadmin_login, $visibility);
            $this->assertTrue($visible);
        }

        foreach ($visibilities as $visibility) {
            $this->preferences->$visibility = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_HIDDEN;

            $visible = $this->preferences->showPublicPage($anon_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($admin_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($user_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($superadmin_login, $visibility);
            $this->assertFalse($visible);
        }

        $this->assertSame(
            \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
            $this->preferences->pref_publicpages_visibility_generic
        );
        foreach ($visibilities as $visibility) {
            $this->preferences->$visibility = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_INHERIT;

            $visible = $this->preferences->showPublicPage($anon_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($admin_login, $visibility);
            $this->assertTrue($visible);

            $visible = $this->preferences->showPublicPage($user_login, $visibility);
            $this->assertTrue($visible);

            $visible = $this->preferences->showPublicPage($superadmin_login, $visibility);
            $this->assertTrue($visible);
        }

        $this->preferences->pref_publicpages_visibility_generic = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_HIDDEN;
        foreach ($visibilities as $visibility) {
            $this->preferences->$visibility = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_INHERIT;

            $visible = $this->preferences->showPublicPage($anon_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($admin_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($user_login, $visibility);
            $this->assertFalse($visible);

            $visible = $this->preferences->showPublicPage($superadmin_login, $visibility);
            $this->assertFalse($visible);
        }
    }

    /**
     * Data provider for colors
     *
     * @return array<array{prop: string, color: string, expected: string}>
     */
    public static function colorsProvider(): array
    {
        return [
            [
                'prop' => 'tcol',
                'color' => '#f0f0f0',
                'expected' => '#f0f0f0'
            ], [
                'prop' => 'tcol',
                'color' => '#f0f0f0f0',
                'expected' => '#FFFFFF'
            ], [
                'prop' => 'tcol',
                'color' => 'f0f0f0',
                'expected' => '#f0f0f0'
            ], [
                'prop' => 'tcol',
                'color' => 'azerty',
                'expected' => '#FFFFFF'

            ]
        ];
    }

    /**
     * Test colors
     *
     * @param string $prop     Property to be set
     * @param string $color    Color to set
     * @param string $expected Expected color
     */
    #[DataProvider('colorsProvider')]
    public function testColors(string $prop, string $color, string $expected): void
    {
        $prop = 'pref_card_' . $prop;
        $this->preferences->$prop = $color;
        $this->assertSame($expected, $this->preferences->$prop);
    }

    /**
     * Test social networks
     */
    public function testSocials(): void
    {
        $preferences = [];
        foreach ($this->preferences->getDefaults() as $key => $value) {
            $preferences[$key] = $value;
        }

        $preferences = array_merge($preferences, [
            'pref_nom' => 'Galette',
            'pref_ville' => 'Avignon',
            'pref_cp' => '84000',
            'pref_adresse' => 'Palais des Papes',
            'pref_adresse2' => 'Au milieu',
            'pref_pays' => 'France'
        ]);

        //will create 2 social networks in table
        $post = [
            'notasocial' => 'notasocial', //must be ignored
            'social_new_type_1' => \Galette\Entity\Social::MASTODON,
            'social_new_value_1' => 'Galette mastodon URL',
            'social_new_type_2' => \Galette\Entity\Social::JABBER,
            'social_new_value_2' => 'Galette jabber ID',
            'social_new_type_3' => \Galette\Entity\Social::FACEBOOK,
            'social_new_value_3' => '', //empty value, no entry
            'social_new_type_4' => \Galette\Entity\Social::BLOG, //no value, no entry
        ];

        $post = array_merge($preferences, $post);

        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertTrue($this->preferences->store());

        $socials = \Galette\Entity\Social::getListForMember(null);
        $this->assertCount(2, $socials);

        $this->assertCount(
            1,
            \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON)
        );
        $this->assertCount(
            1,
            \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::JABBER)
        );
        $this->assertCount(
            0,
            \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::FACEBOOK)
        );
        $this->assertCount(
            0,
            \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::BLOG)
        );

        //create one new social network
        $post = [
            'social_new_type_1' => \Galette\Entity\Social::FACEBOOK,
            'social_new_value_1' => 'Galette does not have facebook',
        ];

        //existing social networks, change jabber ID
        foreach ($socials as $social) {
            $post['social_' . $social->id] = $social->url . ($social->type == \Galette\Entity\Social::JABBER ? ' - modified' : '');
        }

        $post = array_merge($preferences, $post);

        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertTrue($this->preferences->store());

        $socials = \Galette\Entity\Social::getListForMember(null);
        $this->assertCount(3, $socials);

        $search = \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON);
        $this->assertCount(1, $search);
        $masto = array_pop($search);
        $this->assertSame('Galette mastodon URL', $masto->url);

        $search = \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::JABBER);
        $this->assertCount(1, $search);
        $jabber = array_pop($search);
        $this->assertSame('Galette jabber ID - modified', $jabber->url);

        $search = \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::FACEBOOK);
        $this->assertCount(1, $search);
        $facebook = array_pop($search);
        $this->assertSame('Galette does not have facebook', $facebook->url);

        $post = [];

        //existing social networks, drop mastodon
        foreach ($socials as $social) {
            if ($social->type != \Galette\Entity\Social::MASTODON) {
                $post['social_' . $social->id] = $social->url;
            }
        }

        $post = array_merge($preferences, $post);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertTrue($this->preferences->store());

        $socials = \Galette\Entity\Social::getListForMember(null);
        $this->assertCount(2, $socials);

        $this->assertCount(
            0,
            \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON)
        );
        $this->assertCount(
            1,
            \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::JABBER)
        );
        $this->assertCount(
            1,
            \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::FACEBOOK)
        );

        $this->assertTrue(
            $this->preferences->check($preferences, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertTrue($this->preferences->store());
    }

    /**
     * Test email signature
     */
    public function testGetMailSignature(): void
    {
        $mail = new PHPMailer();
        $this->assertSame("\r\n-- \r\nGalette", $this->preferences->getMailSignature($mail));

        $this->preferences->pref_website = 'https://galette.eu';
        $this->assertSame(
            "\r\n-- \r\nGalette\r\n\r\nhttps://galette.eu",
            $this->preferences->getMailSignature($mail)
        );
        $this->assertSame(
            "\r\n-- \r\nGalette https://galette.eu",
            $this->preferences->getMailSignature($mail, true)
        );

        //with legacy values
        $this->preferences->pref_mail_sign = "{NAME}\r\n\r\n{WEBSITE}\r\n{FACEBOOK}\r\n{TWITTER}\r\n{LINKEDIN}\r\n{VIADEO}";
        $this->assertSame(
            "\r\n-- \r\nGalette\r\n\r\nhttps://galette.eu",
            $this->preferences->getMailSignature($mail)
        );

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('https://framapiaf.org/@galette')
                ->setLinkedMember(null)
                ->store()
        );
        $this->assertCount(
            1,
            \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON)
        );

        $this->preferences->pref_mail_sign = "{ASSO_NAME}\r\n\r\n{ASSO_WEBSITE} - {ASSO_SOCIAL_MASTODON}";
        $this->assertSame(
            "\r\n-- \r\nGalette\r\n\r\nhttps://galette.eu - https://framapiaf.org/@galette",
            $this->preferences->getMailSignature($mail)
        );

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('Galette mastodon URL - the return')
                ->setLinkedMember(null)
                ->store()
        );
        $this->assertCount(
            2,
            \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON)
        );
        $this->assertSame(
            "\r\n-- \r\nGalette\r\n\r\nhttps://galette.eu - https://framapiaf.org/@galette, Galette mastodon URL - the return",
            $this->preferences->getMailSignature($mail)
        );

        $this->preferences->pref_mail_sign = "{NAME}\r\n\r\n{ASSO_LOGO} <a href=\"{WEBSITE}\">our website</a>\r\n{FACEBOOK}\r\n{TWITTER}\r\n{LINKEDIN}\r\n{VIADEO}";
        $logo = new \Galette\Core\Logo();
        $this->assertSame(
            "\r\n-- \r\nGalette\r\n\r\n<img src=\"http:///logo\" width=\"" . $logo->getOptimalWidth() . "\" height=\"" . $logo->getOptimalHeight() . "\" alt=\"\" /> <a href=\"https://galette.eu\">our website</a>",
            $this->preferences->getMailSignature($mail)
        );
        $this->assertSame(
            "\r\n-- \r\nGalette (http:///logo) [our website](https://galette.eu)",
            $this->preferences->getMailSignature($mail, true)
        );
    }

    /**
     * Test getLegend
     */
    public function testGetLegend(): void
    {
        $legend = $this->preferences->getLegend();
        $this->assertCount(2, $legend);
        $this->assertCount(12, $legend['main']['patterns']);
        $this->assertCount(10, $legend['socials']['patterns']);
        $this->assertSame(
            [
                'title' => __('Mastodon'),
                'pattern' => '/{ASSO_SOCIAL_MASTODON}/'
            ],
            $legend['socials']['patterns']['asso_social_mastodon']
        );

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType('mynewtype')
                ->setUrl('Galette specific social network URL')
                ->setLinkedMember(null)
                ->store()
        );

        $legend = $this->preferences->getLegend();
        $this->assertCount(2, $legend);
        $this->assertCount(11, $legend['socials']['patterns']);
        $this->assertTrue(isset($legend['socials']['patterns']['asso_social_mynewtype']));
        $this->assertSame(
            [
                'title' => 'mynewtype',
                'pattern' => '/{ASSO_SOCIAL_MYNEWTYPE}/'
            ],
            $legend['socials']['patterns']['asso_social_mynewtype']
        );
    }

    /**
     * Test website URL
     */
    public function testWebsiteURL(): void
    {
        $preferences = [];
        foreach ($this->preferences->getDefaults() as $key => $value) {
            $preferences[$key] = $value;
        }

        $post = array_merge($preferences, ['pref_website' => 'https://galette.eu']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge($preferences, ['pref_website' => 'galette.eu']);
        $this->assertFalse(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertSame(['- Invalid website URL.'], $this->preferences->getErrors());
    }

    /**
     * Test updateTelemetryDate
     */
    public function testUpdateTelemetryDate(): void
    {
        $this->assertSame('', $this->preferences->pref_telemetry_date);
        $this->assertSame('Never', $this->preferences->getTelemetryDate());

        $this->preferences->updateTelemetryDate();
        $this->assertStringStartsWith(date('Y-m-d'), $this->preferences->pref_telemetry_date);
        $this->assertSame($this->preferences->pref_telemetry_date, $this->preferences->getTelemetryDate());
    }

    /**
     * Test updateRegistrationDate
     */
    public function testUpdateRegistrationDate(): void
    {
        $this->assertSame('', $this->preferences->pref_registration_date);
        $this->assertNull($this->preferences->getRegistrationDate());

        $this->preferences->updateRegistrationDate();
        $this->assertStringStartsWith(date('Y-m-d'), $this->preferences->pref_registration_date);
        $this->assertSame($this->preferences->pref_registration_date, $this->preferences->getRegistrationDate());
    }

    /**
     * Test generateUUID
     */
    public function testGenerateUUID(): void
    {
        $this->assertSame('', $this->preferences->pref_instance_uuid);
        $this->assertSame('', $this->preferences->pref_registration_uuid);
        $uuid = $this->preferences->generateUUID('instance');
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]{40}$/', $uuid);
        $this->assertSame($uuid, $this->preferences->pref_instance_uuid);
        $this->assertSame('', $this->preferences->pref_registration_uuid);
    }

    /**
     * Test for required end of membership parameter(s) presence and values
     */
    public function testRequiredEndOfMembership(): void
    {
        $preferences = [];
        foreach ($this->preferences->getDefaults() as $key => $value) {
            $preferences[$key] = $value;
        }

        $post = array_merge($preferences, ['pref_membership_ext' => null, 'pref_beg_membership' => null]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['- You must indicate a membership extension or a beginning of membership.'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_membership_ext' => '10', 'pref_beg_membership' => '01/01']);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['- Default membership extension and beginning of membership are mutually exclusive.'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_membership_ext' => 0, 'pref_beg_membership' => null]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['- Invalid number of months of membership extension.'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_membership_ext' => '10', 'pref_beg_membership' => null]);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge($preferences, ['pref_membership_ext' => null, 'pref_beg_membership' => '10']);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['- Invalid format of beginning of membership.'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_membership_ext' => null, 'pref_beg_membership' => '01/01']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge($preferences, ['pref_membership_ext' => '10', 'pref_beg_membership' => null, 'pref_membership_offermonths' => -1]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['- Invalid number of offered months.'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_membership_ext' => '10', 'pref_beg_membership' => null, 'pref_membership_offermonths' => 2]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['- Offering months is only compatible with beginning of membership.'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_membership_ext' => null, 'pref_beg_membership' => '01/01', 'pref_membership_offermonths' => 2]);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
    }

    /**
     * Test email related parameters
     */
    public function testEmailParameters(): void
    {
        $preferences = [];
        foreach ($this->preferences->getDefaults() as $key => $value) {
            $preferences[$key] = $value;
        }

        $post = array_merge($preferences, ['pref_email' => 'notvalid']);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['Invalid E-Mail address: notvalid'], $this->preferences->getErrors());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Invalid E-Mail address: notvalid');

        $post = array_merge($preferences, ['pref_email' => 'email@address.com']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertSame('email@address.com', $this->preferences->pref_email);

        $post = array_merge($preferences, ['pref_email' => 'email+me@address.com']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge($preferences, ['pref_email' => 'email-me@address.com']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge($preferences, ['pref_email' => 'email.me@address.com']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge($preferences, ['pref_email' => 'email@localhost']);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['Invalid E-Mail address: email@localhost'], $this->preferences->getErrors());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Invalid E-Mail address: email@localhost');

        //can be a coma separated value only for pref_email_newadh
        $post = array_merge($preferences, ['pref_email' => 'email@address.com,another@galette.eu']);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['Invalid E-Mail address: email@address.com,another@galette.eu'], $this->preferences->getErrors());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Invalid E-Mail address: email@address.com,another@galette.eu');


        $post = array_merge($preferences, ['pref_email_newadh' => 'email@address.com,another@galette.eu']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertSame('email@address.com', $this->preferences->pref_email_newadh);
        $this->assertSame(['email@address.com', 'another@galette.eu'], $this->preferences->vpref_email_newadh);

        $post = array_merge(
            $preferences,
            [
                'pref_mail_method' => \Galette\Core\GaletteMail::METHOD_DISABLED,
                'pref_email_nom' => null,
                'pref_email' => null,
            ]
        );
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge(
            $preferences,
            [
                'pref_mail_method' => \Galette\Core\GaletteMail::METHOD_PHPMAIL,
                'pref_email_nom' => null,
                'pref_email' => null,
            ]
        );
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(
            [
                '- You must indicate a sender name for emails!',
                '- You must indicate an email address Galette should use to send emails!',
            ],
            $this->preferences->getErrors()
        );

        $post = array_merge(
            $preferences,
            [
                'pref_mail_method' => \Galette\Core\GaletteMail::METHOD_DISABLED,
                'pref_email_nom' => 'G@l3tt3',
                'pref_email' => 'test@galette.eu',
            ]
        );
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge(
            $preferences,
            [
                'pref_mail_method' => \Galette\Core\GaletteMail::METHOD_SMTP,
                'pref_email_nom' => 'G@l3tt3',
                'pref_email' => 'test@galette.eu',
            ]
        );
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(
            [
                '- You must indicate the SMTP server you want to use!'
            ],
            $this->preferences->getErrors()
        );

        $post = array_merge(
            $preferences,
            [
                'pref_mail_method' => \Galette\Core\GaletteMail::METHOD_SMTP,
                'pref_email_nom' => 'G@l3tt3',
                'pref_email' => 'test@galette.eu',
                'pref_mail_smtp_host' => 'smtp.galette.eu',
            ]
        );
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge(
            $preferences,
            [
                'pref_mail_method' => \Galette\Core\GaletteMail::METHOD_SMTP,
                'pref_email_nom' => 'G@l3tt3',
                'pref_email' => 'test@galette.eu',
                'pref_mail_smtp_host' => 'smtp.galette.eu',
                'pref_mail_smtp_auth' => 1,
            ]
        );
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(
            [
                '- You must provide a login for SMTP authentication.',
                '- You must provide a password for SMTP authentication.'
            ],
            $this->preferences->getErrors()
        );

        $post = array_merge(
            $preferences,
            [
                'pref_mail_method' => \Galette\Core\GaletteMail::METHOD_GMAIL,
                'pref_email_nom' => 'G@l3tt3',
                'pref_email' => 'test@galette.eu',
            ]
        );
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(
            [
                '- You must provide a login for SMTP authentication.',
                '- You must provide a password for SMTP authentication.'
            ],
            $this->preferences->getErrors()
        );
    }

    /**
     * Test for required fields
     */
    public function testRequireds(): void
    {
        $preferences = [];
        foreach ($this->preferences->getDefaults() as $key => $value) {
            $preferences[$key] = $value;
        }

        $count_required = 17;
        $this->assertCount($count_required, $this->preferences->getRequiredFields($this->login));

        $post = array_merge($preferences, ['pref_admin_login' => null, 'pref_nom' => null]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['- Mandatory field pref_nom empty.'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_nom' => 'Galette']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $this->logSuperAdmin();
        $this->assertCount(++$count_required, $this->preferences->getRequiredFields($this->login));

        $post = array_merge($preferences, ['pref_admin_login' => null, 'pref_nom' => null]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(
            [
                '- Mandatory field pref_nom empty.',
                '- Mandatory field pref_admin_login empty.'
            ],
            $this->preferences->getErrors()
        );

        $post = array_merge($preferences, ['pref_admin_login' => null, 'pref_nom' => 'Galette']);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(
            [
                '- Mandatory field pref_admin_login empty.'
            ],
            $this->preferences->getErrors()
        );
    }

    /**
     * Test admin password check
     */
    public function testAdminPassCheck(): void
    {
        $preferences = [];
        foreach ($this->preferences->getDefaults() as $key => $value) {
            $preferences[$key] = $value;
        }

        $post = array_merge($preferences, ['pref_admin_pass' => 'one', 'pref_admin_pass_check' => 'another']);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['Passwords mismatch'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_admin_pass' => 'G@L3tt3', 'pref_admin_pass_check' => 'G@L3tt3']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
    }

    /**
     * Test postal address
     */
    public function testPostalAddress(): void
    {
        $preferences = [];
        foreach ($this->preferences->getDefaults() as $key => $value) {
            $preferences[$key] = $value;
        }

        $post = array_merge($preferences, ['pref_postal_address' => \Galette\Core\Preferences::POSTAL_ADDRESS_FROM_PREFS]);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge($preferences, ['pref_postal_address' => \Galette\Core\Preferences::POSTAL_ADDRESS_FROM_STAFF]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['You have to select a staff member to retrieve its address'], $this->preferences->getErrors());

        $memberOne = $this->getMemberOne();
        $post = array_merge(
            $preferences,
            [
                'pref_postal_address' => \Galette\Core\Preferences::POSTAL_ADDRESS_FROM_STAFF,
                'pref_postal_staff_member' => $memberOne->id
            ]
        );
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $expected = "DURAND René\nGalette association's Non-member\n66, boulevard De Oliveira\n39 069 Martel - Antarctique";
        $this->assertSame($expected, $this->preferences->getPostalAddress());
    }

    /**
     * Test phone number
     */
    public function testOrgPhone(): void
    {
        $preferences = [];
        foreach ($this->preferences->getDefaults() as $key => $value) {
            $preferences[$key] = $value;
        }

        $post = array_merge($preferences, ['pref_org_phone' => \Galette\Core\Preferences::PHONE_NUMBER_FROM_PREFS]);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $post = array_merge($preferences, ['pref_org_phone' => \Galette\Core\Preferences::PHONE_NUMBER_FROM_STAFF]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['You have to select a staff member to retrieve its phone number'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_org_phone' => \Galette\Core\Preferences::PHONE_NUMBER_MOBILE_FROM_STAFF]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['You have to select a staff member to retrieve its phone number'], $this->preferences->getErrors());

        $memberOne = $this->getMemberOne();
        $post = array_merge(
            $preferences,
            [
                'pref_org_phone' => \Galette\Core\Preferences::PHONE_NUMBER_FROM_STAFF,
                'pref_org_phone_staff_member' => $memberOne->id
            ]
        );
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $expected = '0439153432';
        $this->assertSame($expected, $this->preferences->getPhoneNumber());
    }

    /**
     * Test for admin login
     */
    public function testAdminLogin(): void
    {
        $preferences = [];
        foreach ($this->preferences->getDefaults() as $key => $value) {
            $preferences[$key] = $value;
        }

        //not superadmin, cannot change admin login nor password - ignored
        $post = array_merge($preferences, ['pref_admin_login' => 'abc']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );
        $this->assertSame('admin', $this->preferences->pref_admin_login);

        $this->logSuperAdmin();
        $post = array_merge($preferences, ['pref_admin_login' => 'abc']);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['- The username must be composed of at least 4 characters!'], $this->preferences->getErrors());

        $post = array_merge($preferences, ['pref_admin_login' => 'GSuperUser']);
        $this->assertTrue(
            $this->preferences->check($post, $this->login),
            print_r($this->preferences->getErrors(), true)
        );

        $memberOne = $this->getMemberOne();
        $post = array_merge($preferences, ['pref_admin_login' => $memberOne->login]);
        $this->assertFalse($this->preferences->check($post, $this->login));
        $this->assertSame(['- This username is already used by another member !'], $this->preferences->getErrors());
    }

    /**
     * Test __isset
     */
    public function testIsset(): void
    {
        $this->assertFalse(isset($this->preferences->defaults)); //@phpstan-ignore staticProperty.nonStaticAccess (expected to be false)
        $this->assertFalse(isset($this->preferences->pref_not_exists));
        $this->assertTrue(isset($this->preferences->pref_nom));
        $this->assertTrue(isset($this->preferences->vpref_email_newadh));
        $this->assertTrue(isset($this->preferences->socials));
    }
}
