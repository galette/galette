<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Preferences tests
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-10-19
 */

namespace Galette\Core\test\units;

use \atoum;

/**
 * Preferences tests class
 *
 * @category  Core
 * @name      Preferences
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */
class Preferences extends atoum
{
    private $_preferences = null;
    private $_zdb;

    /**
     * Set up tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->_zdb = new \Galette\Core\Db();
        $this->_preferences = new \Galette\Core\Preferences(
            $this->_zdb
        );
    }

    /**
     * Test preferences initialization
     *
     * @return void
     */
    public function testInstallInit()
    {
        $result = $this->_preferences->installInit(
            'en_US',
            'da_admin',
            password_hash('da_secret', PASSWORD_BCRYPT)
        );
        $this->boolean($result)->isTrue();

        //new object with values loaded from database to compare
        $prefs = new \Galette\Core\Preferences($this->_zdb);

        foreach ( $prefs->getDefaults() as $key=>$expected  ) {
            $value = $prefs->$key;

            switch ( $key ) {
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
                if ( TYPE_DB === \Galette\Core\Db::PGSQL ) {
                    if ( $value === 'f' ) {
                        $value = false;
                    }
                }
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

        $this->_preferences->pref_nom = 'Galette';
        $this->_preferences->pref_ville = 'Avignon';
        $this->_preferences->pref_cp = '84000';
        $this->_preferences->pref_adresse = 'Palais des Papes';
        $this->_preferences->pref_adresse2 = 'Au milieu';
        $this->_preferences->pref_pays = 'France';

        $expected = "Galette\n\nPalais des Papes\nAu milieu\n84000 Avignon - France";
        $address = $this->_preferences->getPostalAdress();

        $this->variable($address)->isIdenticalTo($expected);

        $slogan = $this->_preferences->pref_slogan;
        $this->variable($slogan)->isEqualTo('');

        $slogan = 'One Galette to rule them all';
        $this->_preferences->pref_slogan = $slogan;
        $result = $this->_preferences->store();

        $this->boolean($result)->isTrue();

        $prefs = new \Galette\Core\Preferences($this->_zdb);
        $check_slogan = $prefs->pref_slogan;
        $this->variable($check_slogan)->isEqualTo($slogan);

        //reset database value...
        $this->_preferences->pref_slogan = '';
        $this->_preferences->store();
    }

    /**
     * Test fields names
     *
     * @return void
     */
    public function testFieldsNames()
    {
        $this->_preferences->load();
        $fields_names = $this->_preferences->getFieldsNames();
        $expected = array_keys($this->_preferences->getDefaults());

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
        $del = $this->_zdb->db->delete(
            PREFIX_DB . \Galette\Core\Preferences::TABLE,
            $this->_zdb->db->quoteInto(
                \Galette\Core\Preferences::PK . ' = ?',
                'pref_facebook'
            )
        );
        $del = $this->_zdb->db->delete(
            PREFIX_DB . \Galette\Core\Preferences::TABLE,
            $this->_zdb->db->quoteInto(
                \Galette\Core\Preferences::PK . ' = ?',
                'pref_viadeo'
            )
        );

        $this->_preferences->load();
        $fb = $this->_preferences->pref_facebook;
        $viadeo = $this->_preferences->pref_viadeo;

        $this->boolean($fb)->isFalse();
        $this->boolean($viadeo)->isFalse();

        $prefs = new \Galette\Core\Preferences($this->_zdb);
        $fb = $prefs->pref_facebook;
        $viadeo = $prefs->pref_viadeo;

        $this->variable($fb)->isIdenticalTo('');
        $this->variable($viadeo)->isIdenticalTo('');
    }
}
