<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Update tests
 *
 * PHP version 5
 *
 * Copyright © 2021 The Galette Team
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
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2021-05-06
 */

namespace Galette\Core\test\units;

use atoum;

/**
 * Update tests
 *
 * @category  Core
 * @name      Install
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-05-06
 */
class Install extends atoum
{
    private $install;
    private $zdb;
    private $flash_data;
    private $flash;
    private $mocked_router;
    private $container;

    /**
     * Set up tests
     *
     * @param stgring $method Method tested
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        setlocale(LC_ALL, 'en_US');

        $this->mocked_router = new \mock\Slim\Router();
        $this->calling($this->mocked_router)->pathFor = function ($name, $params) {
            return $name;
        };
        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        $app =  new \Galette\Core\SlimApp();
        $plugins = new \Galette\Core\Plugins();
        require GALETTE_BASE_PATH . '/includes/dependencies.php';
        $container = $app->getContainer();
        $_SERVER['HTTP_HOST'] = '';

        $container->set('flash', $this->flash);
        $container->set(Slim\Flash\Messages::class, $this->flash);
        $container->set('router', $this->mocked_router);
        $container->set(Slim\Router::class, $this->mocked_router);

        $this->container = $container;

        $this->zdb = $container->get('zdb');
    }

    /**
     * Tear down tests
     *
     * @param string $method Calling method
     *
     * @return void
     */
    public function afterTestMethod($method)
    {
        if (TYPE_DB === 'mysql') {
            $this->array($this->zdb->getWarnings())->isIdenticalTo([]);
        }
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
        $this->array($update_scripts)->size->isGreaterThan(5);

        $install->setMode(\Galette\Core\Install::UPDATE);
        $errors = [];
        $install->setDbType($this->zdb->type_db, $errors);
        $this->array($errors)->isIdenticalTo([]);

        $install->setInstalledVersion('0.60');
        $install->setTablesPrefix(PREFIX_DB);
        $exec = $install->executeScripts($this->zdb, GALETTE_BASE_PATH . '/install');

        $report = $install->getInitializationReport();
        foreach ($report as $entry) {
            $this->boolean($entry['res'])->isTrue(($entry['debug'] ?? '') . "\n" . ($entry['query'] ?? ''));
        }

        $this->boolean($exec)->isTrue();
        $this->string($this->zdb->getDbVersion())->isIdenticalTo(GALETTE_DB_VERSION);
    }
}
