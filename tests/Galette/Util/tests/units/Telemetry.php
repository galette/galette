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

namespace Galette\Util\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Telemetry tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Telemetry extends TestCase
{
    private \Galette\Core\Db $zdb;
    private \Galette\Core\Preferences $preferences;
    private \Galette\Core\Plugins $plugins;

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

        $this->preferences->pref_instance_uuid = '';
        $this->preferences->pref_registration_uuid = '';
        $this->preferences->store();
    }

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->preferences = new \Galette\Core\Preferences($this->zdb);

        $this->plugins = new \Galette\Core\Plugins();
    }

    /**
     * Test Galette infos
     *
     * @return void
     */
    public function testGrabGaletteInfos()
    {
        $expected = [
            'uuid'               => 'TO BE SET',
            'version'            => GALETTE_VERSION,
            'plugins'            => [],
            'default_language'   => \Galette\Core\I18n::DEFAULT_LANG,
            'usage'              => [
                'avg_members'       => '0-50',
                'avg_contributions' => '0-50',
                'avg_transactions'  => '0-50'
            ]
        ];

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $result = $telemetry->grabGaletteInfos();
        $this->assertSame(40, strlen($result['uuid']));
        $expected['uuid'] = $result['uuid'];
        $this->assertSame($expected, $result);

        $this->plugins->loadModules($this->preferences, GALETTE_PLUGINS_PATH);
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $plugins_list = $this->plugins->getModules();
        foreach ($plugins_list as $plugin) {
            $expected['plugins'][] = [
                'key'       => $plugin['name'],
                'version'   => $plugin['version']
            ];
        }

        $result = $telemetry->grabGaletteInfos();
        $this->assertSame($expected, $result);

        $telemetry = $this->getMockBuilder(\Galette\Util\Telemetry::class)
            ->setConstructorArgs([$this->zdb, $this->preferences, $this->plugins])
            ->onlyMethods(array('getCount'))
            ->getMock();
        $telemetry->method('getCount')
            ->willReturnCallback(
                function ($table) {
                    switch ($table) {
                        case \Galette\Entity\Adherent::TABLE:
                            return 56;
                        case \Galette\Entity\Contribution::TABLE:
                            return 402;
                        case \Galette\Entity\Transaction::TABLE:
                            return 100;
                    }
                    return 0;
                }
            );
        $result = $telemetry->grabGaletteInfos();

        $expected['usage']['avg_members'] = '50-250';
        $expected['usage']['avg_contributions'] = '250-500';
        $expected['usage']['avg_transactions'] = '50-250';
        $this->assertSame($expected, $result);
    }

    /**
     * Test DB infos
     *
     * @return void
     */
    public function testGrabDbInfos()
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );

        $infos  = $telemetry->grabDbInfos();

        $this->assertNotEmpty($infos['engine']);
        $this->assertNotEmpty($infos['version']);
        $this->assertNotNull($infos['size']);
        if (!$this->zdb->isPostgres()) {
            //no sql mode for postgres databases
            $this->assertNotEmpty($infos['sql_mode']);
        }
    }

    /**
     * Test web server infos
     *
     * @return void
     */
    public function testGrabWebserverInfos()
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->grabWebserverInfos();

        $this->assertSame(['engine', 'version'], array_keys($result));
        //no webserver infos from CLI
        $this->assertEmpty($result['engine']);
        $this->assertEmpty($result['version']);
    }

    /**
     * Test PHP infos
     *
     * @return void
     */
    public function testGrabPhpInfos()
    {
        $expected = [
            'version'   => str_replace(PHP_EXTRA_VERSION, '', PHP_VERSION),
            'modules'   => get_loaded_extensions(),
            'setup'     => [
                'max_execution_time'    => ini_get('max_execution_time'),
                'memory_limit'          => ini_get('memory_limit'),
                'post_max_size'         => ini_get('post_max_size'),
                'safe_mode'             => ini_get('safe_mode'),
                'session'               => ini_get('session.save_handler'),
                'upload_max_filesize'   => ini_get('upload_max_filesize')
            ]
        ];

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->grabPhpInfos();
        $this->assertSame($expected, $result);
    }

    /**
     * Test OS infos
     *
     * @return void
     */
    public function testGrabOsInfos()
    {
        $distro = '';
        if (file_exists('/etc/redhat-release')) {
            $distro = preg_replace('/\s+$/S', '', file_get_contents('/etc/redhat-release'));
        }
        if (file_exists('/etc/fedora-release')) {
            $distro = preg_replace('/\s+$/S', '', file_get_contents('/etc/fedora-release'));
        }

        $expected = [
            'family'       => php_uname('s'),
            'distribution' => $distro,
            'version'      => php_uname('r')
        ];

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->grabOsInfos();
        $this->assertSame($expected, $result);
    }

    /**
     * Test whole Telemetry infos
     *
     * @return void
     */
    public function testGetTelemetryInfos()
    {
        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->getTelemetryInfos();

        $this->assertSame(
            array_keys($result),
            [
                'galette',
                'system'
            ]
        );

        $this->assertSame(
            array_keys($result['galette']),
            [
                'uuid',
                'version',
                'plugins',
                'default_language',
                'usage'
            ]
        );

        $this->assertSame(
            array_keys($result['system']),
            [
                'db',
                'web_server',
                'php',
                'os'
            ]
        );
    }
}
