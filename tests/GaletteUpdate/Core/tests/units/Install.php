<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Core\test\units;

use atoum;
use PHPUnit\Framework\TestCase;

/**
 * Update tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Install extends TestCase
{
    private \Galette\Core\Db $zdb;
    private array $flash_data;
    private \Slim\Flash\Messages $flash;
    private \DI\Container $container;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        setlocale(LC_ALL, 'en_US');

        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        $gapp =  new \Galette\Core\SlimApp();
        $app = $gapp->getApp();
        $plugins = new \Galette\Core\Plugins();
        require GALETTE_BASE_PATH . '/includes/dependencies.php';
        $container = $app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $container->set('flash', $this->flash);
        $container->set(Slim\Flash\Messages::class, $this->flash);

        $this->container = $container;

        $this->zdb = $container->get('zdb');
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }
    }

    /**
     * Test if current database version is supported
     *
     * @return void
     */
    public function testDbSupport()
    {
        $this->assertTrue($this->zdb->isEngineSUpported());
    }

    /**
     * Test updates
     *
     * @return void
     */
    public function testUpdates()
    {
        $install = new \Galette\Core\Install();
        $update_scripts = \Galette\Core\Install::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            $this->zdb->type_db,
            '0.6'
        );
        $this->assertGreaterThan(5, count($update_scripts));

        $install->setMode(\Galette\Core\Install::UPDATE);
        $errors = [];
        $install->setDbType($this->zdb->type_db, $errors);
        $this->assertSame([], $errors);

        $install->setInstalledVersion('0.60');
        $install->setTablesPrefix(PREFIX_DB);
        $exec = $install->executeScripts($this->zdb, GALETTE_BASE_PATH . '/install');

        $report = $install->getInitializationReport();
        foreach ($report as $entry) {
            $this->assertTrue(
                $entry['res'],
                ($entry['debug'] ?? '') . "\n" . ($entry['query'] ?? '')
            );
        }

        $this->assertTrue($exec);
        $this->assertSame(GALETTE_DB_VERSION, $this->zdb->getDbVersion());
    }
}
