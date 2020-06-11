<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Telemetry tests
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @category  Util
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-04-25
 */

namespace Galette\Util\test\units;

use atoum;
use Galette\Core\Preferences;

/**
 * Password tests class
 *
 * @category  Util
 * @name      Telemetry
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-04-25
 */
class Password extends atoum
{
    private $zdb;
    private $preferences;

    /**
     * Tear down tests
     *
     * @param string $method Method tested
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        $this->preferences->pref_password_strength = Preferences::PWD_NONE;
        $this->preferences->pref_password_length = 6;
        $this->preferences->pref_password_blacklist = false;
        $this->preferences->store();
        return parent::afterTestMethod($method);
    }

    /**
     * Set up tests
     *
     * @param string $testMethod Method tested
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->preferences = new \Galette\Core\Preferences($this->zdb);
    }

    /**
     * Passwords data provider
     *
     * @return array
     */
    protected function passProvider()
    {
        return [
            // [strength, password, errors]
            [Preferences::PWD_WEAK, 'weaker', ['A', '1', '@']],
            [Preferences::PWD_WEAK, '123456', ['nl', '@']],
            [Preferences::PWD_WEAK, '²²²²²²', ['nl', '@']],
            [Preferences::PWD_WEAK, 'foobar', ['A', '1', '@']],
            [Preferences::PWD_WEAK, 'ömgwat', ['A', '1', '@']],
            [Preferences::PWD_WEAK, '!.!.!.', ['nl', '1']],
            [Preferences::PWD_WEAK, '!.!.!﴾', ['nl', '1']],
            [Preferences::PWD_WEAK, '7857375923752947', ['nl', '@']],
            [Preferences::PWD_WEAK, 'FSDFJSLKFFSDFDSF', ['a', '1', '@']],
            [Preferences::PWD_WEAK, 'FÜKFJSLKFFSDFDSF', ['a', '1', '@']],
            [Preferences::PWD_WEAK, 'fjsfjdljfsjsjjlsj', ['A', '1', '@']],

            [Preferences::PWD_MEDIUM, 'wee6eak',['A', '@']],
            [Preferences::PWD_MEDIUM, 'foobar!', ['A', '1']],
            [Preferences::PWD_MEDIUM, 'Foobar', ['1', '@']],
            [Preferences::PWD_MEDIUM, '123456!', ['nl']],
            [Preferences::PWD_MEDIUM, 'fjsfjdljfsjsjjls1', ['A', '@']],
            [Preferences::PWD_MEDIUM, '785737592375294b', ['A', '@']],

            [Preferences::PWD_STRONG, 'Foobar﴾', ['1']],
            [Preferences::PWD_STRONG, 'foo-b0r!', ['A']],

            [Preferences::PWD_VERY_STRONG, 'Foobar!55!', []],
            [Preferences::PWD_VERY_STRONG, 'Foobar$55', []],
            [Preferences::PWD_VERY_STRONG, 'Foobar€55', []],
            [Preferences::PWD_VERY_STRONG, 'Foobar€55', []],
            [Preferences::PWD_VERY_STRONG, 'Foobar$55_4&F', []],
            [Preferences::PWD_VERY_STRONG, 'L33RoyJ3Jenkins!', []],
        ];
    }

    /**
     * Test password validation
     *
     * @dataProvider passProvider
     *
     * @param integer $level  Password level
     * @param string  $pass   Password
     * @param array   $errors Errors
     *
     * @return void
     */
    public function testValidatePassword($level, $pass, $errors)
    {
        //errror messages mapping
        foreach ($errors as &$err) {
            switch ($err) {
                case 'nl':
                    $err = 'Does not contains letters';
                    break;
                case 'a':
                    $err = 'Does not contains lowercase letters';
                    break;
                case 'A':
                    $err = 'Does not contains uppercase letters';
                    break;
                case 1:
                    $err = 'Does not contains numbers';
                    break;
                case '@':
                    $err = 'Does not contains special characters';
                    break;
            }
        }

        if ($level < Preferences::PWD_VERY_STRONG) {
            $this->preferences->pref_password_strength = $level + 1;
            $password = new \Galette\Util\Password($this->preferences);
            $this->boolean($password->isValid($pass))->isFalse();
            $this->array($password->getErrors())->isEqualTo($errors);
        }

        $this->preferences->pref_password_strength = $level;
        $password = new \Galette\Util\Password($this->preferences);
        $this->boolean($password->isValid($pass))->isTrue(implode(', ', $password->getErrors()));
        $this->array($password->getErrors())->isEqualTo([]);
        $this->array($password->getStrenghtErrors())->isEqualTo($errors);
    }

    /**
     * Blacklist password provider
     *
     * @return array
     */
    protected function blacklistProvider()
    {
        return [
            ['galette', true],
            ['toto',  false],
            ['mypassisgreat', false],
            ['starwars', true],
            ['123456', true],
            ['588795', false]
        ];
    }

    /**
     * Test password blacklist
     *
     * @dataProvider blacklistProvider
     *
     * @param string  $pass     Password to test
     * @param boolean $expected Excpected return
     *
     * @return void
     */
    public function testBlacklist($pass, $expected)
    {
        $this->preferences->pref_password_blacklist = true;
        $password = new \Galette\Util\Password($this->preferences);
        $this->boolean($password->isBlacklisted($pass))->isIdenticalTo($expected, $pass);

        $this->preferences->pref_password_blacklist = false;
        $password = new \Galette\Util\Password($this->preferences);
        $this->boolean($password->isBlacklisted($pass))->isFalse();
    }

    /**
     * Test with personal information
     *
     * @return void
     */
    public function testPersonalInformation()
    {
        $infos = [
            'login'     => 'mylogin',
            'name'      => 'myname',
            'surname'   => 'mysurname',
            'nickname'  => 'mynickname'
        ];

        $this->preferences->pref_password_strength = Preferences::PWD_NONE;
        $password = new \Galette\Util\Password($this->preferences);
        $password->addPersonalInformation($infos);
        foreach ($infos as $info) {
            $this->boolean($password->isValid($info))->isTrue(implode(', ', $password->getErrors()));
            $this->array($password->getErrors())->isEqualTo([]);
        }

        $this->preferences->pref_password_strength = Preferences::PWD_WEAK;
        $password = new \Galette\Util\Password($this->preferences);
        $password->addPersonalInformation($infos);
        foreach ($infos as $info) {
            $this->boolean($password->isValid($info))->isFalse();
            $this->array($password->getErrors())
                 ->isEqualTo(['Do not use any of your personal information as password!']);
        }

        $this->boolean($password->isValid('MyLoGiN'))->isFalse();
        $this->boolean($password->isValid('iMyLoGiN'))->isTrue();

        //create member
        global $zdb, $login, $i18n; // globals :(
        $zdb = $this->zdb;
        $session = new \RKA\Session();
        $i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $login = new \Galette\Core\Login($this->zdb, $i18n, $session);
        $history = new \Galette\Core\History($this->zdb, $login);
        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        $members_fields = $members_fields;

        $adh = new \Galette\Entity\Adherent($this->zdb);
        $adh->setDependencies(
            $this->preferences,
            $members_fields,
            $history
        );

        $adh_data = [
            'nom_adh'       => 'Pignon',
            'prenom_adh'    => 'Jean-Cloud Juste',
            'ddn_adh'       => '1980-05-01',
            'ville_adh'     => 'Paris',
            'pseudo_adh'    => 'petit-cheval-de-manège',
            'login_adh'     => 'log_In',
            'email_adh'     => 'mail@galette.eu',
            //required for check to work
            'date_crea_adh' => date('Y-m-d'),
            'sexe_adh'      => \Galette\Entity\Adherent::NC
        ];
        $check = $adh->check($adh_data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->boolean($check)->isTrue();

        $password = new \Galette\Util\Password($this->preferences);
        $password->setAdherent($adh);

        unset($adh_data['date_crea_adh']);
        unset($adh_data['sexe_adh']);
        //add compounds
        $adh_data['c00'] = 'jean-cloud justepignon';
        $adh_data['c000'] = 'pignonjean-cloud juste';
        $adh_data['c01'] = 'jcjpignon';
        $adh_data['c02'] = 'pignonjcj';
        $adh_data['c03'] = 'pignonj';
        $adh_data['c04'] = 'jpignon';

        $adh_data['c10'] = 'log_inpignon';
        $adh_data['c100'] = 'pignonlog_in';
        $adh_data['c11'] = 'pignonli';
        $adh_data['c12'] = 'lipignon';
        $adh_data['c13'] = 'lpignon';
        $adh_data['c14'] = 'pignonl';

        $adh_data['c20'] = 'petit-cheval-de-manègepignon';
        $adh_data['c200'] = 'pignonpetit-cheval-de-manège';
        $adh_data['c21'] = 'pignonpcdm';
        $adh_data['c22'] = 'pcdmpignon';
        $adh_data['c23'] = 'ppignon';
        $adh_data['c24'] = 'pignonp';

        foreach ($adh_data as $key => $data) {
            $this->boolean($password->isValid($data))->isFalse($key);
        }

        $this->boolean($password->isValid('19800501'))->isFalse();
        $this->boolean($password->isValid('01051980'))->isFalse();
    }
}
