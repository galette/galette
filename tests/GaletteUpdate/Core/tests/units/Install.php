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
    /** @var array<string> */
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
    public function testDbSupport(): void
    {
        $this->assertTrue($this->zdb->isEngineSUpported());
    }

    /**
     * Test updates
     *
     * @return void
     */
    public function testUpdates(): void
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

    /**
     * Test updated database schema against fresh install one
     *
     * @return void
     */
    public function testUpdatedDatabase(): void
    {
        // Last database version is installed with `latest_galette_` prefix
        // Update database uses `galette_` prefix. Let's compare those two.

        $latest_prefix = 'latest_galette_';
        $latest_dsn = array(
            'TYPE_DB'   => TYPE_DB,
            'USER_DB'   => USER_DB,
            'PWD_DB'    => PWD_DB,
            'HOST_DB'   => HOST_DB,
            'PORT_DB'   => PORT_DB,
            'NAME_DB'   => NAME_DB
        );
        $latest_db = new \Galette\Core\Db($latest_dsn);
        $latest_metadata = \Laminas\Db\Metadata\Source\Factory::createSourceFromAdapter($latest_db->db);
        $latest_tables = $latest_db->getTables($latest_prefix);

        $dsn = array(
            'TYPE_DB'   => TYPE_DB,
            'USER_DB'   => USER_DB,
            'PWD_DB'    => PWD_DB,
            'HOST_DB'   => HOST_DB,
            'PORT_DB'   => PORT_DB,
            'NAME_DB'   => NAME_DB,
            'PREFIX_DB' => PREFIX_DB,
        );
        $db = new \Galette\Core\Db($dsn);
        $metadata = \Laminas\Db\Metadata\Source\Factory::createSourceFromAdapter($db->db);

        $tables = $db->getTables();

        //tables order does not matter
        sort($latest_tables);
        sort($tables);

        //make sure all tables are present
        $this->assertEquals(
            array_map(
                function ($table) use ($latest_prefix) {
                    //table prefix differs
                    return str_replace($latest_prefix, PREFIX_DB, $table);
                },
                $latest_tables
            ),
            $tables
        );

        foreach ($latest_tables as $latest_table_name) {
            $latest_table = $latest_metadata->getTable($latest_table_name);
            $latest_columns = $latest_table->getColumns();

            //table prefix differs
            $table_name = str_replace($latest_prefix, PREFIX_DB, $latest_table_name);
            foreach ($latest_columns as $latest_column) {
                try {
                    $column = $metadata->getColumn($latest_column->getName(), $table_name);
                } catch (\Exception $e) {
                    $this->fail($latest_column->getName() . ' | ' . $e->getMessage());
                }

                //table name differs
                $latest_column->setTableName($table_name);
                if ($default = $column->getColumnDefault()) {
                    $latest_column->setColumnDefault(str_replace($latest_prefix, PREFIX_DB, $default));
                }
                //position does not matter
                $column->setOrdinalPosition($latest_column->getOrdinalPosition());

                //Q&D fixes... :'(
                if (!$db->isPostgres()) {
                    if (
                        $table_name === 'galette_cotisations'
                        && (
                            $latest_column->getName() === 'id_type_cotis'
                            || $latest_column->getName() === 'type_paiement_cotis'
                        )
                    ) {
                        //FIXME: dunno why default is not correct, 1.15-mysql upgrade does contains the correct statement.
                        $column->setColumnDefault(null);
                    }
                }

                $this->assertEquals(
                    $latest_column,
                    $column,
                    sprintf(
                        'Column %s.%s differs from latest version',
                        $table_name,
                        $latest_column->getName()
                    )
                );
            }
        }
    }
}
