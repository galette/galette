<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Login tests
 *
 * PHP version 5
 *
 * Copyright © 2016 The Galette Team
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
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2016-12-05
 */

namespace Galette\Core\test\units;

use atoum;

/**
 * Login tests class
 *
 * @category  Core
 * @name      Login
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2016 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2016-12-05
 */
class Login extends atoum
{
    private $zdb;
    private $i18n;
    private $login;
    private $preferences;
    private $seed = 320112365;
    private $login_adh = 'dumas.roger';
    private $mdp_adh = 'sd8)AvtE|*';

    private $members_fields;
    private $history;
    private $adh;

    /**
     * Cleanup after tests
     *
     * @return void
     */
    public function tearDown()
    {
        $this->zdb = new \Galette\Core\Db();
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Set up tests
     *
     * @param string $testMethod Method name
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->i18n = new \Galette\Core\I18n();
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n);
        $this->preferences = new \Galette\Core\Preferences(
            $this->zdb
        );
        $this->history = new \Galette\Core\History($this->zdb, $this->login, $this->preferences);

        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        $this->members_fields = $members_fields;
    }

    /**
     * Test defaults
     *
     * @return void
     */
    public function testDefaults()
    {
        $this->boolean($this->login->isLogged())->isFalse();
        $this->boolean($this->login->isStaff())->isFalse();
        $this->boolean($this->login->isAdmin())->isFalse();
        $this->boolean($this->login->isSuperAdmin())->isFalse();
        $this->boolean($this->login->isActive())->isFalse();
        $this->boolean($this->login->isCron())->isFalse();
        $this->boolean($this->login->isUp2Date())->isFalse();
        $this->boolean($this->login->isImpersonated())->isFalse();
    }

    /**
     * Test not logged in users Impersonating
     *
     * @return void
     */
    public function testNotLoggedCantImpersonate()
    {
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);

        $this->calling($login)->isLogged = false;
        $this
            ->exception(
                function () use ($login) {
                    $login->impersonate(1);
                }
            )->hasMessage('Only superadmin can impersonate!');
    }

    /**
     * Test staff users Impersonating
     *
     * @return void
     */
    public function testStaffCantImpersonate()
    {
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);

        $this->calling($login)->isLogged = true;
        $this->calling($login)->isStaff = true;
        $this->calling($login)->isAdmin = false;
        $this->calling($login)->isSuperAdmin = false;
        $this
            ->exception(
                function () use ($login) {
                    $login->impersonate(1);
                }
            )->hasMessage('Only superadmin can impersonate!');
    }

    /**
     * Test admin users Impersonating
     *
     * @return void
     */
    public function testAdminCantImpersonate()
    {
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);
        $this->calling($login)->isLogged = true;
        $this->calling($login)->isStaff = true;
        $this->calling($login)->isAdmin = true;
        $this->calling($login)->isSuperAdmin = false;
        $this
            ->exception(
                function () use ($login) {
                    $login->impersonate(1);
                }
            )->hasMessage('Only superadmin can impersonate!');
    }

    /**
     * Test Impersonating that throws an exception
     *
     * @return void
     */
    public function testImpersonateExistsWException()
    {
        $zdb = new \mock\Galette\Core\Db();
        $this->calling($zdb)->execute = function ($o) {
            if ($o instanceof \Zend\Db\Sql\Select) {
                throw new \LogicException('Error executing query!', 123);
            }
        };

        $login = new \mock\Galette\Core\Login($zdb, $this->i18n);
        $this->calling($login)->isSuperAdmin = true;
        $this->boolean($login->impersonate(1))->isFalse();
    }

    /**
     * Test superadmin users Impersonating
     *
     * @return void
     */
    public function testSuperadminCanImpersonate()
    {
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);
        $this->calling($login)->isSuperAdmin = true;

        ///We're faking, Impersonating won't work but will not throw any exception
        $this->boolean($login->impersonate(1))->isFalse();
    }

    /**
     * Test return requesting an inexisting property
     *
     * @return void
     */
    public function testInexistingGetter()
    {
        $this->boolean($this->login->doesnotexists)->isFalse();
    }

    /**
     * Test login exists
     *
     * @return void
     */
    public function testLoginExists()
    {
        $this->boolean($this->login->loginExists('exists'))->isFalse();
        $this->boolean($this->login->loginExists('doesnotexists'))->isFalse();
    }

    /**
     * Test login exists that throws an exception
     *
     * @return void
     */
    public function testLoginExistsWException()
    {
        $zdb = new \mock\Galette\Core\Db();
        $this->calling($zdb)->execute = function ($o) {
            if ($o instanceof \Zend\Db\Sql\Select) {
                throw new \LogicException('Error executing query!', 123);
            }
        };

        $login = new \Galette\Core\Login($zdb, $this->i18n);
        $this->boolean($login->loginExists('doesnotexists'))->isTrue();
    }

    /**
     * Test login as super admin
     *
     * @return void
     */
    public function testLogAdmin()
    {
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isStaff())->isFalse();
        $this->boolean($this->login->isAdmin())->isTrue();
        $this->boolean($this->login->isSuperAdmin())->isTrue();
        $this->boolean($this->login->isActive())->isTrue();
        $this->boolean($this->login->isCron())->isFalse();
        $this->boolean($this->login->isUp2Date())->isFalse();
        $this->boolean($this->login->isImpersonated())->isFalse();

        //test logout
        $this->login->logOut();
        $this->testDefaults();
    }

    /**
     * Creates or load test user
     *
     * @return void
     */
    private function createUser()
    {
        $select = $this->zdb->select(\Galette\Entity\Adherent::TABLE, 'a');
        $select->where(array('a.fingerprint' => 'FAKER' . $this->seed));
        $results = $this->zdb->execute($select);

        global $zdb, $login, $hist, $i18n; // globals :(
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;

        if ($results->count() === 0) {
            $status = new \Galette\Entity\Status($this->zdb);
            if (count($status->getList()) === 0) {
                $res = $status->installInit();
                $this->boolean($res)->isTrue();
            }

            $data = [
                'nom_adh' => 'Barre',
                'prenom_adh' => 'Olivier',
                'ville_adh' => 'Le GoffVille',
                'cp_adh' => '05 029',
                'adresse_adh' => '9, impasse Frédérique Boulanger',
                'email_adh' => 'bernadette37@hernandez.fr',
                'login_adh' => 'dumas.roger',
                'mdp_adh' => 'sd8)AvtE|*',
                'mdp_adh2' => 'sd8)AvtE|*',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Pédologue',
                'titre_adh' => null,
                'ddn_adh' => '23/10/1948',
                'lieu_naissance' => 'Lagarde',
                'pseudo_adh' => 'elisabeth50',
                'pays_adh' => 'Géorgie',
                'tel_adh' => '05 05 20 88 04',
                'url_adh' => 'http://www.gay.com/tempora-nemo-quidem-laudantium-dolores',
                'activite_adh' => true,
                'id_statut' => 6,
                'date_crea_adh' => '02/09/2019',
                'pref_lang' => 'nb_NO',
                'fingerprint' => 'FAKER' . $this->seed,
            ];

            $this->adh = new \Galette\Entity\Adherent($this->zdb);
            $this->adh->setDependencies(
                $this->preferences,
                $this->members_fields,
                $this->history
            );

            $check = $this->adh->check($data, [], []);
            if (is_array($check)) {
                var_dump($check);
            }
            $this->boolean($check)->isTrue();

            $store = $this->adh->store();
            $this->boolean($store)->isTrue();
        } else {
            $this->adh = new \Galette\Entity\Adherent($this->zdb, $results->current());
        }
    }

    /**
     * Look for a login that does exists
     *
     * @return void
     */
    public function testLoginExistsDb()
    {
        $this->createUser();
        $this->boolean($this->login->loginExists($this->login))->isTrue();
    }

    /**
     * Test user login
     *
     * @return void
     */
    public function testLogin()
    {
        $this->createUser();
        $this->boolean($this->login->login('doenotexists', 'empty'))->isFalse();
        $this->boolean($this->login->login($this->login_adh, $this->mdp_adh))->isTrue();
    }

    /**
     * Test logged user name
     *
     * @return void
     */
    public function testLoggedInAs()
    {
        global $translator;

        $this->createUser();
        $this->boolean($this->login->login($this->login_adh, $this->mdp_adh))->isTrue();

        /** Should get message in the right locale but doesn't... */
        $this->i18n->changeLanguage('en_US');
        $tstring = $translator->translate(
            "Logged in as:<br/>%login",
            'galette',
            $this->login->lang
        );
        $this->string($this->login->loggedInAs())->isIdenticalTo(
            str_replace(
                '%login',
                'Barre Olivier (dumas.roger)',
                $tstring
            )
        );
        $this->string($this->login->loggedInAs(true))->isIdenticalTo('Barre Olivier (dumas.roger)');
    }

    /**
     * Test login from cron
     *
     * @return void
     */
    public function testLogCron()
    {
        $this->login->logCron('reminder');
        $this->boolean($this->login->isLogged())->isTrue();
        $this->boolean($this->login->isStaff())->isFalse();
        $this->boolean($this->login->isAdmin())->isFalse();
        $this->boolean($this->login->isSuperAdmin())->isFalse();
        $this->boolean($this->login->isActive())->isFalse();
        $this->boolean($this->login->isCron())->isTrue();
        $this->boolean($this->login->isUp2Date())->isFalse();
        $this->boolean($this->login->isImpersonated())->isFalse();
        $this->string($this->login->login)->isIdenticalTo('cron');

        $this->when(
            function () {
                $this->login->logCron('filename');
            }
        )->error()
            ->withMessage('Not authorized!')
            ->withType(E_USER_ERROR)
            ->exists();
    }
}
