<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Telemetry tests
 *
 * PHP version 5
 *
 * Copyright © 2017 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Util
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2017-10-07
 */

namespace Galette\Util\test\units;

use \atoum;

/**
 * Telemetry tests class
 *
 * @category  Util
 * @name      Telemetry
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-10-07
 */
class Telemetry extends atoum
{
    private $zdb;
    private $preferences;
    private $plugins;

    /**
     * Tear down tests
     *
     * @param string $method Method tested
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        $this->preferences->pref_instance_uuid = '';
        $this->preferences->pref_registration_uuid = '';
        $this->preferences->store();
        return parent::afterTestMethod($method);
    }

    /**
     * Set up tests
     *
     * @param string $testMethod Method tested
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
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
        $this->string($result['uuid'])
            ->hasLength(40);
        $expected['uuid'] = $result['uuid'];
        $this->array($result)->isIdenticalTo($expected);

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
        $this->array($result)->isIdenticalTo($expected);

        $telemetry = new \mock\Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $this->calling($telemetry)->getCount = function ($table, $where) {
            switch ($table) {
                case \Galette\Entity\Adherent::TABLE:
                    return 56;
                case \Galette\Entity\Contribution::TABLE:
                    return 402;
                case \Galette\Entity\Transaction::TABLE:
                    return 100;
            }
            return 0;
        };
        $result = $telemetry->grabGaletteInfos();

        $expected['usage']['avg_members'] = '50-250';
        $expected['usage']['avg_contributions'] = '250-500';
        $expected['usage']['avg_transactions'] = '50-250';
        $this->array($result)->isIdenticalTo($expected);
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

        $this->string($infos['engine'])->isNotEmpty();
        $this->string($infos['version'])->isNotEmpty();
        $this->variable($infos['size'])->isNotNull();
        if (!$this->zdb->isPostgres()) {
            //no sql mode for postgres databases
            $this->string($infos['sql_mode'])->isNotEmpty();
        }
    }

    /**
     * Test web server infos
     *
     * @return void
     */
    public function testGrabWebserverInfos()
    {
        $telemetry = new \mock\Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->grabWebserverInfos();

        $this->array($result)
            ->hasSize(2)
            ->hasKeys(['engine', 'version']);
        //no webserver infos from CLI
        $this->string($result['engine'])->isEmpty();
        $this->string($result['version'])->isEmpty();
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
        $this->array($result)->isIdenticalTo($expected);
    }

    /**
     * Test OS infos
     *
     * @return void
     */
    public function testGrabOsInfos()
    {
        $expected = [
            'family'       => php_uname('s'),
            'distribution' => '',
            'version'      => php_uname('r')
        ];

        $telemetry = new \Galette\Util\Telemetry(
            $this->zdb,
            $this->preferences,
            $this->plugins
        );
        $result = $telemetry->grabOsInfos();
        $this->array($result)->isIdenticalTo($expected);
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

        $this->array($result)->keys->isEqualTo([
            'galette',
            'system'
        ]);

        $this->array($result['galette'])->keys->isEqualTo([
            'uuid',
            'version',
            'plugins',
            'default_language',
            'usage'
        ]);

        $this->array($result['system'])->keys->isEqualTo([
            'db',
            'web_server',
            'php',
            'os'
        ]);
    }
}
