<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Plugins tests
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */

namespace Galette\Core\test\units;

use \atoum;

/**
 * Plugins tests class
 *
 * @category  Core
 * @name      Plugins
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-13
 */
class Plugins extends atoum
{
    private $_plugins;

    private $_plugin2 = array(
        'root'          => 'plugin-test2',
        'name'          => 'Galette Test2 Plugin',
        'desc'          => 'Test two plugin',
        'author'        => 'Johan Cwiklinski',
        'version'       => '1.0',
        'permissions'   => null,
        'date'          => '2013-12-15',
        'priority'      => 1000,
        'root_writable' => true
    );

    /**
     * Set up tests
     *
     * @param stgring $testMethod Method tested
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->_plugins = new \Galette\Core\Plugins();
        $this->_plugins->loadModules(GALETTE_PLUGINS_PATH);

        $this->_plugin2['root'] = GALETTE_PLUGINS_PATH .
            $this->_plugin2['root'];
    }


    /**
     * Tests plugins load
     *
     * @return void
     */
    public function testLoadModules()
    {
        $plugins = new \Galette\Core\Plugins();
        $plugins->loadModules(GALETTE_PLUGINS_PATH);

        $this->array($this->_plugins->getModules())
            ->hasSize(3);

        $loaded_plugin = $this->_plugins->getModules('plugin-test2');
        $loaded_plugin['date'] = $this->_plugin2['date'];

        $this->variable($loaded_plugin)
            ->isIdenticalTo($this->_plugin2);
    }

    /**
     * Test module existence
     *
     * @return void
     */
    public function testModuleExists()
    {
        $this->boolean($this->_plugins->moduleExists('plugin-test2'))
            ->isTrue();
        $this->boolean($this->_plugins->moduleExists('plugin-disabled'))
            ->isFalse();
    }

    /**
     * Test disabled plugin
     *
     * @return void
     */
    public function testDisabledModules()
    {
        $this->array($this->_plugins->getDisabledModules())
            ->hasKeys(
                array(
                    'plugin-disabled',
                    'plugin-unversionned',
                    'plugin-oldversion'
                )
            );
    }

    /**
     * Test module root
     *
     * @return void
     */
    public function testModuleRoot()
    {
        $this->variable($this->_plugins->moduleRoot('plugin-test2'))
            ->isIdenticalTo($this->_plugin2['root']);
    }

    /**
     * Test templates path
     *
     * @return void
     */
    /*public function testGetTemplatesPath()
    {
        //FIXME:
        //  - at the moment, there is no config for preferences, so default theme is empty
        //  - remove global $preferences to have this one working as expected...
        $this->variable($this->_plugins->getTemplatesPath('plugin-test2'))
            ->isIdenticalTo($this->_plugin2['root'] . '/templates/');
    }*/

    /**
     * Test reset modules list
     *
     * @return void
     */
    public function testResetModulesList()
    {
        $this->_plugins->resetModulesList();

        $this->array($this->_plugins->getModules())
            ->isempty();
    }

    /**
     * Test plugin (des)activation
     *
     * @return void
     */
    public function testModuleActivation()
    {
        $this->array($this->_plugins->getModules())
            ->hasKey('plugin-test2');
        $this->_plugins->deactivateModule('plugin-test2');

        $this->_plugins = new \Galette\Core\Plugins();
        $this->_plugins->loadModules(GALETTE_PLUGINS_PATH);
        $this->array($this->_plugins->getModules())
            ->notHasKey('plugin-test2');
        $this->_plugins->activateModule('plugin-test2');

        $this->_plugins = new \Galette\Core\Plugins();
        $this->_plugins->loadModules(GALETTE_PLUGINS_PATH);
        $this->array($this->_plugins->getModules())
            ->hasKey('plugin-test2');

        $this->exception(
            function () {
                $plugins = new \Galette\Core\Plugins();
                $plugins->loadModules(GALETTE_PLUGINS_PATH);
                $plugins->deactivateModule('nonexistant');
            }
        )->hasMessage(_T('No such module.'));

        $this->exception(
            function () {
                $plugins = new \Galette\Core\Plugins();
                $plugins->loadModules(GALETTE_PLUGINS_PATH);
                $plugins->activateModule('nonexistant');
            }
        )->hasMessage(_T('No such module.'));
    }

    /**
     * Test if plugin needs database
     *
     * @return void
     */
    public function testNeedDatabse()
    {
        $this->boolean($this->_plugins->needsDatabase('plugin-db'))
            ->isTrue();
        $this->boolean($this->_plugins->needsDatabase('plugin-test2'))
            ->isFalse();

        $this->exception(
            function () {
                $plugins = new \Galette\Core\Plugins();
                $plugins->loadModules(GALETTE_PLUGINS_PATH);
                $plugins->needsDatabase('nonexistant');
            }
        )->hasMessage(_T('Module does not exists!'));
    }
}
