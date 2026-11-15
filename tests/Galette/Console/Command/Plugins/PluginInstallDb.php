<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command\Plugins;

use Galette\Core\Plugins;
use Galette\Tests\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PluginInstallDb command tests.
 *
 * These tests verify that:
 * - A DISABLED_NOT_INSTALLED plugin is processed with INSTALL mode (full SQL script).
 * - A DISABLED_NOT_UP2DATE plugin is processed with UPDATE mode (versioned upgrade
 *   scripts only), ensuring data is preserved.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginInstallDb extends GaletteTestCase
{
    /** Load plugins manually from a dedicated path; do not load the default test plugins. */
    protected bool $load_plugins = false;

    /**
     * DDL statements issued by the install/upgrade SQL scripts cannot be rolled
     * back in MySQL. Disable the automatic transaction wrapper and perform
     * explicit clean-up in tearDown() instead.
     */
    protected bool $db_transactions = false;

    private const string PLUGINS_PATH = GALETTE_TESTS_PATH . '/plugins-installdb/';
    private const string PLUGIN_INSTALL_ID = 'plugin-db-install';
    private const string PLUGIN_UPGRADE_ID = 'plugin-db-upgrade';

    /** Tables created by test SQL scripts that must be dropped during tear-down. */
    private const array TABLES_TO_DROP = [
        //references plugin_db_install_test, must be dropped first
        'plugin_db_install_ref_test',
        'plugin_db_install_test',
        'plugin_db_upgrade_test',
        'plugin_db_upgrade_detail_test',
    ];

    /**
     * Tear down: drop tables created by SQL scripts and remove galette_plugins rows.
     */
    public function tearDown(): void
    {
        foreach (self::TABLES_TO_DROP as $suffix) {
            $table = PREFIX_DB . $suffix;
            if ($this->zdb->isPostgres()) {
                $this->zdb->db->query("DROP TABLE IF EXISTS $table", Adapter::QUERY_MODE_EXECUTE);
            } else {
                $this->zdb->db->query("DROP TABLE IF EXISTS `$table`", Adapter::QUERY_MODE_EXECUTE);
            }
        }

        $delete = $this->zdb->delete(Plugins::TABLE);
        $delete->where->in('plugin_id', [self::PLUGIN_INSTALL_ID, self::PLUGIN_UPGRADE_ID]);
        $this->zdb->execute($delete);
        \GaletteDbUpgradePlugin\PluginGalettePlugdbupgrade::$legacy_version = null;
        \GaletteDbUpgradePlugin\PluginGalettePlugdbupgrade::$upgrade_fails = false;
        \GaletteDbInstallPlugin\PluginGalettePlugdbinstall::$init_error = null;

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Load the dedicated install-db test plugins.
     * Must be called by each test after any required DB seeding so that
     * loadDbModules() picks up the correct state.
     */
    private function loadTestPlugins(): void
    {
        $this->plugins->resetModulesList();
        $this->plugins->loadModules($this->preferences, self::PLUGINS_PATH);
    }

    /**
     * Read the version stored in galette_plugins for the given plugin.
     * Casts through float to match Galette's own internal representation
     * (loadDbModules does the same), so trailing zeros in the DB value are
     * normalised (e.g. '0.100' → '0.1').
     */
    private function fetchStoredVersion(string $plugin_id): ?string
    {
        $select = $this->zdb->select(Plugins::TABLE);
        $select->columns(['version']);
        $select->where([Plugins::PK => $plugin_id]);
        $results = $this->zdb->execute($select);
        if ($results->count() === 0) {
            return null;
        }
        $row = $results->current();
        return $row['version'] !== null ? (string)(float)$row['version'] : null;
    }

    /**
     * Get labels stored in a test table
     *
     * @param string $table Table name, without prefix
     *
     * @return array<int, string>
     */
    private function fetchLabels(string $table): array
    {
        $labels = [];
        foreach ($this->zdb->execute($this->zdb->select($table)) as $row) {
            $labels[] = $row['label'];
        }
        return $labels;
    }

    /**
     * Get Galette database version
     */
    private function fetchGaletteVersion(): string
    {
        return (string)$this->zdb->execute($this->zdb->select('database'))->current()['version'];
    }

    /**
     * Seed an old version (0.1) for plugin-db-upgrade in galette_plugins so
     * that loadModules() will see a version mismatch and set DISABLED_NOT_UP2DATE.
     */
    private function seedUpgradePluginOldVersion(): void
    {
        $insert = $this->zdb->insert(Plugins::TABLE);
        $insert->values([Plugins::PK => self::PLUGIN_UPGRADE_ID, 'version' => '0.1']);
        $this->zdb->execute($insert);
    }

    // -------------------------------------------------------------------------
    // Tests for Plugins::getInstalledDbVersion()
    // -------------------------------------------------------------------------

    /**
     * A plugin that is NOT in galette_plugins returns null.
     */
    public function testGetInstalledDbVersionNotInstalled(): void
    {
        $this->loadTestPlugins();

        // plugin-db-install has isInstalled()=false → never auto-migrated
        $this->assertNull($this->plugins->getInstalledDbVersion(self::PLUGIN_INSTALL_ID));
    }

    /**
     * A plugin whose version has been seeded before loadModules() returns the
     * stored version string.
     * We seed 0.1 so that the mismatch with dbver 0.2 triggers DISABLED_NOT_UP2DATE
     * (version is visible in db_existing) without triggering auto-migration.
     */
    public function testGetInstalledDbVersionInstalled(): void
    {
        $this->seedUpgradePluginOldVersion();
        $this->loadTestPlugins();

        $this->assertSame('0.1', $this->plugins->getInstalledDbVersion(self::PLUGIN_UPGRADE_ID));
    }

    /**
     * Asking for a non-existent module throws a MissingPluginException.
     */
    public function testGetInstalledDbVersionUnknownPlugin(): void
    {
        $this->loadTestPlugins();

        $this->expectException(\Galette\Exception\MissingPluginException::class);
        $this->plugins->getInstalledDbVersion('plugin-does-not-exist');
    }

    // -------------------------------------------------------------------------
    // Tests for the PluginInstallDb command
    // -------------------------------------------------------------------------

    /**
     * A DISABLED_NOT_INSTALLED plugin must be handled in INSTALL mode:
     * the full SQL script is executed and the plugin is registered in
     * galette_plugins with version 0.1.
     */
    public function testInstallFreshPlugin(): void
    {
        $this->loadTestPlugins();

        // Verify initial state: not in galette_plugins, disabled as NOT_INSTALLED
        $this->assertNull($this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));
        $this->assertTrue($this->plugins->isDisabled(self::PLUGIN_INSTALL_ID));
        $this->assertSame(
            Plugins::DISABLED_NOT_INSTALLED,
            $this->plugins->getDisabledCause(self::PLUGIN_INSTALL_ID)
        );

        $command = $this->container->make(\Galette\Console\Command\Plugins\PluginInstallDb::class, ['basepath' => GALETTE_ROOT]);
        $tester = new CommandTester($command);
        $tester->execute(['plugins' => [self::PLUGIN_INSTALL_ID]]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('installed', $output);
        $this->assertStringNotContainsString('upgraded', $output);

        // galette_plugins must now contain the plugin at version 0.1
        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));

        //default data has been created
        $this->assertSame(['Default label'], $this->fetchLabels('plugin_db_install_test'));
    }

    /**
     * A plugin whose install script fails is not marked as installed,
     * and its default data are not created
     */
    public function testInstallFailure(): void
    {
        //a table referencing the plugin one prevents the install script to drop it, so creating it fails;
        //MySQL ignores REFERENCES in a column definition, the constraint must be on the table
        $id = $this->zdb->isPostgres() ? 'id serial PRIMARY KEY' : 'id int NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $table = PREFIX_DB . 'plugin_db_install_test';
        $this->zdb->db->query("CREATE TABLE $table ($id, label varchar(255) NOT NULL)", Adapter::QUERY_MODE_EXECUTE);
        $this->zdb->db->query(
            'CREATE TABLE ' . PREFIX_DB . "plugin_db_install_ref_test ($id, test_id int NOT NULL, FOREIGN KEY (test_id) REFERENCES $table (id))",
            Adapter::QUERY_MODE_EXECUTE
        );
        $this->loadTestPlugins();

        $command = $this->container->make(\Galette\Console\Command\Plugins\PluginInstallDb::class, ['basepath' => GALETTE_ROOT]);
        $tester = new CommandTester($command);
        $tester->execute(['plugins' => [self::PLUGIN_INSTALL_ID]]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Unable to install database for plugin "plugin-db-install"', $tester->getDisplay());
        //a failing DROP is tolerated, a failing CREATE is not
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Error executing query: DROP TABLE');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Error executing query: CREATE TABLE');

        $this->assertNull($this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));
        $this->assertSame([], $this->fetchLabels('plugin_db_install_test'));
    }

    /**
     * Default data are created on install only, by plugins that can create them
     */
    public function testInstallInitSkipped(): void
    {
        $this->loadTestPlugins();

        //update mode
        $install = new \Galette\Core\PluginInstall();
        $install->setMode(\Galette\Core\PluginInstall::UPDATE);
        $install->reinitReport();
        $install->setPlugin($this->plugins, self::PLUGIN_INSTALL_ID, $this->i18n);
        $this->assertTrue($install->initPlugin($this->plugins, $this->zdb));
        $this->assertSame([], $install->getInitializationReport());

        //plugin without default data
        $install = new \Galette\Core\PluginInstall();
        $install->setMode(\Galette\Core\PluginInstall::INSTALL);
        $install->reinitReport();
        $install->setPlugin($this->plugins, self::PLUGIN_UPGRADE_ID, $this->i18n);
        $this->assertTrue($install->initPlugin($this->plugins, $this->zdb));
        $this->assertSame([], $install->getInitializationReport());
    }

    /**
     * A plugin whose default data cannot be created is installed all the same,
     * the command reports the failure
     */
    public function testInstallInitFailure(): void
    {
        $this->loadTestPlugins();
        \GaletteDbInstallPlugin\PluginGalettePlugdbinstall::$init_error = new \RuntimeException('Default data creation failed');

        $command = $this->container->make(\Galette\Console\Command\Plugins\PluginInstallDb::class, ['basepath' => GALETTE_ROOT]);
        $tester = new CommandTester($command);
        $tester->execute(['plugins' => [self::PLUGIN_INSTALL_ID]]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Unable to create default data for plugin "plugin-db-install"', $tester->getDisplay());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to create default data for plugin plugin-db-install');

        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));
        $this->assertSame([], $this->fetchLabels('plugin_db_install_test'));
    }

    /**
     * Errors that make default data creation fail
     *
     * @return array<string, array{0: \Throwable}>
     */
    public static function initErrorsProvider(): array
    {
        return [
            'exception' => [new \RuntimeException('Default data creation failed')],
            'error' => [new \TypeError('Default data creation failed')],
        ];
    }

    /**
     * Default data creation failure is reported as such, whatever has been thrown
     */
    #[DataProvider('initErrorsProvider')]
    public function testInstallInitFailureReport(\Throwable $error): void
    {
        $this->loadTestPlugins();
        \GaletteDbInstallPlugin\PluginGalettePlugdbinstall::$init_error = $error;

        $install = new \Galette\Core\PluginInstall();
        $install->setMode(\Galette\Core\PluginInstall::INSTALL);
        $install->setPlugin($this->plugins, self::PLUGIN_INSTALL_ID, $this->i18n);

        $this->assertFalse($install->initPlugin($this->plugins, $this->zdb));
        $this->assertSame(
            [
                [
                    'message' => 'Default data',
                    'res' => false,
                    'debug' => 'Default data creation failed'
                ]
            ],
            $install->getInitializationReport()
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to create default data for plugin plugin-db-install');
    }

    /**
     * A DISABLED_NOT_UP2DATE plugin must be handled in UPDATE mode:
     * only the versioned upgrade scripts (upgrade-to-0.2 SQL script, then
     * upgrade-to-0.3 update class) are executed — NOT the full install script —
     * and galette_plugins is updated to 0.3.
     */
    public function testUpgradeOutdatedPlugin(): void
    {
        // Pre-insert 0.1 BEFORE loading plugins so loadDbModules() detects a mismatch
        $this->seedUpgradePluginOldVersion();
        $this->loadTestPlugins();

        // Verify initial state: disabled as NOT_UP2DATE with installed version 0.1
        $this->assertTrue($this->plugins->isDisabled(self::PLUGIN_UPGRADE_ID));
        $this->assertSame(
            Plugins::DISABLED_NOT_UP2DATE,
            $this->plugins->getDisabledCause(self::PLUGIN_UPGRADE_ID)
        );
        $this->assertSame('0.1', $this->plugins->getInstalledDbVersion(self::PLUGIN_UPGRADE_ID));
        $galette_version = $this->fetchGaletteVersion();

        $command = $this->container->make(\Galette\Console\Command\Plugins\PluginInstallDb::class, ['basepath' => GALETTE_ROOT]);
        $tester = new CommandTester($command);
        $tester->execute(['plugins' => [self::PLUGIN_UPGRADE_ID]]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('upgraded', $output);
        $this->assertStringNotContainsString('installed', $output);

        // galette_plugins must now reflect version 0.3
        $this->assertSame('0.3', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));

        //update class ran its SQL script from the plugin directory, then its data
        $rows = $this->zdb->execute($this->zdb->select('plugin_db_upgrade_detail_test'))->toArray();
        $this->assertCount(1, $rows);
        $this->assertSame('Upgraded note', $rows[0]['note']);
        $this->assertSame('upgrade', $rows[0]['kind']);

        //Galette database version is left alone
        $this->assertSame($galette_version, $this->fetchGaletteVersion());
    }

    /**
     * A plugin whose update class fails keeps its previous version
     */
    public function testUpgradeFailure(): void
    {
        $this->seedUpgradePluginOldVersion();
        $this->loadTestPlugins();
        \GaletteDbUpgradePlugin\PluginGalettePlugdbupgrade::$upgrade_fails = true;

        $command = $this->container->make(\Galette\Console\Command\Plugins\PluginInstallDb::class, ['basepath' => GALETTE_ROOT]);
        $tester = new CommandTester($command);
        $tester->execute(['plugins' => [self::PLUGIN_UPGRADE_ID]]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Unable to upgrade database for plugin "plugin-db-upgrade"', $tester->getDisplay());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to run 0.3 update script');

        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));
    }

    /**
     * Update classes cannot be found without knowing the plugin they belong to
     */
    public function testUpgradeWithoutPlugin(): void
    {
        $install = new \Galette\Core\PluginInstall();
        $install->setMode(\Galette\Core\PluginInstall::UPDATE);
        $error = [];
        $install->setDbType(TYPE_DB, $error);
        $install->setInstalledVersion('0.2');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Plugin must be set to run its update classes.');
        $install->executeScripts($this->zdb, self::PLUGINS_PATH . self::PLUGIN_UPGRADE_ID);
    }

    /**
     * Tables that predate versions tracking are recorded with the version the
     * plugin reports for them, so the plugin is marked to be updated.
     */
    public function testAutoMigrateLegacyVersion(): void
    {
        //plugins autoloader is not registered yet
        require_once self::PLUGINS_PATH . self::PLUGIN_UPGRADE_ID
            . '/lib/GaletteDbUpgradePlugin/PluginGalettePlugdbupgrade.php';
        \GaletteDbUpgradePlugin\PluginGalettePlugdbupgrade::$legacy_version = 0.1;
        $this->loadTestPlugins();

        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));
        $this->assertTrue($this->plugins->isDisabled(self::PLUGIN_UPGRADE_ID));
        $this->assertSame(
            Plugins::DISABLED_NOT_UP2DATE,
            $this->plugins->getDisabledCause(self::PLUGIN_UPGRADE_ID)
        );
        $this->assertSame('0.1', $this->plugins->getInstalledDbVersion(self::PLUGIN_UPGRADE_ID));
    }

    /**
     * Reinstalling a plugin whose galette_plugins row remains from a prior install
     * (tables dropped externally) must NOT raise a PK violation. The command should
     * succeed and update the tracking row in place.
     */
    public function testInstallPluginWithStaleTrackingRow(): void
    {
        //pre-seed a tracking row so that the upcoming INSTALL path would normally
        //hit a primary key violation
        $insert = $this->zdb->insert(Plugins::TABLE);
        $insert->values([Plugins::PK => self::PLUGIN_INSTALL_ID, 'version' => '0.1']);
        $this->zdb->execute($insert);

        $this->loadTestPlugins();

        //plugin-db-install's isInstalled()=false → DISABLED_NOT_INSTALLED
        //even though the tracking row exists
        $this->assertTrue($this->plugins->isDisabled(self::PLUGIN_INSTALL_ID));
        $this->assertSame(
            Plugins::DISABLED_NOT_INSTALLED,
            $this->plugins->getDisabledCause(self::PLUGIN_INSTALL_ID)
        );
        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));

        $command = $this->container->make(\Galette\Console\Command\Plugins\PluginInstallDb::class, ['basepath' => GALETTE_ROOT]);
        $tester = new CommandTester($command);
        $tester->execute(['plugins' => [self::PLUGIN_INSTALL_ID]]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        //the tracking row is preserved and updated to the module's declared dbver
        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));
    }

    /**
     * setPluginInstalled() on an active plugin whose tracking row is missing must
     * insert the row rather than silently UPDATE 0 rows.
     */
    public function testSetPluginInstalledInsertsWhenRowMissing(): void
    {
        //load plugins so plugin-db-upgrade is auto-migrated to galette_plugins
        $this->loadTestPlugins();

        //after loadModules, plugin-db-upgrade is active and has a tracking row
        $this->assertFalse($this->plugins->isDisabled(self::PLUGIN_UPGRADE_ID));
        $this->assertSame('0.3', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));

        //simulate a missing tracking row (e.g. autoMigratePluginVersion silently
        //swallowed a missing-table error in a previous run)
        $delete = $this->zdb->delete(Plugins::TABLE);
        $delete->where([Plugins::PK => self::PLUGIN_UPGRADE_ID]);
        $this->zdb->execute($delete);
        $this->assertNull($this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));

        //plugin is active so setPluginInstalled hits the case-null branch
        $install = new \Galette\Core\PluginInstall();
        $install->setPluginInstalled($this->zdb, $this->plugins, self::PLUGIN_UPGRADE_ID);

        //the row must now exist with the module's declared dbver
        $this->assertSame('0.3', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));
    }

    /**
     * Interactive and non-interactive (cron jobs, scripts) runs
     *
     * @return array<string, array{0: bool}>
     */
    public static function interactiveProvider(): array
    {
        return [
            'interactive' => [true],
            'non-interactive' => [false],
        ];
    }

    /**
     * The wildcard (*) argument must handle each plugin according to its cause:
     * DISABLED_NOT_INSTALLED → install, DISABLED_NOT_UP2DATE → upgrade.
     */
    #[DataProvider('interactiveProvider')]
    public function testAllPluginsHandledByCorrectMode(bool $interactive): void
    {
        // Seed version 0.1 for the upgrade plugin before loading
        $this->seedUpgradePluginOldVersion();
        $this->loadTestPlugins();

        $command = $this->container->make(\Galette\Console\Command\Plugins\PluginInstallDb::class, ['basepath' => GALETTE_ROOT]);
        $tester = new CommandTester($command);
        // Use --all flag instead of ['*'] argument: initialize() converts it to
        // plugins=['*'] which execute() recognises as "all relevant plugins",
        // including when interact() is never called.
        $tester->execute(['--all' => true], ['interactive' => $interactive]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $output = $tester->getDisplay();

        $this->assertStringContainsString('installed', $output);
        $this->assertStringContainsString('upgraded', $output);

        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));
        $this->assertSame('0.3', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));
    }

    /**
     * Unknown plugins are rejected, even when interact() is never called
     */
    #[DataProvider('interactiveProvider')]
    public function testUnknownPlugin(bool $interactive): void
    {
        $this->loadTestPlugins();

        $command = $this->container->make(\Galette\Console\Command\Plugins\PluginInstallDb::class, ['basepath' => GALETTE_ROOT]);
        $tester = new CommandTester($command);

        $this->expectException(\Symfony\Component\Console\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown plugin(s): plugin-does-not-exist');
        $tester->execute(['plugins' => ['plugin-does-not-exist']], ['interactive' => $interactive]);
    }

    /**
     * --all and a plugins list cannot be used together
     */
    public function testAllWithPlugins(): void
    {
        $this->loadTestPlugins();

        $command = $this->container->make(\Galette\Console\Command\Plugins\PluginInstallDb::class, ['basepath' => GALETTE_ROOT]);
        $tester = new CommandTester($command);

        $this->expectException(\Symfony\Component\Console\Exception\InvalidArgumentException::class);
        $tester->execute(['--all' => true, 'plugins' => [self::PLUGIN_INSTALL_ID]], ['interactive' => false]);
    }
}
