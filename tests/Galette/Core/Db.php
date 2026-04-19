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

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Database tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Db extends BaseGaletteTestCase
{
    /**
     * Test constructor
     */
    public function testConstructor(): void
    {
        $db = new \Galette\Core\Db();
        $type = $db->type_db;
        $this->assertSame(TYPE_DB, $type);

        $dsn = [
            'TYPE_DB'   => TYPE_DB,
            'USER_DB'   => USER_DB,
            'PWD_DB'    => PWD_DB,
            'HOST_DB'   => HOST_DB,
            'PORT_DB'   => PORT_DB,
            'NAME_DB'   => NAME_DB
        ];
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

        $exception_thrown = false;
        try {
            $dsn['TYPE_DB'] = 'DOES_NOT_EXISTS';
            new \Galette\Core\Db($dsn);
        } catch (\Exception) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);
        $this->expectLogEntry(
            \Analog\Analog::ALERT,
            '[Db] Error (0|Type DOES_NOT_EXISTS not known'
        );
    }

    /**
     * Test database connectivity
     */
    public function testConnectivity(): void
    {
        $res = $this->zdb->testConnectivity(
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
     */
    public function testGrant(): void
    {
        $db = new \Galette\Core\Db();
        $db->dropTestTable();

        $expected = [
            'create' => true,
            'insert' => true,
            'select' => true,
            'update' => true,
            'delete' => true,
            'drop'   => true
        ];
        $result = $db->grantCheck();

        $this->assertSame($expected, $result);

        //in update mode, we need alter
        $result = $db->grantCheck('u');

        $expected['alter'] = true;
        $this->assertSame($result, $expected);
    }

    /**
     * Test database grants that throws an exception
     */
    public function testGrantWException(): void
    {
        //test insert failing
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $this->zdb->method('execute')
            ->willReturnCallback(
                function ($o): void {
                    if ($o instanceof \Laminas\Db\Sql\Insert) {
                        throw new \LogicException('Error executing query!', 123);
                    }
                }
            );

        $result = $this->zdb->grantCheck('u');

        $this->assertTrue($result['create']);
        $this->assertTrue($result['alter']);
        $this->assertInstanceOf(\LogicException::class, $result['insert']);
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Cannot INSERT records | Error executing query!'
        );
        $this->assertFalse($result['update']);
        $this->assertFalse($result['select']);
        $this->assertFalse($result['delete']);
        $this->assertTrue($result['drop']);

        //test select failing
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $this->zdb->method('execute')
            ->willReturnCallback(
                function ($o) {
                    if ($o instanceof \Laminas\Db\Sql\Select) {
                        throw new \LogicException('Error executing query!', 123);
                    } else {
                        $rs = $this->getMockBuilder(\Laminas\Db\ResultSet\ResultSet::class)
                            ->onlyMethods(['count'])
                            ->getMock();
                        $rs->method('count')
                            ->willReturn(1);
                        return $rs;
                    }
                }
            );

        $result = $this->zdb->grantCheck('u');

        $this->assertTrue($result['create']);
        $this->assertTrue($result['alter']);
        $this->assertTrue($result['insert']);
        $this->assertTrue($result['update']);
        $this->assertInstanceOf(\LogicException::class, $result['select']);
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Cannot SELECT records | Error executing query!'
        );
        $this->assertTrue($result['delete']);
        $this->assertTrue($result['drop']);

        //test update failing
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $this->zdb->method('execute')
            ->willReturnCallback(
                function ($o) {
                    if ($o instanceof \Laminas\Db\Sql\Update) {
                        throw new \LogicException('Error executing query!', 123);
                    } else {
                        $rs = $this->getMockBuilder(\Laminas\Db\ResultSet\ResultSet::class)
                            ->onlyMethods(['count'])
                            ->getMock();
                        $rs->method('count')
                            ->willReturn(1);
                        return $rs;
                    }
                }
            );

        $result = $this->zdb->grantCheck('u');

        $this->assertTrue($result['create']);
        $this->assertTrue($result['alter']);
        $this->assertTrue($result['insert']);
        $this->assertInstanceOf(\LogicException::class, $result['update']);
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Cannot UPDATE records | Error executing query!'
        );
        $this->assertTrue($result['select']);
        $this->assertTrue($result['delete']);
        $this->assertTrue($result['drop']);

        //test delete failing
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $this->zdb->method('execute')
            ->willReturnCallback(
                function ($o) {
                    if ($o instanceof \Laminas\Db\Sql\Delete) {
                        throw new \LogicException('Error executing query!', 123);
                    } else {
                        $rs = $this->getMockBuilder(\Laminas\Db\ResultSet\ResultSet::class)
                            ->onlyMethods(['count'])
                            ->getMock();
                        $rs->method('count')
                            ->willReturn(1);
                        return $rs;
                    }
                }
            );

        $result = $this->zdb->grantCheck('u');

        $this->assertTrue($result['create']);
        $this->assertTrue($result['alter']);
        $this->assertTrue($result['insert']);
        $this->assertTrue($result['update']);
        $this->assertTrue($result['select']);
        $this->assertInstanceOf(\LogicException::class, $result['delete']);
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Cannot DELETE records | Error executing query!'
        );
        $this->assertTrue($result['drop']);
    }

    /**
     * Is database Postgresql powered?
     */
    public function testIsPostgres(): void
    {
        $is_pg = $this->zdb->isPostgres();

        match (TYPE_DB) {
            'pgsql' => $this->assertTrue($is_pg), // @phpstan-ignore match.alwaysTrue (TYPE_DB is a constant, not a variable)
            default => $this->assertFalse($is_pg),
        };
    }

    /**
     * Test getters
     */
    public function testGetters(): void
    {
        switch (TYPE_DB) {
            case 'pgsql':
                $type = $this->zdb->type_db;
                $this->assertSame('pgsql', $type);
                break;
            case 'mysql':
                $type = $this->zdb->type_db;
                $this->assertSame('mysql', $type);
                break;
        }

        $db = $this->zdb->db;
        $this->assertInstanceOf(\Laminas\Db\Adapter\Adapter::class, $db);

        $sql = $this->zdb->sql;
        $this->assertInstanceOf(\Laminas\Db\Sql\Sql::class, $sql);

        $connection = $this->zdb->connection;
        $this->assertInstanceOf(\Laminas\Db\Adapter\Driver\Pdo\Connection::class, $connection);

        $driver = $this->zdb->driver;
        $this->assertInstanceOf(\Laminas\Db\Adapter\Driver\Pdo\Pdo::class, $driver);
    }

    /**
     * Test getters with exception
     */
    public function testGetterWException(): void
    {
        $this->expectExceptionMessage('Unknown property non_existing');
        $this->zdb->non_existing; //@phpstan-ignore property.notFound,expr.resultUnused (we want to test that exception is thrown)
    }

    /**
     * Test select
     */
    public function testSelect(): void
    {
        $select = $this->zdb->select('preferences', 'p');
        $select->where(['p.nom_pref' => 'pref_nom']);

        $this->zdb->execute($select);

        $query = $this->zdb->query_string;

        $expected = 'SELECT "p".* FROM "galette_preferences" AS "p" '
            . 'WHERE "p"."nom_pref" = \'pref_nom\'';

        if (!$this->zdb->isPostgres()) {
            $expected = 'SELECT `p`.* FROM `galette_preferences` AS `p` '
                . 'WHERE `p`.`nom_pref` = \'pref_nom\'';
        }

        $this->assertSame($expected, $query);
    }

    /**
     * Test selectAll
     */
    public function testSelectAll(): void
    {
        $all = $this->zdb->selectAll('preferences');
        $this->assertInstanceOf(\Laminas\Db\ResultSet\ResultSet::class, $all);
    }

    /**
     * Test insert
     */
    public function testInsert(): void
    {
        $insert = $this->zdb->insert('titles');
        $data = [
            'short_label'   => 'Dr',
            'long_label'    => 'Doctor'
        ];
        $insert->values($data);
        $this->zdb->execute($insert);

        $select = $this->zdb->select('titles', 't');
        $select->where(['t.short_label' => $data['short_label']]);

        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());

        $result = (array)$results->current();
        $this->assertSame($data['short_label'], $result['short_label']);
        $this->assertSame($data['long_label'], $result['long_label']);
    }

    /**
     * Test update
     */
    public function testUpdate(): void
    {
        $insert = $this->zdb->insert('titles');
        $data = [
            'short_label'   => 'Dr',
            'long_label'    => 'Doctor'
        ];
        $insert->values($data);
        $this->zdb->execute($insert);

        $update = $this->zdb->update('titles');
        $data = [
            'long_label'    => 'DoctorS'
        ];
        $where = ['short_label' => 'Dr'];

        $select = $this->zdb->select('titles', 't');
        $select->columns(['long_label']);
        $select->where($where);
        $results = $this->zdb->execute($select);

        $long_label = $results->current()->long_label;
        $this->assertSame('Doctor', $long_label);

        $update->set($data);
        $update->where($where);
        $res = $this->zdb->execute($update);
        $this->assertSame(1, $res->count());

        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());

        $long_label = $results->current()->long_label;
        $this->assertSame('DoctorS', $long_label);
    }

    /**
     * Test delete
     */
    public function testDelete(): void
    {
        $insert = $this->zdb->insert('titles');
        $data = [
            'short_label'   => 'Dr',
            'long_label'    => 'Doctor'
        ];
        $insert->values($data);
        $this->zdb->execute($insert);

        $delete = $this->zdb->delete('titles');
        $where = ['short_label' => 'Dr'];

        $select = $this->zdb->select('titles', 't');
        $select->where($where);
        $results = $this->zdb->execute($select);
        $this->assertSame(1, $results->count());

        $delete->where($where);
        $res = $this->zdb->execute($delete);
        $this->assertSame(1, $res->count());

        $results = $this->zdb->execute($select);
        $this->assertSame(0, $results->count());
    }

    /**
     * Test database version
     */
    public function testDbVersion(): void
    {
        $db_version = $this->zdb->getDbVersion();
        $this->assertSame(GALETTE_DB_VERSION, $db_version);

        $res = $this->zdb->checkDbVersion();
        $this->assertTrue($res);
    }

    /**
     * Test database version that throws an exception
     */
    public function testDbVersionWException(): void
    {
        $this->zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['execute'])
            ->getMock();
        $this->zdb->method('execute')
            ->willReturnCallback(
                function ($sql): void {
                    throw new \LogicException('Error executing query!', 123);
                }
            );

        $exception_thrown = false;
        try {
            $this->zdb->getDbVersion();
        } catch (\LogicException) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);
        $this->assertFalse($this->zdb->checkDbVersion());
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Cannot check database version: Error executing query!'
        );
    }

    /**
     * Test get columns method
     */
    public function testGetColumns(): void
    {
        $cols = $this->zdb->getColumns('preferences');

        $this->assertCount(3, $cols);

        $columns = [];
        foreach ($cols as $c) {
            $columns[] = $c->getName();
        }

        $this->assertSame(
            [
                'id_pref',
                'nom_pref',
                'val_pref'
            ],
            $columns
        );
    }

    /**
     * Test tables count
     *
     * this test will fail if some plugins tables are present
     */
    public function testTables(): void
    {
        $expected =  [
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
            'galette_payments_schedules',
            'galette_pdfmodels',
            'galette_preferences',
            'galette_searches',
            'galette_tmplinks',
            'galette_documents'
        ];

        $tables = $this->zdb->getTables();

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
     * Test table exists method
     */
    public function testTableExists(): void
    {
        $this->assertTrue($this->zdb->tableExists('preferences'));
        $this->assertFalse($this->zdb->tableExists('does_not_exists'));
        $warning = new \ArrayObject([
            'Level' => 'Error',
            'Code'  => 1146,
            'Message' => "regex:/.*does_not_exists.*/i"
        ]);
        $this->expected_mysql_warnings[] = $warning;
    }

    /**
     * Test UTF conversion, for MySQL only
     */
    public function testConvertToUtf(): void
    {
        $db = new \Galette\Core\Db();
        $db->convertToUTF();
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Upgrading from 0.6 will soon be discontinued.'
        );
    }

    /**
     * Test get platform
     */
    public function testGetPlatform(): void
    {
        $quoted = $this->zdb->platform->quoteValue('somethin\' to "quote"');

        $expected = ($this->zdb->isPostgres())
            ? "'somethin'' to \"quote\"'"
            : "'somethin\\' to \\\"quote\\\"'";

        $this->assertSame($expected, $quoted);
    }

    /**
     * Test execute Method
     */
    public function testExecute(): void
    {
        $select = $this->zdb->select('preferences', 'p');
        $select->where(['p.nom_pref' => 'azerty']);
        $results = $this->zdb->execute($select);

        $this->assertInstanceOf(\Laminas\Db\ResultSet\ResultSet::class, $results);
    }

    /**
     * Test execute Method
     */
    public function testExecuteWException(): void
    {
        $select = $this->zdb->select('preferences', 'p');
        $select->where(['p.nom_pref' => 'azerty']);
        $select->where(['p.notknown' => 'azerty']);

        $exception_thrown = false;
        try {
            $this->zdb->execute($select);
        } catch (\PDOException) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            $this->zdb->isPostgres() ? 'Undefined column' : 'Unknown column'
        );
        $warning = new \ArrayObject([
            'Level' => 'Error',
            'Code'  => 1054,
            'Message' => "regex:/Unknown column 'p\.notknown'.*/i"
        ]);
        $this->expected_mysql_warnings[] = $warning;
    }

    /**
     * Test serialization
     */
    public function testSerialization(): void
    {
        $db = $this->zdb;
        $serialized = serialize($db);
        $this->assertNotEmpty($serialized);

        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(\Galette\Core\Db::class, $unserialized);
    }

    /**
     * Test getSequenceName
     */
    public function testSequenceName(): void
    {
        $this->assertSame('adherents_id_adherent_seq', $this->zdb->getSequenceName('adherents', 'id_adherent'));
        $this->assertSame('galette_adherents_id_adherent_seq', $this->zdb->getSequenceName('adherents', 'id_adherent', true));
        $this->assertSame('adherents_id_adherent_seq', $this->zdb->getSequenceName('adherents', 'id_adherent', false));
    }

    /**
     * Test isset
     */
    public function testIsset(): void
    {
        $this->assertTrue(isset($this->zdb->sql));
        $this->assertTrue(isset($this->zdb->query_string));
        $this->assertTrue(isset($this->zdb->db)); // @phpstan-ignore isset.property,method.alreadyNarrowedType
        $this->assertFalse(isset($this->zdb->non_existing));
    }

    /**
     * Test supported engine
     */
    public function testSupportedEngine(): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['getInfos', 'isPostgres'])
            ->getMock();

        $zdb->method('isPostgres')->willReturn(false);
        $zdb->method('getInfos')->willReturnCallback(
            fn() => [
                'engine' => 'MariaDB Server',
                'version' => GALETTE_MARIADB_MIN
            ]
        );
        $this->assertTrue($zdb->isEngineSUpported());

        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['getInfos', 'isPostgres'])
            ->getMock();

        $zdb->method('isPostgres')->willReturn(false);
        $zdb->method('getInfos')->willReturnCallback(
            fn() => [
                'engine' => 'MariaDB Server',
                'version' => '10.4-MariaDB'
            ]
        );
        $this->assertFalse($zdb->isEngineSUpported());
        $this->assertSame(
            sprintf('Minimum version for MariaDB engine is %s, MariaDB 10.4 found!', GALETTE_MARIADB_MIN),
            $zdb->getUnsupportedMessage()
        );

        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['getInfos', 'isPostgres'])
            ->getMock();

        $zdb->method('isPostgres')->willReturn(true);
        $zdb->method('getInfos')->willReturnCallback(
            fn() => [
                'engine' => 'PostgreSQL',
                'version' => GALETTE_PGSQL_MIN
            ]
        );
        $this->assertTrue($zdb->isEngineSUpported());

        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['getInfos', 'isPostgres'])
            ->getMock();

        $zdb->method('isPostgres')->willReturn(true);
        $zdb->method('getInfos')->willReturnCallback(
            fn() => [
                'engine' => 'PostgreSQL',
                'version' => '12'
            ]
        );
        $this->assertFalse($zdb->isEngineSUpported());
        $this->assertFalse($zdb->isEngineSUpported());
        $this->assertSame(
            sprintf('Minimum version for PostgreSQL engine is %s, PostgreSQL 12 found!', GALETTE_PGSQL_MIN),
            $zdb->getUnsupportedMessage()
        );
    }

    /**
     * @return array<int,array{query: string, expected: bool}>
     */
    public static function implicitCommitProvider(): array
    {
        return [
            [
                'query' => 'ALTER TABLE galette_adherents ADD COLUMN test_column VARCHAR(255);',
                'expected' => true
            ],
            [
                'query' => 'CREATE INDEX idx_test ON galette_adherents (test_column);',
                'expected' => true
            ],
            [
                'query' => 'DROP TABLE galette_test;',
                'expected' => true
            ],
            [
                'query' => 'INSERT INTO galette_adherents (id_adherent, nom_adherent) VALUES (9999, \'Test\');',
                'expected' => false
            ],
            [
                'query' => 'UPDATE galette_adherents SET nom_adherent = \'Test2\' WHERE id_adherent = 9999;',
                'expected' => false
            ],
            [
                'query' => 'DELETE FROM galette_adherents WHERE id_adherent = 9999;',
                'expected' => false
            ],
            [
                'query' => 'SELECT * FROM galette_adherents;',
                'expected' => false
            ],
            [
                'query' => 'TRUNCATE TABLE galette_adherents;',
                'expected' => true
            ],
            [
                'query' => 'CREATE TABLE galette_test (id INT);',
                'expected' => true
            ],
            [
                'query' => 'DROP INDEX idx_test ON galette_adherents;',
                'expected' => true
            ],
            [
                'query' => 'ANALYZE TABLE galette_adherents;',
                'expected' => true
            ],
            [
                'query' => 'OPTIMIZE TABLE galette_adherents;',
                'expected' => true
            ],
            [
                'query' => 'RENAME TABLE galette_adherents TO galette_adherents_old;',
                'expected' => true
            ],
            [
                'query' => 'SET autocommit = 1;',
                'expected' => false // does cause implicit commit, but not handled: too complex and should really not happen.
            ],
            [
                'query' => 'SET sql_mode = \'STRICT_ALL_TABLES\';',
                'expected' => false
            ],
        ];
    }

    /**
     * Test willMysqlImplicitCommit method
     */
    #[DataProvider('implicitCommitProvider')]
    public function testWillMysqlImplicitCommit(string $query, bool $expected): void
    {
        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['isPostgres'])
            ->getMock();
        $zdb->method('isPostgres')->willReturn(true);
        $this->assertFalse($zdb->willMysqlImplicitCommit($query));

        $zdb = $this->getMockBuilder(\Galette\Core\Db::class)
            ->onlyMethods(['isPostgres'])
            ->getMock();
        $zdb->method('isPostgres')->willReturn(false);
        $this->assertSame($expected, $zdb->willMysqlImplicitCommit($query));
    }

    /**
     * Test isMissingTableException method
     */
    public function testIsMissingTableException(): void
    {
        $exception_thrown = false;
        try {
            $this->zdb->execute($this->zdb->select('non_existing_table'));
        } catch (\PDOException $e) {
            $exception_thrown = true;
            $this->assertTrue($this->zdb->isMissingTableException($e));
        }
        $this->assertTrue($exception_thrown);

        $warning = new \ArrayObject([
            'Level' => 'Error',
            'Code'  => 1146,
            'Message' => "regex:/.*non_existing_table.*/i"
        ]);
        $this->expected_mysql_warnings[] = $warning;
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            "non_existing_table"
        );

        $exception_thrown = false;
        try {
            $select = $this->zdb->select(\Galette\Core\Preferences::TABLE);
            $select->where(['does_not_exists' => 'does_not_exists']);
            $this->zdb->execute($select);
        } catch (\PDOException $e) {
            $exception_thrown = true;
            $this->assertFalse($this->zdb->isMissingTableException($e));
        }
        $this->assertTrue($exception_thrown);

        $warning = new \ArrayObject([
            'Level' => 'Error',
            'Code'  => 1054,
            'Message' => "regex:/.*does_not_exists.*/i"
        ]);
        $this->expected_mysql_warnings[] = $warning;
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            "does_not_exists"
        );
    }
}
