<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

declare(strict_types=1);

namespace Galette\Core\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Install tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Install extends TestCase
{
    private \Galette\Core\Install $install;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        setlocale(LC_ALL, 'en_US');
        $this->install = new \Galette\Core\Install();
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $zdb = new \Galette\Core\Db();
            $this->assertSame([], $zdb->getWarnings());
        }
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $install = new \Galette\Core\Install();

        $step = $install->isCheckStep();
        $this->assertTrue($step);

        $mode = $install->getMode();
        $this->assertNull($mode);

        $is_install = $install->isInstall();
        $this->assertFalse($is_install);

        $is_upgrade = $install->isUpgrade();
        $this->assertFalse($is_upgrade);

        $connected = $install->isDbConnected();
        $this->assertFalse($connected);

        $title = $install->getStepTitle();
        $this->assertSame('Checks', $title);
    }

    /**
     * Tests update scripts list
     *
     * @return void
     */
    public function testGetUpgradeScripts(): void
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
            '0.95'  => 'upgrade-to-0.95-pgsql.sql',
            '0.96'  => 'upgrade-to-0.96-pgsql.sql',
            '1.10'  => 'upgrade-to-1.10.php',
            '1.20'  => 'upgrade-to-1.20.php',
        );

        $this->assertSame($knowns, $update_scripts);

        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            'pgsql',
            '0.7'
        );

        //if we're from 0.7.0, there are 4 less update scripts
        $this->assertCount(count($knowns) - 4, $update_scripts);

        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install'
        );

        //without specifying database nor version, we got all update scripts
        $all_knowns = ['0.60' => 'upgrade-to-0.60-pgsql.sql'] + $knowns;
        $this->assertEquals(array_values($update_scripts), array_keys($all_knowns));

        $this->install->setMode(\Galette\Core\Install::UPDATE);
        $errors = array();
        $this->install->setDbType(\Galette\Core\Db::PGSQL, $errors);
        $this->install->setInstalledVersion('0.6');
        $update_scripts = $this->install->getScripts(
            GALETTE_BASE_PATH . '/install'
        );

        $this->assertSame($knowns, $update_scripts);

        //for installation, only one script is present :)
        $this->install->setMode(\Galette\Core\Install::INSTALL);
        $update_scripts = $this->install->getScripts(
            GALETTE_BASE_PATH . '/install'
        );

        $this->assertSame(['current' => \Galette\Core\Db::PGSQL . '.sql'], $update_scripts);
    }

    /**
     * Test type step
     *
     * @return void
     */
    public function testTypeStep(): void
    {
        $this->install->atTypeStep();

        $step = $this->install->isTypeStep();
        $this->assertTrue($step);

        $title = $this->install->getStepTitle();
        $this->assertSame('Installation mode', $title);
    }

    /**
     * Test DB installation step
     *
     * @return void
     */
    public function testInstallDbStep(): void
    {
        $this->install->setMode(\Galette\Core\Install::INSTALL);
        $this->install->atDbStep();

        $is_install = $this->install->isInstall();
        $is_upgrade = $this->install->isUpgrade();

        $this->assertTrue($is_install);
        $this->assertFalse($is_upgrade);

        $title = $this->install->getStepTitle();
        $this->assertSame('Database', $title);

        $this->install->atPreviousStep();
        $step = $this->install->isTypeStep();
        $this->assertTrue($step);
    }

    /**
     * Test DB upgrade step
     *
     * @return void
     */
    public function testUpgradeDbStep(): void
    {
        $this->install->setMode(\Galette\Core\Install::UPDATE);
        $this->install->atDbStep();

        $is_install = $this->install->isInstall();
        $is_upgrade = $this->install->isUpgrade();

        $this->assertFalse($is_install);
        $this->assertTrue($is_upgrade);

        $title = $this->install->getStepTitle();
        $this->assertSame('Database', $title);

        $this->install->atPreviousStep();
        $step = $this->install->isTypeStep();

        $this->assertTrue($step);
    }

    /**
     * Test unknown mode
     *
     * @return void
     */
    public function testUnknownMode(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unknown mode "nonsense"');
        $this->install->setMode('nonsense');
    }

    /**
     * Test Db types
     *
     * @return void
     */
    public function testSetDbType(): void
    {
        $types = array(
            \Galette\Core\Db::MYSQL,
            \Galette\Core\Db::PGSQL
        );

        foreach ($types as $t) {
            $errors = array();

            $this->install->setDbType(\Galette\Core\Db::MYSQL, $errors);
            $type = $this->install->getDbType();

            $this->assertSame(\Galette\Core\Db::MYSQL, $type);
            $this->assertCount(0, $errors);
        }

        $errors = array();
        $this->install->setDbType('nonsense', $errors);

        $this->assertSame(['Database type unknown'], $errors);

        $post_check = $this->install->postCheckDb();
        $this->assertFalse($post_check);
    }

    /**
     * Test Db chack step (same for install and upgrade)
     *
     * @return void
     */
    public function testDbCheckStep(): void
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
        $this->assertTrue($step);

        $title = $this->install->getStepTitle();
        $this->assertSame('Database access and permissions', $title);

        $connected = $this->install->testDbConnexion();
        $this->assertTrue($connected);

        $host = $this->install->getDbHost();
        $this->assertSame(HOST_DB, $host);

        $port = $this->install->getDbPort();
        $this->assertSame(PORT_DB, $port);

        $name = $this->install->getDbName();
        $this->assertSame(NAME_DB, $name);

        $user = $this->install->getDbUser();
        $this->assertSame(USER_DB, $user);

        $prefix = $this->install->getTablesPrefix();
        $this->assertSame(PREFIX_DB, $prefix);

        $pass = $this->install->getDbPass();
        $this->assertSame(PWD_DB, $pass);

        $post_check = $this->install->postCheckDb();
        $this->assertTrue($post_check);

        $this->install->atPreviousStep();
        $step = $this->install->isDbStep();
        $this->assertTrue($step);
    }

    /**
     * Test db install step
     *
     * @return void
     */
    public function testDbInstallStep(): void
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

        $this->install->atDbInstallStep();

        $step = $this->install->isDbinstallStep();
        $this->assertTrue($step);

        $title = $this->install->getStepTitle();
        $this->assertSame('Tables Creation', $title);

        $post_check = $this->install->postCheckDb();
        $this->assertTrue($post_check);

        $this->install->atPreviousStep();
        $step = $this->install->isDbCheckStep();
        $this->assertTrue($step);
    }

    /**
     * Test admin step
     *
     * @return void
     */
    public function testAdminStep(): void
    {
        $this->install->atAdminStep();

        $step = $this->install->isAdminStep();
        $this->assertTrue($step);

        $title = $this->install->getStepTitle();
        $this->assertSame('Admin parameters', $title);

        $post_check = $this->install->postCheckDb();
        $this->assertTrue($post_check);

        $this->install->atPreviousStep();
        //db install cannot be run twice, step is still Admin
        $step = $this->install->isAdminStep();
        $this->assertTrue($step);
    }

    /**
     * Test galette initialization
     *
     * @return void
     */
    public function testInitStep(): void
    {
        $this->install->atGaletteInitStep();

        $step = $this->install->isGaletteInitStep();
        $this->assertTrue($step);

        $title = $this->install->getStepTitle();
        $this->assertSame('Galette initialization', $title);

        $post_check = $this->install->postCheckDb();
        $this->assertTrue($post_check);
    }
}
