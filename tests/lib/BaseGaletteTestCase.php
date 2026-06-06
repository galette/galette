<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests;

use ArrayObject;
use PHPUnit\Framework\TestCase;

use function Safe\preg_match;
use function Safe\session_regenerate_id;
use function Safe\session_start;
use function Safe\session_write_close;

/**
 * Galette tests case main class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class BaseGaletteTestCase extends TestCase
{
    protected \Galette\Core\Db $zdb;
    /** @var \Slim\App<\Psr\Container\ContainerInterface> */
    protected \Slim\App $app;
    protected \Galette\Core\Plugins $plugins;
    protected \Slim\Flash\Messages $flash;
    /** @var array<string,array<string,array<int,string>>> */
    protected array $flash_data;
    protected \DI\Container $container;
    /** @var array<ArrayObject<string, string|int>> */
    protected array $expected_mysql_warnings = [];
    protected bool $check_logs = true;
    protected bool $db_transactions = true;

    /**
     * @var string[]
     * @see \Analog\Handler\Level::$log_levels
     */
    private array $log_levels_names = [
        \Analog\Analog::DEBUG    => 'DEBUG',
        \Analog\Analog::INFO     => 'INFO',
        \Analog\Analog::NOTICE   => 'NOTICE',
        \Analog\Analog::WARNING  => 'WARNING',
        \Analog\Analog::ERROR    => 'ERROR',
        \Analog\Analog::CRITICAL => 'CRITICAL',
        \Analog\Analog::ALERT    => 'ALERT',
        \Analog\Analog::URGENT   => 'URGENT'
    ];
    protected bool $load_plugins = false;
    protected string $app_mode = GALETTE_MODE;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        global $zdb;
        if (isset($zdb) && $zdb instanceof \Galette\Core\Db) {
            $zdb->db->getDriver()->getConnection()->disconnect();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION = [];
        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        $plugins = new \Galette\Core\Plugins();
        $this->plugins = $plugins;
        if ($this->load_plugins) {
            $this->plugins->autoload(GALETTE_PLUGINS_PATH);
        }

        $gapp =  new \Galette\Core\SlimApp($this->plugins, $this->app_mode);
        $app = $gapp->getApp(); //needed as global
        $this->app = $app;

        global $plugins;
        $plugins = $this->plugins;
        global $app;
        $app = $this->app;

        /** @var \DI\Container $container */
        $container = $this->app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $container->set(\Slim\Flash\Messages::class, $this->flash);

        $this->app->addRoutingMiddleware();
        if ($this->app_mode === GALETTE_MODE) {
            $this->app->add(\Slim\Views\TwigMiddleware::createFromContainer($this->app, \Slim\Views\Twig::class));
        }

        $this->container = $container;
        global $container;
        $container = $this->container;

        // Cleanup cache
        if (is_dir(GALETTE_CACHE_DIR)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    GALETTE_CACHE_DIR,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getPathname());
            }
        }

        // Isolate logger
        global $galette_log_var;
        $this->expectNoLogEntry();
        $galette_log_var = null;
        $galette_run_log = \Analog\Handler\LevelName::init(
            \Analog\Handler\Variable::init($galette_log_var)
        );
        \Analog\Analog::handler($galette_run_log);

        $this->zdb = $container->get(\Galette\Core\Db::class);
        $container->get(\Galette\Core\I18n::class)->changeLanguage('en_US');
        if ($this->db_transactions) {
            $this->zdb->setNoCommit()->beginTransaction();
        }

        // Synchronize globals
        //phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- globals \o/
        global $zdb, $preferences, $login, $i18n, $emitter;
        $zdb = $this->zdb;
        if ($container->has(\Galette\Core\Preferences::class)) {
            $preferences = $container->get(\Galette\Core\Preferences::class);
        }
        if ($container->has(\Galette\Core\Login::class)) {
            $login = $container->get(\Galette\Core\Login::class);
        }
        $i18n        = $container->get(\Galette\Core\I18n::class);
        $emitter     = $container->get(\League\Event\EventDispatcher::class);
        //phpcs:enable
        gc_collect_cycles();
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        if ($this->db_transactions && $this->zdb->inTransaction()) {
            $this->zdb->rollback();
        }

        if (!$this->zdb->isPostgres()) {
            $this->handleMysqlWarnings();
        }

        if (isset($this->zdb)) {
            $this->zdb->db->getDriver()->getConnection()->disconnect();
        }

        if ($this->check_logs) {
            $logs = $this->getCleanedLogs();
            $this->assertCount(0, $logs, implode("\n", $logs));
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        global $plugins, $app, $container, $zdb, $preferences, $login, $hist, $l10n, $emitter, $routeparser, $i18n, $translator;
        $plugins = $app = $container = $zdb = $preferences = $login = $hist = $l10n = $emitter = $routeparser = $i18n = $translator = null;
        unset(
            $this->zdb,
            $this->app,
            $this->container,
            $this->plugins,
            $this->flash,
            $this->flash_data,
            $this->routeparser
        );

        gc_collect_cycles();
    }

    /**
     * Handle MySQL warnings checking against expected ones
     */
    private function handleMysqlWarnings(): void
    {
        $current_warnings = $this->zdb->getWarnings();
        $expected = $this->expected_mysql_warnings;

        // Missing expected warnings are not errors, as MySQL 8 does not
        // always report warnings that occur on MariaDB.
        // However, any unexpected warning must fail.
        foreach ($current_warnings as $warning) {
            $found_index = null;
            foreach ($expected as $index => $expected_warning) {
                if ($this->warningsMatch($expected_warning, $warning)) {
                    $found_index = $index;
                    break;
                }
            }

            if ($found_index !== null) {
                unset($expected[$found_index]);
            } else {
                $this->fail(
                    'Unexpected MySQL warning: ' . print_r($warning, true) . PHP_EOL
                    . 'Expected warnings: ' . print_r($this->expected_mysql_warnings, true)
                );
            }
        }
    }

    /**
     * Check if two warnings match, considering possible regex in expected message
     *
     * @param ArrayObject<string, string|int> $expected Expected warning
     * @param ArrayObject<string, string|int> $actual   Actual warning
     */
    private function warningsMatch(ArrayObject $expected, ArrayObject $actual): bool
    {
        if ($expected['Level'] !== $actual['Level'] || (int)$expected['Code'] !== (int)$actual['Code']) {
            return false;
        }

        $expected_message = (string)$expected['Message'];

        // Check if the expected message is a regex pattern
        if (str_starts_with($expected_message, 'regex:')) {
            $pattern = substr($expected_message, 6); // Remove 'regex:' prefix
            return preg_match($pattern, (string)$actual['Message']) === 1;
        }

        // Standard exact match
        return $expected_message === $actual['Message'];
    }

    /**
     * Get logs as an array, cleaned of unwanted entries
     *
     * @param ?int $keep_level Level to keep explicitly (to check INFO or DEBUG logs messages)
     *
     * @return string[]
     */
    private function getCleanedLogs(?int $keep_level = null): array
    {
        global $galette_log_var;
        $logs = explode("localhost - ", $galette_log_var ?? '');

        $excluded_logs = [
            'WARNING - Plugin "Galette Old Plugin" is known to be compatible with Galette 0.7.0 only',
            'ERROR - Plugin "Galette Unversionned Plugin" does not contain mandatory version compatibility information',
            'ERROR - Plugin "Galette Noclass Plugin" class "GaletteNoclassPlugin\PluginGalettePluginnoclass" is missing ',
            'ERROR - Plugin "Galette Db No Version" needs a database but no version is provided.',
            'WARNING - Plugin "Galette Db Plugin" database needs to be updated.',
            // Expected logs from the install-db test plugins
            'WARNING - Plugin "Galette Db Install Plugin" has not been installed.',
            'WARNING - Plugin "Galette Db Upgrade Plugin" database needs to be updated.',
        ];

        foreach ($logs as $i => $log) {
            foreach ($excluded_logs as $excluded_log) {
                if (str_contains($log, $excluded_log)) {
                    unset($logs[$i]);
                }
            }

            if (
                empty($log)
                || str_contains($log, '- ' . $this->log_levels_names[\Analog\Analog::DEBUG] . ' - ')
                && $keep_level !== \Analog\Analog::DEBUG
                || str_contains($log, '- ' . $this->log_levels_names[\Analog\Analog::INFO] . ' - ')
                && $keep_level !== \Analog\Analog::INFO
                || str_contains($log, '- ' . $this->log_levels_names[\Analog\Analog::NOTICE] . ' - ')
                && $keep_level !== \Analog\Analog::NOTICE
            ) {
                unset($logs[$i]);
            }
        }
        return $logs;
    }

    /**
     * Check for expected log entry. If found, it will be removed from logs.
     *
     * @param int    $level   Log level
     * @param string $message Log message
     */
    protected function expectLogEntry(int $level, string $message): void
    {
        global $galette_log_var;
        $this->assertNotEmpty($galette_log_var);

        $logs = $this->getCleanedLogs(keep_level: $level);
        $found = false;
        foreach ($logs as $i => $log) {
            if (str_contains($log, $this->log_levels_names[$level] . ' - ') && str_contains($log, $message)) {
                $found = true;
                unset($logs[$i]);
            }
        }

        //rebuild log storage with remaining entries, keeping separator so they can still be checked later
        $galette_log_var = count($logs) ? 'localhost - ' . implode('localhost - ', $logs) : '';
        $this->assertTrue(
            $found,
            "Log message '{$message}' not found in log storage for level '{$this->log_levels_names[$level]}'."
        );
    }

    /**
     * Check there is no log entry.
     */
    protected function expectNoLogEntry(): void
    {
        $logs = $this->getCleanedLogs();
        $this->assertCount(0, $logs, print_r($logs, true));
    }
}
