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

use \atoum;

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
    private $session;
    private $login;

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
        $this->session = new \RKA\Session();
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n, $this->session);
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
     * Test (un)serialize
     *
     * @return void
     */
    public function testSerialize()
    {
        $this->login->name = 'Serialization test';
        $serialized = serialize($this->login);
        $this->string($serialized)->isNotEmpty();

        $login = unserialize($serialized);
        $this->object($login)->isInstanceOf('\Galette\Core\Login');
        $this->string($login->name)->isIdenticalTo('Serialization test');
    }

    /**
     * Test not logged in users Impersonating
     *
     * @return void
     */
    public function testNotLoggedCantImpersonate()
    {
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n, $this->session);

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
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n, $this->session);

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
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n, $this->session);
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

        $login = new \mock\Galette\Core\Login($zdb, $this->i18n, $this->session);
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
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n, $this->session);
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

        $login = new \Galette\Core\Login($zdb, $this->i18n, $this->session);
        $this->boolean($login->loginExists('doesnotexists'))->isTrue();
    }
}
