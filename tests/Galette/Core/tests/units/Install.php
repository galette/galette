<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Install tests
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
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
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2014-01-03
 */

namespace Galette\Core\test\units;

use \atoum;

/**
 * Install tests class
 *
 * @category  Core
 * @name      Db
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2014-01-03
 */
class Install extends atoum
{
    private $_install;

    /**
     * Set up tests
     *
     * @param stgring $testMethod Method tested
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        setlocale(LC_ALL, 'en_US');
        $this->_install = new \Galette\Core\Install();
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor()
    {
        $install = new \Galette\Core\Install();

        $step = $install->isCheckStep();
        $this->boolean($step)->isTrue();

        $mode = $install->getMode();
        $this->variable($mode)->isNull();

        $is_install = $install->isInstall();
        $this->boolean($is_install)->isFalse();

        $is_upgrade = $install->isUpgrade();
        $this->boolean($is_upgrade)->isFalse();

        $connected = $install->isDbConnected();
        $this->boolean($connected)->isFalse();

        $title = $install->getStepTitle();
        $this->string($title)->isIdenticalTo('Checks');
    }

    /**
     * Tests update scripts list
     *
     * @return void
     */
    public function testGetUpgradeScripts()
    {
        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            'pgsql',
            '0.6'
        );

        $knowns = array(
            '0.60' => 'upgrade-to-0.60-pgsql.sql',
            '0.61' => 'upgrade-to-0.61-pgsql.sql',
            '0.62' => 'upgrade-to-0.62-pgsql.sql',
            '0.63' => 'upgrade-to-0.63-pgsql.sql',
            '0.70' => 'upgrade-to-0.70.php',
            '0.71' => 'upgrade-to-0.71-pgsql.sql',
            '0.74' => 'upgrade-to-0.74-pgsql.sql',
            '0.75' => 'upgrade-to-0.75-pgsql.sql',
            '0.76' => 'upgrade-to-0.76-pgsql.sql',
            '0.8'  => 'upgrade-to-0.8.php',
            '0.81' => 'upgrade-to-0.81-pgsql.sql',
            '0.82' => 'upgrade-to-0.82-pgsql.sql'
        );

        //as of 0.8, we got 10 update scripts total
        $this->array($update_scripts)
            ->hasSize(count($knowns))
            ->isIdenticalTo($knowns);

        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            'pgsql',
            '0.7'
        );

        //if we're from 0.7.0, there are only 6 update scripts left
        $this->array($update_scripts)
            ->hasSize(count($knowns) - 4);

        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install'
        );

        //without specifying database nor version, we got 10 update scripts total
        $this->array(array_values($update_scripts))
            ->hasSize(count($knowns))
            ->isEqualTo(array_keys($knowns));

        $this->_install->setMode(\Galette\Core\Install::UPDATE);
        $errors = array();
        $this->_install->setDbType(\Galette\Core\Db::PGSQL, $errors);
        $this->_install->setInstalledVersion('0.6');
        $update_scripts = $this->_install->getScripts(
            GALETTE_BASE_PATH . '/install'
        );

        $this->array($update_scripts)
            ->hasSize(count($knowns))
            ->isIdenticalTo($knowns);

        $this->_install->setMode(\Galette\Core\Install::INSTALL);
        $update_scripts = $this->_install->getScripts(
            GALETTE_BASE_PATH . '/install'
        );

        $this->array($update_scripts)
            ->hasSize(1)
            ->hasKey('current')
            ->strictlyContains(\Galette\Core\Db::PGSQL . '.sql');
    }

    /**
     * Test type step
     *
     * @return void
     */
    public function testTypeStep()
    {
        $this->_install->atTypeStep();

        $step = $this->_install->isTypeStep();
        $this->boolean($step)->isTrue();

        $title = $this->_install->getStepTitle();
        $this->string($title)->isIdenticalTo('Installation mode');
    }

    /**
     * Test DB installation step
     *
     * @return void
     */
    public function testInstallDbStep()
    {
        $this->_install->setMode(\Galette\Core\Install::INSTALL);
        $this->_install->atDbStep();

        $is_install = $this->_install->isInstall();
        $is_upgrade = $this->_install->isUpgrade();

        $this->boolean($is_install)->isTrue();
        $this->boolean($is_upgrade)->isFalse();

        $title = $this->_install->getStepTitle();
        $this->string($title)->isIdenticalTo('Database');

        $this->_install->atPreviousStep();
        $step = $this->_install->isTypeStep();
        $this->boolean($step)->isTrue();
    }

    /**
     * Test DB upgrade step
     *
     * @return void
     */
    public function testUpgradeDbStep()
    {
        $this->_install->setMode(\Galette\Core\Install::UPDATE);
        $this->_install->atDbStep();

        $is_install = $this->_install->isInstall();
        $is_upgrade = $this->_install->isUpgrade();

        $this->boolean($is_install)->isFalse();
        $this->boolean($is_upgrade)->isTrue();

        $title = $this->_install->getStepTitle();
        $this->string($title)->isIdenticalTo('Database');

        $this->_install->atPreviousStep();
        $step = $this->_install->isTypeStep();

        $this->boolean($step)->isTrue();
    }

    /**
     * Test unknown mode
     *
     * @return void
     */
    public function testUnknownMode()
    {
        $this->exception(
            function () {
                $this->_install->setMode('nonsense');
            }
        )->hasMessage('Unknown mode "nonsense"');
    }

    /**
     * Test Db types
     *
     * @return void
     */
    public function testSetDbType()
    {
        $types = array(
            \Galette\Core\Db::MYSQL,
            \Galette\Core\Db::PGSQL
        );

        foreach ( $types as $t ) {
            $errors = array();

            $this->_install->setDbType(\Galette\Core\Db::MYSQL, $errors);
            $type = $this->_install->getDbType();

            $this->variable($type)->isIdenticalTo(\Galette\Core\Db::MYSQL);
            $this->array($errors)->hasSize(0);
        }

        $errors = array();
        $this->_install->setDbType('nonsense', $errors);

        $this->array($errors)->hasSize(1)
            ->strictlyContains('Database type unknown');

        $post_check = $this->_install->postCheckDb();
        $this->boolean($post_check)->isFalse();
    }

    /**
     * Test Db chack step (same for install and upgrade)
     *
     * @return void
     */
    public function testDbCheckStep()
    {
        $errors = array();
        $this->_install->setDbType(TYPE_DB, $errors);
        $this->_install->setDsn(
            HOST_DB,
            PORT_DB,
            NAME_DB,
            USER_DB,
            PWD_DB
        );
        $this->_install->setTablesPrefix(
            PREFIX_DB
        );
        $this->_install->atDbCheckStep();

        $step = $this->_install->isDbCheckStep();
        $this->boolean($step)->isTrue();

        $title = $this->_install->getStepTitle();
        $this->string($title)->isIdenticalTo('Database access and permissions');

        $connected = $this->_install->testDbConnexion();
        $this->boolean($connected)->isTrue();

        $host = $this->_install->getDbHost();
        $this->string($host)->isIdenticalTo(HOST_DB);

        $port = $this->_install->getDbPort();
        $this->variable($port)->isIdenticalTo(PORT_DB);

        $name = $this->_install->getDbName();
        $this->variable($name)->isIdenticalTo(NAME_DB);

        $user = $this->_install->getDbUser();
        $this->variable($user)->isIdenticalTo(USER_DB);

        $prefix = $this->_install->getTablesPrefix();
        $this->variable($prefix)->isIdenticalTo(PREFIX_DB);

        $pass = $this->_install->getDbPass();
        $this->variable($pass)->isIdenticalTo(PWD_DB);

        $post_check = $this->_install->postCheckDb();
        $this->boolean($post_check)->isFalse();

        $this->_install->atPreviousStep();
        $step = $this->_install->isDbStep();
        $this->boolean($step)->isTrue();
    }

    /**
     * Test db install step
     *
     * @return void
     */
    public function testDbInstallStep()
    {
        $this->_install->setDbType(TYPE_DB, $errors);
        $this->_install->setDsn(
            HOST_DB,
            PORT_DB,
            NAME_DB,
            USER_DB,
            PWD_DB
        );
        $this->_install->setTablesPrefix(
            PREFIX_DB
        );

        $this->_install->atDbInstallStep();

        $step = $this->_install->isDbinstallStep();
        $this->boolean($step)->isTrue();

        $title = $this->_install->getStepTitle();
        $this->string($title)->isIdenticalTo('Tables Creation');

        $post_check = $this->_install->postCheckDb();
        $this->boolean($post_check)->isTrue();

        $this->_install->atPreviousStep();
        $step = $this->_install->isDbCheckStep();
        $this->boolean($step)->isTrue();
    }

    /**
     * Test admin step
     *
     * @return void
     */
    public function testAdminStep()
    {
        $this->_install->atAdminStep();

        $step = $this->_install->isAdminStep();
        $this->boolean($step)->isTrue();

        $title = $this->_install->getStepTitle();
        $this->string($title)->isIdenticalTo('Admin parameters');

        $post_check = $this->_install->postCheckDb();
        $this->boolean($post_check)->isTrue();

        $this->_install->atPreviousStep();
        //db install cannot be run twice, step is still Admin
        $step = $this->_install->isAdminStep();
        $this->boolean($step)->isTrue();
    }

    /**
     * Test galette initialization
     *
     * @return void
     */
    public function testInitStep()
    {
        $this->_install->atGaletteInitStep();

        $step = $this->_install->isGaletteInitStep();
        $this->boolean($step)->isTrue();

        $title = $this->_install->getStepTitle();
        $this->string($title)->isIdenticalTo('Galette initialization');

        $post_check = $this->_install->postCheckDb();
        $this->boolean($post_check)->isTrue();
    }
}
