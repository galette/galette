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

    /**
     * Set up tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->preferences = new \Galette\Core\Preferences(
            $this->zdb
        );
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
                \Galette\Core\Preferences::PK => 'pref_facebook'
            )
        );
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Core\Preferences::TABLE);
        $delete->where(
            array(
                \Galette\Core\Preferences::PK => 'pref_viadeo'
            )
        );
        $this->zdb->execute($delete);

        $this->preferences->load();
        $fb = $this->preferences->pref_facebook;
        $viadeo = $this->preferences->pref_viadeo;

        $this->boolean($fb)->isFalse();
        $this->boolean($viadeo)->isFalse();

        $prefs = new \Galette\Core\Preferences($this->zdb);
        $fb = $prefs->pref_facebook;
        $viadeo = $prefs->pref_viadeo;

        $this->variable($fb)->isIdenticalTo('');
        $this->variable($viadeo)->isIdenticalTo('');
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
}
