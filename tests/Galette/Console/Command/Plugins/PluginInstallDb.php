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

        $command = new \Galette\Console\Command\Plugins\PluginInstallDb(GALETTE_ROOT);
        $tester = new CommandTester($command);
        $tester->execute(['plugins' => [self::PLUGIN_INSTALL_ID]]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('installed', $output);
        $this->assertStringNotContainsString('upgraded', $output);

        // galette_plugins must now contain the plugin at version 0.1
        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));
    }

    /**
     * A DISABLED_NOT_UP2DATE plugin must be handled in UPDATE mode:
     * only the versioned upgrade script (upgrade-to-0.2) is executed —
     * NOT the full install script — and galette_plugins is updated to 0.2.
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

        $command = new \Galette\Console\Command\Plugins\PluginInstallDb(GALETTE_ROOT);
        $tester = new CommandTester($command);
        $tester->execute(['plugins' => [self::PLUGIN_UPGRADE_ID]]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('upgraded', $output);
        $this->assertStringNotContainsString('installed', $output);

        // galette_plugins must now reflect version 0.2
        $this->assertSame('0.2', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));
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

        $command = new \Galette\Console\Command\Plugins\PluginInstallDb(GALETTE_ROOT);
        $tester = new CommandTester($command);
        $tester->execute(['plugins' => [self::PLUGIN_INSTALL_ID]]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
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
        $this->assertSame('0.2', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));

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
        $this->assertSame('0.2', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));
    }

    /**
     * The wildcard (*) argument must handle each plugin according to its cause:
     * DISABLED_NOT_INSTALLED → install, DISABLED_NOT_UP2DATE → upgrade.
     */
    public function testAllPluginsHandledByCorrectMode(): void
    {
        // Seed version 0.1 for the upgrade plugin before loading
        $this->seedUpgradePluginOldVersion();
        $this->loadTestPlugins();

        $command = new \Galette\Console\Command\Plugins\PluginInstallDb(GALETTE_ROOT);
        $tester = new CommandTester($command);
        // Use --all flag instead of ['*'] argument: interact() converts it to
        // plugins=['*'] which execute() recognises as "all relevant plugins".
        $tester->execute(['--all' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $this->assertStringContainsString('installed', $output);
        $this->assertStringContainsString('upgraded', $output);

        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));
        $this->assertSame('0.2', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));
    }
}
