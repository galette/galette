<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Controllers;

use Galette\Core\Plugins;
use Galette\Tests\GaletteRoutingTestCase;
use Laminas\Db\Adapter\Adapter;

/**
 * Plugins database initialization controller tests
 *
 * Installation mode and installed version are detected from plugin state;
 * user only confirms, then gets the result.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginInitDbControllerTest extends GaletteRoutingTestCase
{
    protected int $seed = 20261025180000;

    /**
     * DDL statements issued by the install/upgrade SQL scripts cannot be rolled
     * back in MySQL; clean up explicitly in tearDown() instead.
     */
    protected bool $db_transactions = false;

    private const string PLUGINS_PATH = GALETTE_TESTS_PATH . '/plugins-installdb/';
    private const string BROKEN_PLUGINS_PATH = GALETTE_TESTS_PATH . '/plugins-installdb-broken/';
    private const string PLUGIN_INSTALL_ID = 'plugin-db-install';
    private const string PLUGIN_UPGRADE_ID = 'plugin-db-upgrade';
    private const string PLUGIN_BROKEN_ID = 'plugin-db-broken';

    /** Tables created by test SQL scripts that must be dropped during tear-down. */
    private const array TABLES_TO_DROP = [
        'plugin_db_install_test',
        'plugin_db_upgrade_test',
        'plugin_db_upgrade_detail_test',
        'plugin_db_broken_test',
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
        $delete->where->in('plugin_id', [self::PLUGIN_INSTALL_ID, self::PLUGIN_UPGRADE_ID, self::PLUGIN_BROKEN_ID]);
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Load test plugins from given path
     */
    private function loadTestPlugins(string $path = self::PLUGINS_PATH): void
    {
        $this->plugins->resetModulesList();
        $this->plugins->loadModules($this->preferences, $path);
    }

    /**
     * Store a version for a plugin in galette_plugins, before plugins are loaded
     */
    private function seedVersion(string $plugin_id, string $version): void
    {
        $insert = $this->zdb->insert(Plugins::TABLE);
        $insert->values([Plugins::PK => $plugin_id, 'version' => $version]);
        $this->zdb->execute($insert);
    }

    /**
     * Read the version stored in galette_plugins for the given plugin.
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
     * Display initialization page
     */
    private function getPage(string $plugin_id): string
    {
        $request = $this->createRequest('pluginInitDb', ['id' => $plugin_id]);
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        return html_entity_decode((string)$test_response->getBody(), ENT_QUOTES);
    }

    /**
     * Post initialization confirmation
     *
     * @param array<string, string> $data Posted data
     */
    private function postConfirm(string $plugin_id, array $data = []): string
    {
        $request = $this->createRequest('pluginInitDb', ['id' => $plugin_id], 'POST')
            ->withParsedBody(['confirm' => '1'] + $data);
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        return html_entity_decode((string)$test_response->getBody(), ENT_QUOTES);
    }

    /**
     * A not installed plugin is installed, without asking for installation mode
     */
    public function testInstall(): void
    {
        $this->loadTestPlugins();
        $this->logSuperAdmin();

        $body = $this->getPage(self::PLUGIN_INSTALL_ID);
        $this->assertStringContainsString("Database of plugin 'Galette Db Install Plugin' will be installed.", $body);
        $this->assertStringNotContainsString('confirm_reinstall', $body);
        $this->assertStringNotContainsString('install_type', $body);
        $this->assertNull($this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));

        $body = $this->postConfirm(self::PLUGIN_INSTALL_ID);
        $this->assertStringContainsString("Plugin 'Galette Db Install Plugin' has been successfully installed!", $body);
        $this->assertStringContainsString('CREATE TABLE galette_plugin_db_install_test', $body);
        $this->assertSame('0.1', $this->fetchStoredVersion(self::PLUGIN_INSTALL_ID));
    }

    /**
     * An outdated plugin is upgraded from its installed version, without asking for it
     */
    public function testUpgrade(): void
    {
        $this->seedVersion(self::PLUGIN_UPGRADE_ID, '0.1');
        $this->loadTestPlugins();
        $this->logSuperAdmin();

        $body = $this->getPage(self::PLUGIN_UPGRADE_ID);
        $this->assertStringContainsString(
            "Database of plugin 'Galette Db Upgrade Plugin' will be upgraded from version 0.1 to version 0.2.",
            $body
        );
        $this->assertStringNotContainsString('previous_version', $body);

        $body = $this->postConfirm(self::PLUGIN_UPGRADE_ID);
        $this->assertStringContainsString("Plugin 'Galette Db Upgrade Plugin' has been successfully updated!", $body);
        $this->assertSame('0.2', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));
    }

    /**
     * Reinstalling an up to date plugin requires an explicit confirmation
     */
    public function testReinstallRequiresConfirmation(): void
    {
        $this->seedVersion(self::PLUGIN_UPGRADE_ID, '0.2');
        $this->loadTestPlugins();
        $this->logSuperAdmin();
        $this->assertFalse($this->plugins->isDisabled(self::PLUGIN_UPGRADE_ID));

        $body = $this->getPage(self::PLUGIN_UPGRADE_ID);
        $this->assertStringContainsString('A new installation will erase all its data.', $body);
        $this->assertStringContainsString('name="confirm_reinstall"', $body);

        //not confirmed: nothing is run
        $body = $this->postConfirm(self::PLUGIN_UPGRADE_ID);
        $this->assertStringContainsString('Please confirm you want to erase existing data.', $body);
        $this->assertStringNotContainsString('has been successfully installed!', $body);
        $this->assertFalse($this->zdb->tableExists(PREFIX_DB . 'plugin_db_upgrade_test'));

        $body = $this->postConfirm(self::PLUGIN_UPGRADE_ID, ['confirm_reinstall' => '1']);
        $this->assertStringContainsString("Plugin 'Galette Db Upgrade Plugin' has been successfully installed!", $body);
        $this->assertSame('0.2', $this->fetchStoredVersion(self::PLUGIN_UPGRADE_ID));
    }

    /**
     * A plugin whose scripts fail is not marked as installed, and the failure is displayed
     */
    public function testFailingScripts(): void
    {
        $this->loadTestPlugins(self::BROKEN_PLUGINS_PATH);
        $this->logSuperAdmin();

        $body = $this->postConfirm(self::PLUGIN_BROKEN_ID);
        $this->assertStringContainsString('The tables have not been totally created', $body);
        $this->assertStringContainsString('missing_column', $body);
        $this->assertStringNotContainsString('has been successfully installed!', $body);
        $this->assertNull($this->fetchStoredVersion(self::PLUGIN_BROKEN_ID));
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Error executing query');
    }
}
