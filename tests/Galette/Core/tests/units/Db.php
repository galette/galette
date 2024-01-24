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

use PHPUnit\Framework\TestCase;

/**
 * Database tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Db extends TestCase
{
    private \Galette\Core\Db $db;
    private array $have_warnings = [];

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->db = new \Galette\Core\Db();
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertEquals($this->db->getWarnings(), $this->have_warnings);
        }

        $this->db = new \Galette\Core\Db();
        $delete = $this->db->delete(\Galette\Entity\Title::TABLE);
        $delete->where([\Galette\Entity\Title::PK => '150']);
        $this->db->execute($delete);
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
        $this->assertSame(TYPE_DB, $type);

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
                $this->assertTrue($is_pg);
                $this->assertSame(\Galette\Core\Db::PGSQL, $type);
                break;
            case \Galette\Core\Db::MYSQL:
                $this->assertFalse($is_pg);
                $this->assertSame(\Galette\Core\Db::MYSQL, $type);
                break;
        }

        $this->expectException(\Exception::class);
        $dsn['TYPE_DB'] = 'DOES_NOT_EXISTS';
        $db = new \Galette\Core\Db($dsn);
    }

    /**
     * Test database connectivity
     *
     * @return void
     */
    public function testConnectivity()
    {
        $res = $this->db->testConnectivity(
            TYPE_DB,
            USER_DB,
            PWD_DB,
            HOST_DB,
            PORT_DB,
            NAME_DB
        );
        $this->assertTrue($res);
    }

    /**
     * Test database grants
     *
     * @return void
     */
    public function testGrant()
    {
        $result = $this->db->dropTestTable();

        $expected = array(
            'create' => true,
            'insert' => true,
            'select' => true,
            'update' => true,
            'delete' => true,
            'drop'   => true
        );
        $result = $this->db->grantCheck();

        $this->assertSame($expected, $result);

        //in update mode, we need alter
        $result = $this->db->grantCheck('u');

        $expected['alter'] = true;
        $this->assertSame($result, $expected);
    }

    /**
     * Test database grants that throws an exception
     *
     * @return void
     */
    public function testGrantWException()
    {
        $atoum = $this;

        //test insert failing
        $this->db = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $this->db->method('execute')
            ->will(
                $this->returnCallback(
                    function ($o) {
                        if ($o instanceof \Laminas\Db\Sql\Insert) {
                            throw new \LogicException('Error executing query!', 123);
                        }
                    }
                )
            );

        $result = $this->db->grantCheck('u');

        $this->assertTrue($result['create']);
        $this->assertTrue($result['alter']);
        $this->assertInstanceOf(\LogicException::class, $result['insert']);
        $this->assertFalse($result['update']);
        $this->assertFalse($result['select']);
        $this->assertFalse($result['delete']);
        $this->assertTrue($result['drop']);

        //test select failing
        $this->db = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $this->db->method('execute')
            ->will(
                $this->returnCallback(
                    function ($o) {
                        if ($o instanceof \Laminas\Db\Sql\Select) {
                            throw new \LogicException('Error executing query!', 123);
                        } else {
                            $rs = $this->getMockBuilder(\Laminas\Db\ResultSet\ResultSet::class)
                                ->onlyMethods(array('count'))
                                ->getMock();
                            $rs->method('count')
                                ->willReturn(1);
                            return $rs;
                        }
                    }
                )
            );

        $result = $this->db->grantCheck('u');

        $this->assertTrue($result['create']);
        $this->assertTrue($result['alter']);
        $this->assertTrue($result['insert']);
        $this->assertTrue($result['update']);
        $this->assertInstanceOf(\LogicException::class, $result['select']);
        $this->assertTrue($result['delete']);
        $this->assertTrue($result['drop']);

        //test update failing
        $this->db = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $this->db->method('execute')
            ->will(
                $this->returnCallback(
                    function ($o) {
                        if ($o instanceof \Laminas\Db\Sql\Update) {
                            throw new \LogicException('Error executing query!', 123);
                        } else {
                            $rs = $this->getMockBuilder(\Laminas\Db\ResultSet\ResultSet::class)
                                ->onlyMethods(array('count'))
                                ->getMock();
                            $rs->method('count')
                                ->willReturn(1);
                            return $rs;
                        }
                    }
                )
            );

        $result = $this->db->grantCheck('u');

        $this->assertTrue($result['create']);
        $this->assertTrue($result['alter']);
        $this->assertTrue($result['insert']);
        $this->assertInstanceOf(\LogicException::class, $result['update']);
        $this->assertTrue($result['select']);
        $this->assertTrue($result['delete']);
        $this->assertTrue($result['drop']);

        //test delete failing
        $this->db = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();

        $this->db->method('execute')
            ->will(
                $this->returnCallback(
                    function ($o) {
                        if ($o instanceof \Laminas\Db\Sql\Delete) {
                            throw new \LogicException('Error executing query!', 123);
                        } else {
                            $rs = $this->getMockBuilder(\Laminas\Db\ResultSet\ResultSet::class)
                                ->onlyMethods(array('count'))
                                ->getMock();
                            $rs->method('count')
                                ->willReturn(1);
                            return $rs;
                        }
                    }
                )
            );

        $result = $this->db->grantCheck('u');

        $this->assertTrue($result['create']);
        $this->assertTrue($result['alter']);
        $this->assertTrue($result['insert']);
        $this->assertTrue($result['update']);
        $this->assertTrue($result['select']);
        $this->assertInstanceOf(\LogicException::class, $result['delete']);
        $this->assertTrue($result['drop']);
    }

    /**
     * Is database Postgresql powered?
     *
     * @return void
     */
    public function testIsPostgres()
    {
        $is_pg = $this->db->isPostgres();

        switch (TYPE_DB) {
            case 'pgsql':
                $this->assertTrue($is_pg);
                break;
            default:
                $this->assertFalse($is_pg);
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
        switch (TYPE_DB) {
            case 'pgsql':
                $type = $this->db->type_db;
                $this->assertSame('pgsql', $type);
                break;
            case 'mysql':
                $type = $this->db->type_db;
                $this->assertSame('mysql', $type);
                break;
        }

        $db = $this->db->db;
        $this->assertInstanceOf('Laminas\Db\Adapter\Adapter', $db);

        $sql = $this->db->sql;
        $this->assertInstanceOf('Laminas\Db\Sql\Sql', $sql);

        $connection = $this->db->connection;
        $this->assertInstanceOf('Laminas\Db\Adapter\Driver\Pdo\Connection', $connection);

        $driver = $this->db->driver;
        $this->assertInstanceOf('Laminas\Db\Adapter\Driver\Pdo\Pdo', $driver);
    }

    /**
     * Test select
     *
     * @return void
     */
    public function testSelect()
    {
        $select = $this->db->select('preferences', 'p');
        $select->where(array('p.nom_pref' => 'pref_nom'));

        $results = $this->db->execute($select);

        $query = $this->db->query_string;

        $expected = 'SELECT "p".* FROM "galette_preferences" AS "p" ' .
            'WHERE "p"."nom_pref" = \'pref_nom\'';

        if (TYPE_DB === 'mysql') {
            $expected = 'SELECT `p`.* FROM `galette_preferences` AS `p` ' .
                'WHERE `p`.`nom_pref` = \'pref_nom\'';
        }

        $this->assertSame($expected, $query);
    }

    /**
     * Test selectAll
     *
     * @return void
     */
    public function testSelectAll()
    {
        $all = $this->db->selectAll('preferences');
        $this->assertInstanceOf('Laminas\Db\ResultSet\ResultSet', $all);
    }

    /**
     * Test insert
     *
     * @return void
     */
    public function testInsert()
    {
        $insert = $this->db->insert('titles');
        $data = [
            'id_title'      => '150',
            'short_label'   => 'Dr',
            'long_label'    => 'Doctor'
        ];
        $insert->values($data);
        $res = $this->db->execute($insert);

        $select = $this->db->select('titles', 't');
        $select->where(['t.id_title' => $data['id_title']]);

        $results = $this->db->execute($select);
        $this->assertSame(1, $results->count());

        if (TYPE_DB === 'pgsql') {
            $data['id_title'] = (int)$data['id_title'];
        }
        $this->assertEquals((array)$results->current(), $data);
    }

    /**
     * Test update
     *
     * @return void
     */
    public function testUpdate()
    {
        $insert = $this->db->insert('titles');
        $data = [
            'id_title'      => '150',
            'short_label'   => 'Dr',
            'long_label'    => 'Doctor'
        ];
        $insert->values($data);
        $res = $this->db->execute($insert);

        $update = $this->db->update('titles');
        $data = [
            'long_label'    => 'DoctorS'
        ];
        $where = ['id_title' => 150];

        $select = $this->db->select('titles', 't');
        $select->columns(['long_label']);
        $select->where($where);
        $results = $this->db->execute($select);

        $long_label = $results->current()->long_label;
        $this->assertSame('Doctor', $long_label);

        $update->set($data);
        $update->where($where);
        $res = $this->db->execute($update);
        $this->assertSame(1, $res->count());

        $results = $this->db->execute($select);
        $this->assertSame(1, $results->count());

        $long_label = $results->current()->long_label;
        $this->assertSame('DoctorS', $long_label);
    }

    /**
     * Test delete
     *
     * @return void
     */
    public function testDelete()
    {
        $insert = $this->db->insert('titles');
        $data = [
            'id_title'      => '150',
            'short_label'   => 'Dr',
            'long_label'    => 'Doctor'
        ];
        $insert->values($data);
        $res = $this->db->execute($insert);

        $delete = $this->db->delete('titles');
        $where = ['id_title' => 150];

        $select = $this->db->select('titles', 't');
        $select->where($where);
        $results = $this->db->execute($select);
        $this->assertSame(1, $results->count());

        $delete->where($where);
        $res = $this->db->execute($delete);
        $this->assertSame(1, $res->count());

        $results = $this->db->execute($select);
        $this->assertSame(0, $results->count());
    }

    /**
     * Test database version
     *
     * @return void
     */
    public function testDbVersion()
    {
        $db_version = $this->db->getDbVersion();
        $this->assertSame(GALETTE_DB_VERSION, $db_version);

        $res = $this->db->checkDbVersion();
        $this->assertTrue($res);
    }

    /**
     * Test database version that throws an exception
     *
     * @return void
     */
    public function testDbVersionWException()
    {
        $this->db = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(array('execute'))
            ->getMock();
        $this->db->method('execute')
            ->will(
                $this->returnCallback(
                    function ($table, $where) {
                        throw new \LogicException('Error executing query!', 123);
                    }
                )
            );

        $this->expectException('LogicException');
        $this->db->getDbVersion();
        $this->assertFalse($this->db->checkDbVersion());
    }

    /**
     * Test get columns method
     *
     * @return void
     */
    public function testGetColumns()
    {
        $cols = $this->db->getColumns('preferences');

        $this->assertCount(3, $cols);

        $columns = array();
        foreach ($cols as $c) {
            $columns[] = $c->getName();
        }

        $this->assertSame(
            array(
                'id_pref',
                'nom_pref',
                'val_pref'
            ),
            array_values($columns)
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
            'galette_paymenttypes',
            'galette_database',
            'galette_socials',
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
            'galette_searches',
            'galette_tmplinks'
        );

        $tables = $this->db->getTables();

        //tables created in grantCheck are sometimes
        //present here... :(
        if (in_array('galette_test', $tables)) {
            unset($tables[array_search('galette_test', $tables)]);
        }

        sort($tables);
        sort($expected);

        $this->assertSame($expected, $tables);
    }

    /**
     * Test UTF conversion, for MySQL only
     *
     * @return void
     */
    public function testConvertToUtf()
    {
        $convert = $this->db->convertToUTF();
        $this->assertNull($convert);
    }

    /**
     * Test get platform
     *
     * @return void
     */
    public function testGetPlatform()
    {
        $quoted = $this->db->platform->quoteValue('somethin\' to "quote"');

        $expected = ($this->db->isPostgres()) ?
            "'somethin'' to \"quote\"'" :
            "'somethin\\' to \\\"quote\\\"'";

        $this->assertSame($expected, $quoted);
    }

    /**
     * Test execute Method
     *
     * @return void
     */
    public function testExecute()
    {
        $select = $this->db->select('preferences', 'p');
        $select->where(['p.nom_pref' => 'azerty']);
        $results = $this->db->execute($select);

        $this->assertInstanceOf('\Laminas\Db\ResultSet\ResultSet', $results);
    }

    /**
     * Test execute Method
     *
     * @return void
     */
    public function testExecuteWException()
    {
        $this->have_warnings = [
            new \ArrayObject(
                [
                    'Level' => 'Error',
                    'Code' => 1054,
                    'Message' => "Unknown column 'p.notknown' in 'where clause'"
                ]
            )
        ];
        $select = $this->db->select('preferences', 'p');
        $select->where(['p.nom_pref' => 'azerty']);
        $select->where(['p.notknown' => 'azerty']);

        $this->expectException('\PDOException');
        $results = $this->db->execute($select);
    }

    /**
     * Test serialization
     *
     * @return void
     */
    public function testSerialization()
    {
        $db = $this->db;
        $serialized = serialize($db);
        $this->assertNotNull($serialized);

        $unserialized = unserialize($serialized);
        $this->assertInstanceOf('Galette\Core\Db', $unserialized);
    }
}
