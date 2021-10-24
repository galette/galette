<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Preferences tests
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-10-19
 */

namespace Galette\Core\test\units;

use atoum;

/**
 * Preferences tests class
 *
 * @category  Core
 * @name      Preferences
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */
class Preferences extends atoum
{
    private $preferences = null;
    private $zdb;
    private $login;
    private $mocked_router;

    /**
     * Set up tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->mocked_router = new \mock\Slim\Router();
        $this->calling($this->mocked_router)->pathFor = function ($name, $params) {
            return $name;
        };

        $app =  new \Galette\Core\SlimApp();
        $plugins = new \Galette\Core\Plugins();
        require GALETTE_BASE_PATH . '/includes/dependencies.php';
        $container = $app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $this->zdb = $container->get('zdb');
        $this->login = $container->get('login');
        $this->preferences = $container->get('preferences');
        $container->set('router', $this->mocked_router);
        $container->set(Slim\Router::class, $this->mocked_router);

        global $router;
        $router = $this->mocked_router;
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        if (TYPE_DB === 'mysql') {
            $this->array($this->zdb->getWarnings())->isIdenticalTo([]);
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
        $this->boolean($result)->isTrue();

        //new object with values loaded from database to compare
        $prefs = new \Galette\Core\Preferences($this->zdb);

        foreach ($prefs->getDefaults() as $key => $expected) {
            $value = $prefs->$key;

            switch ($key) {
                case 'pref_admin_login':
                    $this->variable($value)->isIdenticalTo('da_admin');
                    break;
                case 'pref_admin_pass':
                    $pw_checked = password_verify('da_secret', $value);
                    $this->boolean($pw_checked)->isTrue();
                    break;
                case 'pref_lang':
                    $this->variable($value)->isIdenticalTo('en_US');
                    break;
                case 'pref_card_year':
                    $this->variable($value)->isIdenticalTo(date('Y'));
                    break;
                default:
                    $this->variable($value)->isEqualTo($expected);
                    break;
            }
        }

        //tru to set and get a non existent value
        $prefs->doesnotexists = 'that *does* not exists.';
        $false_result = $prefs->doesnotexists;
        $this->boolean($false_result)->isFalse();

        //change slogan
        $slogan = 'One Galette to rule them all';
        $prefs->pref_slogan = $slogan;
        $check = $prefs->pref_slogan;
        $this->string($check)->isIdenticalTo($slogan);

        //change password
        $new_pass = 'anoth3er_s3cr3t';
        $prefs->pref_admin_pass = $new_pass;
        $pass = $prefs->pref_admin_pass;
        $pw_checked = password_verify($new_pass, $pass);
        $this->boolean($pw_checked)->isTrue();

        $this->preferences->pref_nom = 'Galette';
        $this->preferences->pref_ville = 'Avignon';
        $this->preferences->pref_cp = '84000';
        $this->preferences->pref_adresse = 'Palais des Papes';
        $this->preferences->pref_adresse2 = 'Au milieu';
        $this->preferences->pref_pays = 'France';

        $expected = "Galette\nPalais des Papes\nAu milieu\n84000 Avignon - France";
        $address = $this->preferences->getPostalAddress();

        $this->variable($address)->isIdenticalTo($expected);

        $slogan = $this->preferences->pref_slogan;
        $this->variable($slogan)->isEqualTo('');

        $slogan = 'One Galette to rule them all';
        $this->preferences->pref_slogan = $slogan;
        $result = $this->preferences->store();

        $this->boolean($result)->isTrue();

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $check_slogan = $prefs->pref_slogan;
        $this->variable($check_slogan)->isEqualTo($slogan);

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

        $this->array($fields_names)->isIdenticalTo($expected);
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

        $this->boolean($footer)->isFalse();
        $this->boolean($new_contrib_script)->isFalse();

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $footer = $prefs->pref_footer;
        $new_contrib_script = $prefs->pref_new_contrib_script;

        $this->variable($footer)->isIdenticalTo('');
        $this->variable($new_contrib_script)->isIdenticalTo('');
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
        $this->variable($visibility)->isEqualTo(
            \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED
        );

        $anon_login = new \Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );

        $admin_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($admin_login)->isAdmin = true;

        $user_login = new \mock\Galette\Core\Login(
            $this->zdb,
            new \Galette\Core\I18n()
        );
        $this->calling($user_login)->isUp2Date = true;

        $visible = $this->preferences->showPublicPages($anon_login);
        $this->boolean($visible)->isFalse();

        $visible = $this->preferences->showPublicPages($admin_login);
        $this->boolean($visible)->isTrue();

        $visible = $this->preferences->showPublicPages($user_login);
        $this->boolean($visible)->isTrue();

        $this->preferences->pref_publicpages_visibility
            = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PUBLIC;

        $visible = $this->preferences->showPublicPages($anon_login);
        $this->boolean($visible)->isTrue();

        $visible = $this->preferences->showPublicPages($admin_login);
        $this->boolean($visible)->isTrue();

        $visible = $this->preferences->showPublicPages($user_login);
        $this->boolean($visible)->isTrue();

        $this->preferences->pref_publicpages_visibility
            = \Galette\Core\Preferences::PUBLIC_PAGES_VISIBILITY_PRIVATE;

        $visible = $this->preferences->showPublicPages($anon_login);
        $this->boolean($visible)->isFalse();

        $visible = $this->preferences->showPublicPages($admin_login);
        $this->boolean($visible)->isTrue();

        $visible = $this->preferences->showPublicPages($user_login);
        $this->boolean($visible)->isFalse();
    }

    /**
     * Data provider for cards sizes tests
     *
     * @return array
     */
    protected function sizesProvider()
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
        $this->array($this->preferences->checkCardsSizes())->hasSize($count);
    }

    /**
     * Data provider for colors
     *
     * @return array
     */
    protected function colorsProvider(): array
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
        $this->string($this->preferences->$prop)->isIdenticalTo($expected);
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

        $this->boolean($this->preferences->check($post, $this->login))->isTrue(print_r($this->preferences->getErrors(), true));
        $this->boolean($this->preferences->store())->isTrue();

        $socials = \Galette\Entity\Social::getListForMember(null);
        $this->array($socials)->hasSize(2);

        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON))->hasSize(1);
        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::JABBER))->hasSize(1);
        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::FACEBOOK))->hasSize(0);
        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::BLOG))->hasSize(0);

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

        $this->boolean($this->preferences->check($post, $this->login))->isTrue(print_r($this->preferences->getErrors(), true));
        $this->boolean($this->preferences->store())->isTrue();

        $socials = \Galette\Entity\Social::getListForMember(null);
        $this->array($socials)->hasSize(3);

        $search = \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON);
        $this->array($search)->hasSize(1);
        $masto = array_pop($search);
        $this->string($masto->url)->isIdenticalTo('Galette mastodon URL');

        $search = \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::JABBER);
        $this->array($search)->hasSize(1);
        $jabber = array_pop($search);
        $this->string($jabber->url)->isIdenticalTo('Galette jabber ID - modified');

        $search = \Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::FACEBOOK);
        $this->array($search)->hasSize(1);
        $facebook = array_pop($search);
        $this->string($facebook->url)->isIdenticalTo('Galette does not have facebook');

        $post = [];

        //existing social networks, drop mastodon
        foreach ($socials as $social) {
            if ($social->type != \Galette\Entity\Social::MASTODON) {
                $post['social_' . $social->id] = $social->url;
            }
        }

        $post = array_merge($preferences, $post);
        $this->boolean($this->preferences->check($post, $this->login))->isTrue(print_r($this->preferences->getErrors(), true));
        $this->boolean($this->preferences->store())->isTrue();

        $socials = \Galette\Entity\Social::getListForMember(null);
        $this->array($socials)->hasSize(2);

        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON))->hasSize(0);
        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::JABBER))->hasSize(1);
        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::FACEBOOK))->hasSize(1);
    }

    /**
     * Test email signature
     *
     * @return void
     */
    public function testGetMailSignature()
    {
        $this->string($this->preferences->getMailSignature())->isIdenticalTo("\r\n-- \r\nGalette\r\n\r\n");

        $this->preferences->pref_website = 'https://galette.eu';
        $this->string($this->preferences->getMailSignature())->isIdenticalTo("\r\n-- \r\nGalette\r\n\r\nhttps://galette.eu");

        //with legacy values
        $this->preferences->pref_mailsign = "NAME}\r\n\r\n{WEBSITE}\r\n{GOOGLEPLUS}\r\n{FACEBOOK}\r\n{TWITTER}\r\n{LINKEDIN}\r\n{VIADEO}";
        $this->string($this->preferences->getMailSignature())->isIdenticalTo("\r\n-- \r\nGalette\r\n\r\nhttps://galette.eu");

        $social = new \Galette\Entity\Social($this->zdb);
        $this->boolean(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('https://framapiaf.org/@galette')
                ->setLinkedMember(null)
                ->store()
        )->isTrue();
        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON))->hasSize(1);

        $this->preferences->pref_mail_sign = "{ASSO_NAME}\r\n\r\n{ASSO_WEBSITE} - {ASSO_SOCIAL_MASTODON}";
        $this->string($this->preferences->getMailSignature())->isIdenticalTo("\r\n-- \r\nGalette\r\n\r\nhttps://galette.eu - https://framapiaf.org/@galette");

        $social = new \Galette\Entity\Social($this->zdb);
        $this->boolean(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('Galette mastodon URL - the return')
                ->setLinkedMember(null)
                ->store()
        )->isTrue();
        $this->array(\Galette\Entity\Social::getListForMember(null, \Galette\Entity\Social::MASTODON))->hasSize(2);
        $this->string($this->preferences->getMailSignature())->isIdenticalTo("\r\n-- \r\nGalette\r\n\r\nhttps://galette.eu - https://framapiaf.org/@galette, Galette mastodon URL - the return");
    }

    /**
     * Test getLegend
     *
     * @return void
     */
    public function testGetLegend()
    {
        $legend = $this->preferences->getLegend();
        $this->array($legend)->hasSize(2);
        $this->array($legend['main']['patterns'])->hasSize(8);
        $this->array($legend['socials']['patterns'])->hasSize(9);
        $this->array($legend['socials']['patterns']['asso_social_mastodon'])->isIdenticalTo([
            'title' => __('Mastodon'),
            'pattern' => '/{ASSO_SOCIAL_MASTODON}/'
        ]);

        $social = new \Galette\Entity\Social($this->zdb);
        $this->boolean(
            $social
                ->setType('mynewtype')
                ->setUrl('Galette specific social network URL')
                ->setLinkedMember(null)
                ->store()
        )->isTrue();

        $legend = $this->preferences->getLegend();
        $this->array($legend)->hasSize(2);
        $this->array($legend['socials']['patterns'])->hasSIze(10)->hasKey('asso_social_mynewtype');
        $this->array($legend['socials']['patterns']['asso_social_mynewtype'])->isIdenticalTo([
            'title' => 'mynewtype',
            'pattern' => '/{ASSO_SOCIAL_MYNEWTYPE}/'
        ]);
    }
}
