<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Galette tests case main class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class BaseGaletteTestCase extends TestCase
{
    protected \Galette\Core\Db $zdb;
    protected \Slim\App $app;
    protected \Galette\Core\Plugins $plugins;
    protected \Slim\Flash\Messages $flash;
    /** @var array<string,array<string,array<int,string>>> */
    protected array $flash_data;
    protected \DI\Container $container;
    /** @var string[] */
    protected array $expected_mysql_warnings = [];
    protected bool $check_logs = true;
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

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        $gapp =  new \Galette\Core\SlimApp();
        $app = $gapp->getApp(); //needed as global
        $this->app = $app;

        $plugins = new \Galette\Core\Plugins();
        $this->plugins = $plugins;
        if ($this->load_plugins) {
            $this->plugins->autoload(GALETTE_PLUGINS_PATH);
        }
        require GALETTE_BASE_PATH . '/includes/dependencies.php';
        /** @var \DI\Container $container */
        $container = $this->app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $container->set(\Slim\Flash\Messages::class, $this->flash);

        $this->app->addRoutingMiddleware();
        $this->app->add(\Slim\Views\TwigMiddleware::createFromContainer($this->app, \Slim\Views\Twig::class));

        $this->container = $container;
        $this->zdb = $container->get(\Galette\Core\Db::class);
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        if ($this->check_logs) {
            $logs = $this->getCleanedLogs();
            $this->assertCount(0, $logs, implode("\n", $logs));
        }

        if (TYPE_DB === 'mysql') {
            $this->assertEquals($this->expected_mysql_warnings, $this->zdb->getWarnings());
        }
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
            'WARNING - Plugin plugin-oldversion',
            'ERROR - Plugin Galette Unversionned'
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

        $galette_log_var = implode("\n", $logs);
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
