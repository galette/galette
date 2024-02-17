<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Galette\Core\test\units;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

/**
 * Preferences tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Preferences extends TestCase
{
    private ?\Galette\Core\Preferences $preferences = null;
    private \Galette\Core\Db $zdb;
    private \Galette\Core\Login $login;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $gapp =  new \Galette\Core\SlimApp();
        $app = $gapp->getApp();
        $app->addRoutingMiddleware();

        $plugins = new \Galette\Core\Plugins();
        require GALETTE_BASE_PATH . '/includes/dependencies.php';
        $container = $app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $this->zdb = $container->get('zdb');
        $this->login = $container->get('login');
        $this->preferences = $container->get('preferences');

        global $routeparser;
        $routeparser = $container->get(\Slim\Routing\RouteParser::class);

        $authenticate = new \Galette\Middleware\Authenticate($container);
        require GALETTE_ROOT . 'includes/routes/main.routes.php';
        require GALETTE_ROOT . 'includes/routes/authentication.routes.php';
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }

        $delete = $this->zdb->delete(\Galette\Entity\Social::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Test preferences initialization
     *
     * @return void
     */
    public function testInstallInit()
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
                    $pw_checked = password_verify('da_secret', $value);
                    $this->assertTrue($pw_checked);
                    break;
                case 'pref_lang':
                    $this->assertSame('en_US', $value);
                    break;
                case 'pref_card_year':
                    $this->assertSame(date('Y'), $value);
                    break;
                default:
                    $this->assertEquals($expected, $value);
                    break;
            }
        }

        //tru to set and get a non existent value
        $prefs->doesnotexists = 'that *does* not exists.';
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
     * Test fields names
     *
     * @return void
     */
    public function testFieldsNames()
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
     *
     * @return void
     */
    public function testUpdate()
    {
        $delete = $this->zdb->delete(\Galette\Core\Preferences::TABLE);
        $delete->where(
            array(
                \Galette\Core\Preferences::PK => 'pref_footer'
            )
        );
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Core\Preferences::TABLE);
        $delete->where(
            array(
                \Galette\Core\Preferences::PK => 'pref_new_contrib_script'
            )
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
     *
     * @return void
     */
    public function testPublicPagesVisibility()
    {
        $this->preferences->load();

        $visibility = $this->preferences->pref_publicpages_visibility;
        $this->assertEquals(
            \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED,
            $visibility
        );

        $anon_login = new \Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );

        $admin_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isAdmin'))
            ->getMock();
        $admin_login->method('isAdmin')->willReturn(true);

        $user_login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, new \Galette\Core\I18n()))
            ->onlyMethods(array('isUp2Date'))
            ->getMock();
        $user_login->method('isUp2Date')->willReturn(true);

        $visible = $this->preferences->showPublicPages($anon_login);
        $this->assertFalse($visible);

        $visible = $this->preferences->showPublicPages($admin_login);
        $this->assertTrue($visible);

        $visible = $this->preferences->showPublicPages($user_login);
        $this->assertTrue($visible);

        $this->preferences->pref_publicpages_visibility
            = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;

        $visible = $this->preferences->showPublicPages($anon_login);
        $this->assertTrue($visible);

        $visible = $this->preferences->showPublicPages($admin_login);
        $this->assertTrue($visible);

        $visible = $this->preferences->showPublicPages($user_login);
        $this->assertTrue($visible);

        $this->preferences->pref_publicpages_visibility
            = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE;

        $visible = $this->preferences->showPublicPages($anon_login);
        $this->assertFalse($visible);

        $visible = $this->preferences->showPublicPages($admin_login);
        $this->assertTrue($visible);

        $visible = $this->preferences->showPublicPages($user_login);
        $this->assertFalse($visible);
    }

    /**
     * Data provider for cards sizes tests
     *
     * @return array
     */
    public static function sizesProvider()
    {
        return [
            [//defaults
                15, //vertical margin
                20, //horizontal margin
                5,  //vertical spacing
                10, //horizontal spacing
                0   //expected number of warnings
            ], [ //OK
                0,  //vertical margin
                20, //horizontal margin
                11, //vertical spacing
                10, //horizontal spacing
                0   //expected number of warnings
            ], [ //vertical overflow
                0,  //vertical margin
                20, //horizontal margin
                12, //vertical spacing
                10, //horizontal spacing
                1   //expected number of warnings
            ], [//horizontal overflow
                15, //vertical margin
                20, //horizontal margin
                5,  //vertical spacing
                61, //horizontal spacing
                1   //expected number of warnings
            ], [//vertical and horizontal overflow
                0,  //vertical margin
                20, //horizontal margin
                12, //vertical spacing
                61, //horizontal spacing
                2   //expected number of warnings
            ], [//vertical overflow
                17, //vertical margin
                20, //horizontal margin
                5,  //vertical spacing
                10, //horizontal spacing
                1   //expected number of warnings
            ]
        ];
    }

    /**
     * Test checkCardsSizes
     *
     * @dataProvider sizesProvider
     *
     * @param integer $vm    Vertical margin
     * @param integer $hm    Horizontal margin
     * @param integer $vs    Vertical spacing
     * @param integer $hs    Horizontal spacing
     * @param integer $count Number of expected errors
     *
     * @return void
     */
    public function testCheckCardsSizes($vm, $hm, $vs, $hs, $count)
    {
        $this->preferences->pref_card_marges_v = $vm;
        $this->preferences->pref_card_marges_h = $hm;
        $this->preferences->pref_card_vspace = $vs;
        $this->preferences->pref_card_hspace = $hs;
        $this->assertCount($count, $this->preferences->checkCardsSizes());
    }

    /**
     * Data provider for colors
     *
     * @return array
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
     * @dataProvider colorsProvider
     *
     * @param string $prop     Property to be set
     * @param string $color    Color to set
     * @param string $expected Expected color
     *
     * @return void
     */
    public function testColors($prop, $color, $expected)
    {
        $prop = 'pref_card_' . $prop;
        $this->preferences->$prop = $color;
        $this->assertSame($expected, $this->preferences->$prop);
    }

    /**
     * Test social networks
     *
     * @return void
     */
    public function testSocials()
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
    }

    /**
     * Test email signature
     *
     * @return void
     */
    public function testGetMailSignature()
    {
        $mail = new PHPMailer();
        $this->assertSame("\r\n-- \r\nGalette", $this->preferences->getMailSignature($mail));

        $this->preferences->pref_website = 'https://galette.eu';
        $this->assertSame(
            "\r\n-- \r\nGalette\r\n\r\nhttps://galette.eu",
            $this->preferences->getMailSignature($mail)
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
    }

    /**
     * Test getLegend
     *
     * @return void
     */
    public function testGetLegend()
    {
        $legend = $this->preferences->getLegend();
        $this->assertCount(2, $legend);
        $this->assertCount(9, $legend['main']['patterns']);
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
     *
     * @return void
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
}
