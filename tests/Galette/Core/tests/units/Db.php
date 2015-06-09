<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dadatabse tests
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @copyright 2013-2014 The Galette Team
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
 * @copyright 2013-2014 The Galette Team
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
        $type = $db->type_db;

        switch (TYPE_DB) {
        case 'pgsql':
            $this->boolean($is_pg)
                ->isTrue();
            $this->string($type)
                ->isIdenticalTo(\Galette\Core\Db::PGSQL);
            break;
        case \Galette\Core\Db::MYSQL:
            $this->boolean($is_pg)
                ->isFalse();
            $this->string($type)
                ->isIdenticalTo(\Galette\Core\Db::MYSQL);
            break;
        }

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

        $expected['alter'] = true;
        $this->array($result)
            ->hasSize(7)
            ->isIdenticalTo($expected);
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
        switch(TYPE_DB) {
        case 'pgsql':
            $type = $this->_db->type_db;
            $this->string($type)
                ->isIdenticalTo('pgsql');
            break;
        case 'mysql':
            $type = $this->_db->type_db;
            $this->string($type)
                ->isIdenticalTo('mysql');
            break;
        }

        $db = $this->_db->db;
        $this->object($db)->IsInstanceOf('Zend\Db\Adapter\Adapter');

        $sql = $this->_db->sql;
        $this->object($sql)->IsInstanceOf('Zend\Db\Sql\Sql');

        $connection = $this->_db->connection;
        $this->object($connection)
            ->IsInstanceOf('Zend\Db\Adapter\Driver\Pdo\Connection');

        $driver = $this->_db->driver;
        $this->object($driver)
            ->IsInstanceOf('Zend\Db\Adapter\Driver\Pdo\Pdo');
    }

    /**
     * Test select
     *
     * @return void
     */
    public function testSelect()
    {
        $select = $this->_db->select('preferences', 'p');
        $select->where(array('p.nom_pref' => 'pref_nom'));
        $results = $this->_db->execute($select);
        $query = $this->_db->query_string;

        $expected = 'SELECT "p".* FROM "galette_preferences" AS "p" ' .
            'WHERE "p"."nom_pref" = \'pref_nom\'';

        if ( TYPE_DB === 'mysql' ) {
            $expected = 'SELECT `p`.* FROM `galette_preferences` AS `p` ' .
                'WHERE `p`.`nom_pref` = \'pref_nom\'';
        }

        $this->string($query)->isIdenticalTo($expected);
    }

    /**
     * Test database version
     *
     * @return void
     */
    public function testDbVersion()
    {
        $db_version = $this->_db->getDbVersion();
        $this->variable($db_version)->isIdenticalTo(GALETTE_DB_VERSION);

        $res = $this->_db->checkDbVersion();
        $this->boolean($res)->isTrue();
    }

    /**
     * Test get columns method
     *
     * @return void
     */
    public function testGetColumns()
    {
        $cols = $this->_db->getColumns('preferences');

        $this->array($cols)->hasSize(3);

        $columns = array();
        foreach ( $cols as $c ) {
            $columns[] = $c->getName();
        }

        $this->array($columns)
            ->containsValues(
                array(
                    'id_pref',
                    'nom_pref',
                    'val_pref'
                )
            );
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
