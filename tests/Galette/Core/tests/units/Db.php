<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dadatabse tests
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
 * @since     2013-02-05
 */

namespace Galette\Core\test\units;

use \atoum;

/**
 * Database tests class
 *
 * @category  Core
 * @name      Db
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-02-05
 */
class Db extends atoum
{
    private $_db;

    /**
     * Set up tests
     *
     * @param stgring $testMethod Method tested
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->_db = new \Galette\Core\Db();
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor()
    {
        $db = new \Galette\Core\Db();
        $type = $db->type_db;
        $this->string($type)
            ->isIdenticalTo(TYPE_DB);

        $dsn = array(
            'TYPE_DB'   => TYPE_DB,
            'USER_DB'   => USER_DB,
            'PWD_DB'    => PWD_DB,
            'HOST_DB'   => HOST_DB,
            'PORT_DB'   => PORT_DB,
            'NAME_DB'   => NAME_DB
        );
        $db = new \Galette\Core\Db($dsn);

        $is_pg = $db->isPostgres();
        $zdb = $db->db;
        $type = $db->type_db;

        switch (TYPE_DB) {
        case 'pgsql':
            $this->boolean($is_pg)
                ->isTrue();
            $this->object($zdb)
                ->IsInstanceOf('Zend_Db_Adapter_Pdo_Pgsql');
            $this->string($type)
                ->isIdenticalTo(\Galette\Core\Db::PGSQL);
            break;
        case \Galette\Core\Db::MYSQL:
            $this->boolean($is_pg)
                ->isFalse();
            $this->object($zdb)
                ->IsInstanceOf('Zend_Db_Adapter_Pdo_Mysql');
            $this->string($type)
                ->isIdenticalTo(\Galette\Core\Db::MYSQL);
            break;
        case \galette\Core\Db::SQLITE:
            $this->boolean($is_pg)
                ->isFalse();
            $this->object($zdb)
                ->IsInstanceOf('Zend_Db_Adapter_Pdo_Sqlite');
            $this->string($type)
                ->isIdenticalTo(\Galette\Core\Db::SQLITE);
            break;
        }

        $dsn['TYPE_DB'] = \Galette\Core\Db::SQLITE;
        $db = new \Galette\Core\Db($dsn);

        $type = $db->type_db;
        $this->string($type)
            ->isIdenticalTo(\Galette\Core\Db::SQLITE);

        $this->exception(
            function () use ($dsn) {
                $dsn['TYPE_DB'] = 'DOES_NOT_EXISTS';
                $db = new \Galette\Core\Db($dsn);
            }
        );
    }

    /**
     * Test database connectivity
     *
     * @return void
     */
    public function testConnectivity()
    {
        $res = $this->_db->testConnectivity(
            TYPE_DB,
            USER_DB,
            PWD_DB,
            HOST_DB,
            PORT_DB,
            NAME_DB
        );
        $this->boolean($res)->isTrue();

        $res = $this->_db->testConnectivity(
            \Galette\Core\Db::SQLITE
        );
        $this->boolean($res)->isTrue();

    }

    /**
     * Test database grants
     *
     * @return void
     */
    public function testGrant()
    {
        $result = $this->_db->dropTestTable();

        $expected = array(
            'create' => true,
            'insert' => true,
            'select' => true,
            'update' => true,
            'delete' => true,
            'drop'   => true
        );
        $result = $this->_db->grantCheck();

        $this->array($result)
            ->hasSize(6)
            ->isIdenticalTo($expected);

        //in update mode, we need alter
        $result = $this->_db->grantCheck('u');

        if ( TYPE_DB !== \Galette\Core\Db::SQLITE ) {
            $expected['alter'] = true;
            $this->array($result)
                ->hasSize(7)
                ->isIdenticalTo($expected);
        } else {
            //for SQLITE, ALTER will not work.
            $alter = $result['alter'];
            $this->object($alter)->IsInstanceOf('\PDOException');

            unset($result['alter']);
            $this->array($result)
                ->hasSize(6)
                ->isIdenticalTo($expected);
        }
    }

    /**
     * Is database Postgresql powered?
     *
     * @return void
     */
    public function testIsPostgres()
    {
        $is_pg = $this->_db->isPostgres();

        switch (TYPE_DB) {
        case 'pgsql':
            $this->boolean($is_pg)
                ->isTrue();
            break;
        default:
            $this->boolean($is_pg)
                ->isFalse();
            break;
        }
    }

    /**
     * Test getters
     *
     * @return void
     */
    public function testGetters()
    {
        $db = $this->_db->db;

        switch(TYPE_DB) {
        case 'pgsql':
            $this->object($db)
                ->IsInstanceOf('Zend_Db_Adapter_Pdo_Pgsql');
            $type = $this->_db->type_db;
            $this->string($type)
                ->isIdenticalTo('pgsql');
            break;
        case 'mysql':
            $this->object($db)
                ->IsInstanceOf('Zend_Db_Adapter_Pdo_Mysql');
            $type = $this->_db->type_db;
            $this->string($type)
                ->isIdenticalTo('mysql');
            break;
        case 'sqlite':
            $this->object($db)
                ->IsInstanceOf('Zend_Db_Adapter_Pdo_Sqlite');
            $type = $this->_db->type_db;
            $this->string($type)
                ->isIdenticalTo('sqlite');
            break;
        }
    }

    /**
     * Test database version
     *
     * @return void
     */
    public function testDbVersion()
    {
        $res = $this->_db->checkDbVersion();

        $this->boolean($res)->isTrue();
    }

    /**
     * Test tables count
     *
     * FIXME: this test will fail if some plugins tables are present
     *
     * @return void
     */
    public function testTables()
    {
        $expected = array (
            'galette_groups_members',
            'galette_transactions',
            'galette_titles',
            'galette_types_cotisation',
            'galette_database',
            'galette_statuts',
            'galette_texts',
            'galette_logs',
            'galette_groups',
            'galette_reminders',
            'galette_groups_managers',
            'galette_dynamic_fields',
            'galette_fields_config',
            'galette_tmppasswds',
            'galette_pictures',
            'galette_adherents',
            'galette_l10n',
            'galette_import_model',
            'galette_cotisations',
            'galette_field_types',
            'galette_fields_categories',
            'galette_mailing_history',
            'galette_pdfmodels',
            'galette_preferences',
        );

        $tables = $this->_db->getTables();

        //tables created in grantCheck il sometimes
        //presnet here... :(
        if ( in_array('galette_test', $tables) ) {
            unset($tables[array_search('galette_test', $tables)]);
        }

        sort($tables);
        sort($expected);

        $this->array($tables)
            ->hasSize(24)
            ->isIdenticalTo($expected);
    }

    /**
     * Tests plugins load
     *
     * @return void
     */
    public function testGetUpgradeScripts()
    {
        $update_scripts = \Galette\Core\Db::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            'pgsql',
            '0.6'
        );

        $knowns = array(
            '0.60' => 'upgrade-to-0.60-pgsql.sql',
            '0.61' => 'upgrade-to-0.61-pgsql.sql',
            '0.62' => 'upgrade-to-0.62-pgsql.sql',
            '0.63' => 'upgrade-to-0.63-pgsql.sql',
            '0.70' => 'upgrade-to-0.70-pgsql.sql',
            '0.71' => 'upgrade-to-0.71-pgsql.sql',
            '0.74' => 'upgrade-to-0.74-pgsql.sql',
            '0.75' => 'upgrade-to-0.75-pgsql.sql',
            '0.76' => 'upgrade-to-0.76-pgsql.sql'
        );

        //as of 0.7.6, we got 9 update scripts total
        $this->array($update_scripts)
            ->hasSize(9)
            ->isIdenticalTo($knowns);

        $update_scripts = \Galette\Core\Db::getUpdateScripts(
            GALETTE_BASE_PATH . '/install',
            'pgsql',
            '0.7'
        );

        //if we're from 0.7.0, there are only 5 update scripts left
        $this->array($update_scripts)
            ->hasSize(5);

        $update_scripts = \Galette\Core\Db::getUpdateScripts(
            GALETTE_BASE_PATH . '/install'
        );

        //without specifying database nor version, we got 9 update scripts total
        $this->array(array_values($update_scripts))
            ->hasSize(9)
            ->isEqualTo(array_keys($knowns));
    }

    /**
     * Test UTF conversion, for MySQL only
     *
     * @return void
     */
    public function testConvertToUtf()
    {
        if ( TYPE_DB === \Galette\Core\Db::MYSQL ) {
            $convert = $this->_db->convertToUTF();

            $this->variable($convert)->isNull();
        }
    }
}
