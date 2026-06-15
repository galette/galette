<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\unlink;

/**
 * Install tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Install extends BaseGaletteTestCase
{
    protected string $app_mode = 'INSTALL';
    private \Galette\Core\Install $install;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        setlocale(LC_ALL, 'en_US');
        $this->install = new \Galette\Core\Install();
    }

    /**
     * Test constructor
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

        $title = $install->getStepDetail('title');
        $this->assertSame('Checks', $title);

        $documentation = $this->install->getStepDetail('documentation');
        $this->assertSame('installation/galette.html', $documentation);
    }

    /**
     * Tests update scripts list
     */
    public function testGetUpgradeScripts(): void
    {
        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            'pgsql',
            '0.6'
        );

        $knowns = [
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
            '1.201' => 'upgrade-to-1.201-pgsql.sql',
            '1.21'  => 'upgrade-to-1.21.php',
            '1.30'  => 'upgrade-to-1.30.php'
        ];

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
        $errors = [];
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
     */
    public function testTypeStep(): void
    {
        $this->install->atTypeStep();

        $step = $this->install->isTypeStep();
        $this->assertTrue($step);

        $title = $this->install->getStepDetail('title');
        $this->assertSame('Installation mode', $title);

        $documentation = $this->install->getStepDetail('documentation');
        $this->assertSame('installation/galette.html#installation-type', $documentation);
    }

    /**
     * Test DB installation step
     */
    public function testInstallDbStep(): void
    {
        $this->install->setMode(\Galette\Core\Install::INSTALL);
        $this->install->atDbStep();

        $is_install = $this->install->isInstall();
        $is_upgrade = $this->install->isUpgrade();

        $this->assertTrue($is_install);
        $this->assertFalse($is_upgrade);

        $title = $this->install->getStepDetail('title');
        $this->assertSame('Database', $title);

        $documentation = $this->install->getStepDetail('documentation');
        $this->assertSame('installation/galette.html#database', $documentation);

        $this->install->atPreviousStep();
        $step = $this->install->isTypeStep();
        $this->assertTrue($step);
    }

    /**
     * Test DB upgrade step
     */
    public function testUpgradeDbStep(): void
    {
        $this->install->setMode(\Galette\Core\Install::UPDATE);
        $this->install->atDbStep();

        $is_install = $this->install->isInstall();
        $is_upgrade = $this->install->isUpgrade();

        $this->assertFalse($is_install);
        $this->assertTrue($is_upgrade);

        $title = $this->install->getStepDetail('title');
        $this->assertSame('Database', $title);

        $documentation = $this->install->getStepDetail('documentation');
        $this->assertSame('installation/galette.html#database', $documentation);

        $this->install->atPreviousStep();
        $step = $this->install->isTypeStep();

        $this->assertTrue($step);
    }

    /**
     * Test unknown mode
     */
    public function testUnknownMode(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unknown mode "nonsense"');
        $this->install->setMode('nonsense');
    }

    /**
     * Test Db types
     */
    public function testSetDbType(): void
    {
        $types = [
            \Galette\Core\Db::MYSQL,
            \Galette\Core\Db::PGSQL
        ];

        foreach ($types as $t) {
            $errors = [];

            $this->install->setDbType($t, $errors);
            $type = $this->install->getDbType();

            $this->assertSame($t, $type);
            $this->assertCount(0, $errors);
        }

        $errors = [];
        $this->install->setDbType('nonsense', $errors);

        $this->assertSame(['Database type unknown'], $errors);

        $post_check = $this->install->postCheckDb();
        $this->assertFalse($post_check);
    }

    /**
     * Test Db chack step (same for install and upgrade)
     */
    public function testDbCheckStep(): void
    {
        $errors = [];
        $this->install->setDbType(TYPE_DB, $errors);
        $this->install->setDsn(
            host: HOST_DB,
            port: PORT_DB,
            name: NAME_DB,
            user: USER_DB,
            pass: PWD_DB
        );
        $this->install->setTablesPrefix(
            PREFIX_DB
        );
        $this->install->atDbCheckStep();

        $step = $this->install->isDbCheckStep();
        $this->assertTrue($step);

        $title = $this->install->getStepDetail('title');
        $this->assertSame('Database access and permissions', $title);

        $documentation = $this->install->getStepDetail('documentation');
        $this->assertSame('installation/galette.html#id1', $documentation);

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
     */
    public function testDbInstallStep(): void
    {
        $errors = [];
        $this->install->setDbType(TYPE_DB, $errors);
        $this->install->setDsn(
            host: HOST_DB,
            port: PORT_DB,
            name: NAME_DB,
            user: USER_DB,
            pass: PWD_DB
        );
        $this->install->setTablesPrefix(
            PREFIX_DB
        );

        $this->install->atDbInstallStep();

        $step = $this->install->isDbinstallStep();
        $this->assertTrue($step);

        $title = $this->install->getStepDetail('title');
        $this->assertSame('Tables Creation', $title);

        $documentation = $this->install->getStepDetail('documentation');
        $this->assertSame('installation/galette.html#create-tables', $documentation);

        $post_check = $this->install->postCheckDb();
        $this->assertTrue($post_check);

        $this->install->atPreviousStep();
        $step = $this->install->isDbCheckStep();
        $this->assertTrue($step);
    }

    /**
     * Test admin step
     */
    public function testAdminStep(): void
    {
        $this->install->atAdminStep();

        $step = $this->install->isAdminStep();
        $this->assertTrue($step);

        $title = $this->install->getStepDetail('title');
        $this->assertSame('Admin parameters', $title);

        $documentation = $this->install->getStepDetail('documentation');
        $this->assertSame('installation/galette.html#admin-parameters', $documentation);

        $post_check = $this->install->postCheckDb();
        $this->assertTrue($post_check);
        $this->expectNoLogEntry();

        $this->install->atPreviousStep();
        //db install cannot be run twice, step is still Admin
        $step = $this->install->isAdminStep();
        $this->assertTrue($step);
        $this->expectLogEntry(\Analog\Analog::WARNING, 'It is forbidden to rerun database install!');
    }

    /**
     * Test installer enable file lifecycle (fail-safe: presence enables)
     */
    public function testInstallEnabledLifecycle(): void
    {
        $enable_file = $this->install->getEnableInstallFilePath();

        //preserve any pre-existing enable file
        $preexisting = file_exists($enable_file);
        $backup = $preexisting ? file_get_contents($enable_file) : null;
        if ($preexisting) {
            @unlink($enable_file);
        }

        try {
            //absent by default => installer disabled
            $this->assertFalse($this->install->isInstallEnabled());

            //the admin creates the file => installer enabled
            file_put_contents($enable_file, '');
            $this->assertTrue($this->install->isInstallEnabled());

            //Galette removes it on success => installer disabled again
            $this->assertTrue($this->install->disableInstaller());
            $this->assertFalse($this->install->isInstallEnabled());
            $this->assertFileDoesNotExist($enable_file);

            //disabling an already disabled installer is a no-op success
            $this->assertTrue($this->install->disableInstaller());
        } finally {
            if ($preexisting) {
                file_put_contents($enable_file, (string)$backup);
            }
        }
    }

    /**
     * Test loading existing config for an update (credentials, including password)
     */
    public function testLoadExistingConfigForUpdate(): void
    {
        //the update loader reads everything, including the password
        $errors = [];
        $loaded = $this->install->loadExistingConfigForUpdate($errors);
        $this->assertTrue($loaded, implode(', ', $errors));
        $this->assertCount(0, $errors);

        //values are read from the on-disk config file (which the test env may
        //override at runtime), so assert they are populated rather than equal
        //to the live constants. The key point is the password IS loaded, unlike
        //the legacy loadExistingConfig() which leaves it null.
        $this->assertNotEmpty($this->install->getDbType());
        $this->assertNotEmpty($this->install->getDbPort());
        $this->assertNotEmpty($this->install->getDbHost());
        $this->assertNotEmpty($this->install->getDbUser());
        $this->assertNotEmpty($this->install->getDbName());
        $this->assertNotEmpty($this->install->getTablesPrefix());
        $this->assertNotEmpty($this->install->getDbPass());
    }

    /**
     * Test database constants are defined only once (idempotent)
     */
    public function testInitDbConstantsIdempotent(): void
    {
        $errors = [];
        $this->install->setDbType(TYPE_DB, $errors);
        $this->install->setDsn(HOST_DB, PORT_DB, NAME_DB, USER_DB, PWD_DB);
        $this->install->setTablesPrefix(PREFIX_DB);

        //constants are already defined by the bootstrap; calling this again
        //must not raise a "constant already defined" error
        $this->install->initDbConstants();
        $this->install->initDbConstants();

        $this->assertSame(HOST_DB, constant('HOST_DB'));
    }

    /**
     * Test galette initialization
     */
    public function testInitStep(): void
    {
        $this->install->atGaletteInitStep();

        $step = $this->install->isGaletteInitStep();
        $this->assertTrue($step);

        $title = $this->install->getStepDetail('title');
        $this->assertSame('Galette initialization', $title);

        $documentation = $this->install->getStepDetail('documentation');
        $this->assertSame('installation/galette.html#initialize', $documentation);

        $post_check = $this->install->postCheckDb();
        $this->assertTrue($post_check);
    }
}
