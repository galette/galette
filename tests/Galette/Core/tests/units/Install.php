<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Install tests
 *
 * PHP version 5
 *
 * Copyright © 2014-2021 The Galette Team
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
 * @copyright 2014-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2014-01-03
 */

namespace Galette\Core\test\units;

use atoum;

/**
 * Install tests class
 *
 * @category  Core
 * @name      Db
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2014-01-03
 */
class Install extends atoum
{
    private $install;

    /**
     * Set up tests
     *
     * @param stgring $method Method tested
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        setlocale(LC_ALL, 'en_US');
        $this->install = new \Galette\Core\Install();
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
            $zdb = new \Galette\Core\Db();
            $this->array($zdb->getWarnings())->isIdenticalTo([]);
        }
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
            '0.61'  => 'upgrade-to-0.61-pgsql.sql',
            '0.62'  => 'upgrade-to-0.62-pgsql.sql',
            '0.63'  => 'upgrade-to-0.63-pgsql.sql',
            '0.70'  => 'upgrade-to-0.70.php',
            '0.71'  => 'upgrade-to-0.71-pgsql.sql',
            '0.74'  => 'upgrade-to-0.74-pgsql.sql',
            '0.75'  => 'upgrade-to-0.75-pgsql.sql',
            '0.76'  => 'upgrade-to-0.76-pgsql.sql',
            '0.8'   => 'upgrade-to-0.8.php',
            '0.81'  => 'upgrade-to-0.81-pgsql.sql',
            '0.82'  => 'upgrade-to-0.82-pgsql.sql',
            '0.91'  => 'upgrade-to-0.91-pgsql.sql',
            '0.92'  => 'upgrade-to-0.92-pgsql.sql',
            '0.93'  => 'upgrade-to-0.93-pgsql.sql',
            '0.931' => 'upgrade-to-0.931-pgsql.sql',
            '0.94'  => 'upgrade-to-0.94-pgsql.sql',
            '0.95'  => 'upgrade-to-0.95-pgsql.sql'
        );

        $this->array($update_scripts)
            ->hasSize(count($knowns))
            ->isIdenticalTo($knowns);

        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            'pgsql',
            '0.7'
        );

        //if we're from 0.7.0, there are 4 less update scripts
        $this->array($update_scripts)
            ->hasSize(count($knowns) - 4);

        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install'
        );

        //without specifying database nor version, we got all update scripts
        $all_knowns = ['0.60' => 'upgrade-to-0.60-pgsql.sql'] + $knowns;
        $this->array(array_values($update_scripts))
            ->hasSize(count($all_knowns))
            ->isEqualTo(array_keys($all_knowns));

        $this->install->setMode(\Galette\Core\Install::UPDATE);
        $errors = array();
        $this->install->setDbType(\Galette\Core\Db::PGSQL, $errors);
        $this->install->setInstalledVersion('0.6');
        $update_scripts = $this->install->getScripts(
            GALETTE_BASE_PATH . '/install'
        );

        $this->array($update_scripts)
            ->hasSize(count($knowns))
            ->isIdenticalTo($knowns);

        //for installation, only one script is present :)
        $this->install->setMode(\Galette\Core\Install::INSTALL);
        $update_scripts = $this->install->getScripts(
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
        $this->install->atTypeStep();

        $step = $this->install->isTypeStep();
        $this->boolean($step)->isTrue();

        $title = $this->install->getStepTitle();
        $this->string($title)->isIdenticalTo('Installation mode');
    }

    /**
     * Test DB installation step
     *
     * @return void
     */
    public function testInstallDbStep()
    {
        $this->install->setMode(\Galette\Core\Install::INSTALL);
        $this->install->atDbStep();

        $is_install = $this->install->isInstall();
        $is_upgrade = $this->install->isUpgrade();

        $this->boolean($is_install)->isTrue();
        $this->boolean($is_upgrade)->isFalse();

        $title = $this->install->getStepTitle();
        $this->string($title)->isIdenticalTo('Database');

        $this->install->atPreviousStep();
        $step = $this->install->isTypeStep();
        $this->boolean($step)->isTrue();
    }

    /**
     * Test DB upgrade step
     *
     * @return void
     */
    public function testUpgradeDbStep()
    {
        $this->install->setMode(\Galette\Core\Install::UPDATE);
        $this->install->atDbStep();

        $is_install = $this->install->isInstall();
        $is_upgrade = $this->install->isUpgrade();

        $this->boolean($is_install)->isFalse();
        $this->boolean($is_upgrade)->isTrue();

        $title = $this->install->getStepTitle();
        $this->string($title)->isIdenticalTo('Database');

        $this->install->atPreviousStep();
        $step = $this->install->isTypeStep();

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
                $this->install->setMode('nonsense');
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

        foreach ($types as $t) {
            $errors = array();

            $this->install->setDbType(\Galette\Core\Db::MYSQL, $errors);
            $type = $this->install->getDbType();

            $this->variable($type)->isIdenticalTo(\Galette\Core\Db::MYSQL);
            $this->array($errors)->hasSize(0);
        }

        $errors = array();
        $this->install->setDbType('nonsense', $errors);

        $this->array($errors)->hasSize(1)
            ->strictlyContains('Database type unknown');

        $post_check = $this->install->postCheckDb();
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
        $this->install->setDbType(TYPE_DB, $errors);
        $this->install->setDsn(
            HOST_DB,
            PORT_DB,
            NAME_DB,
            USER_DB,
            PWD_DB
        );
        $this->install->setTablesPrefix(
            PREFIX_DB
        );
        $this->install->atDbCheckStep();

        $step = $this->install->isDbCheckStep();
        $this->boolean($step)->isTrue();

        $title = $this->install->getStepTitle();
        $this->string($title)->isIdenticalTo('Database access and permissions');

        $connected = $this->install->testDbConnexion();
        $this->boolean($connected)->isTrue();

        $host = $this->install->getDbHost();
        $this->string($host)->isIdenticalTo(HOST_DB);

        $port = $this->install->getDbPort();
        $this->variable($port)->isIdenticalTo(PORT_DB);

        $name = $this->install->getDbName();
        $this->variable($name)->isIdenticalTo(NAME_DB);

        $user = $this->install->getDbUser();
        $this->variable($user)->isIdenticalTo(USER_DB);

        $prefix = $this->install->getTablesPrefix();
        $this->variable($prefix)->isIdenticalTo(PREFIX_DB);

        $pass = $this->install->getDbPass();
        $this->variable($pass)->isIdenticalTo(PWD_DB);

        $post_check = $this->install->postCheckDb();
        $this->boolean($post_check)->isFalse();

        $this->install->atPreviousStep();
        $step = $this->install->isDbStep();
        $this->boolean($step)->isTrue();
    }

    /**
     * Test db install step
     *
     * @return void
     */
    public function testDbInstallStep()
    {
        $this->install->setDbType(TYPE_DB, $errors);
        $this->install->setDsn(
            HOST_DB,
            PORT_DB,
            NAME_DB,
            USER_DB,
            PWD_DB
        );
        $this->install->setTablesPrefix(
            PREFIX_DB
        );

        $this->install->atDbInstallStep();

        $step = $this->install->isDbinstallStep();
        $this->boolean($step)->isTrue();

        $title = $this->install->getStepTitle();
        $this->string($title)->isIdenticalTo('Tables Creation');

        $post_check = $this->install->postCheckDb();
        $this->boolean($post_check)->isTrue();

        $this->install->atPreviousStep();
        $step = $this->install->isDbCheckStep();
        $this->boolean($step)->isTrue();
    }

    /**
     * Test admin step
     *
     * @return void
     */
    public function testAdminStep()
    {
        $this->install->atAdminStep();

        $step = $this->install->isAdminStep();
        $this->boolean($step)->isTrue();

        $title = $this->install->getStepTitle();
        $this->string($title)->isIdenticalTo('Admin parameters');

        $post_check = $this->install->postCheckDb();
        $this->boolean($post_check)->isTrue();

        $this->install->atPreviousStep();
        //db install cannot be run twice, step is still Admin
        $step = $this->install->isAdminStep();
        $this->boolean($step)->isTrue();
    }

    /**
     * Test galette initialization
     *
     * @return void
     */
    public function testInitStep()
    {
        $this->install->atGaletteInitStep();

        $step = $this->install->isGaletteInitStep();
        $this->boolean($step)->isTrue();

        $title = $this->install->getStepTitle();
        $this->string($title)->isIdenticalTo('Galette initialization');

        $post_check = $this->install->postCheckDb();
        $this->boolean($post_check)->isTrue();
    }
}
