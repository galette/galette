<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Updates;

use Galette\Core\Install;
use Galette\Core\Preferences;
use Galette\Tests\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;

use function Safe\file_get_contents;

/**
 * 1.40 upgrade script tests: maps plugin positions are taken over
 *
 * Only maps steps are run: the script also creates roles tables, which exist
 * already.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MapsPositionsTakeOver extends GaletteTestCase
{
    //tables are dropped and created, which implicitly commits transactions on MySQL
    protected bool $db_transactions = false;

    private const string TABLE = 'maps_coordinates';

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->seed = 20261015;
        include_once GALETTE_BASE_PATH . '/install/scripts/upgrade-to-1.40.php';
        $this->deleteEnabledPreference();
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        if (isset($this->zdb)) {
            //restore the table as the installer creates it, even if test has failed
            $this->zdb->drop(self::TABLE . '_old', maymiss: true);
            $this->zdb->drop(self::TABLE, maymiss: true);
            $this->createCoreTable();
            $this->deleteEnabledPreference();
            $this->cleanMembers();
        }
        parent::tearDown();
    }

    /**
     * Create positions table as the 1.40 SQL script of current database engine does
     */
    private function createCoreTable(): void
    {
        $script = file_get_contents(
            GALETTE_BASE_PATH . '/install/scripts/sql/upgrade-to-1.40-' . $this->zdb->type_db . '.sql'
        );
        $start = strpos($script, '-- table for members geographic positions');
        $this->assertNotFalse($start);
        $end = strpos($script, ';', $start);
        $this->assertNotFalse($end);

        $install = new Install();
        $install->setTablesPrefix(PREFIX_DB);
        $install->reinitReport();
        $this->assertTrue($install->executeSql($this->zdb, substr($script, $start, $end - $start + 1)));
    }

    /**
     * Remove maps enabled preference row
     */
    private function deleteEnabledPreference(): void
    {
        $delete = $this->zdb->delete(Preferences::TABLE);
        $delete->where(['nom_pref' => 'pref_maps_enabled']);
        $this->zdb->execute($delete);
    }

    /**
     * Get maps enabled preference stored value, null when absent
     */
    private function getEnabledPreference(): ?string
    {
        $select = $this->zdb->select(Preferences::TABLE);
        $select->where(['nom_pref' => 'pref_maps_enabled']);
        $row = $this->zdb->execute($select)->current();
        return $row === null ? null : (string)$row['val_pref'];
    }

    /**
     * Run maps steps of the upgrade script, around the table creation
     */
    private function runUpgrade(): \Galette\Updates\UpgradeTo140
    {
        $updater = new \Galette\Updates\UpgradeTo140();
        (new \ReflectionProperty($updater, 'zdb'))->setValue($updater, $this->zdb);

        $this->assertTrue((new \ReflectionMethod($updater, 'preUpdate'))->invoke($updater));
        $this->createCoreTable();
        (new \ReflectionMethod($updater, 'takeOverMapsPositions'))->invoke($updater);

        $this->assertFalse($updater->hasErrors());
        return $updater;
    }

    /**
     * Test positions stored by the plugin are taken over, and maps kept enabled
     */
    public function testTakeOverPluginPositions(): void
    {
        $member = $this->getMemberOne();

        //an old plugin table: other column types, and no foreign key to keep orphans
        $this->zdb->drop(self::TABLE);
        $table = PREFIX_DB . self::TABLE;
        $this->zdb->db->query(
            $this->zdb->isPostgres()
                ? 'CREATE TABLE ' . $table . ' (id_adh integer NOT NULL, latitude decimal(10,8) NOT NULL,'
                    . ' longitude decimal(11,8) NOT NULL, PRIMARY KEY (id_adh))'
                : 'CREATE TABLE ' . $table . ' (id_adh int(10) unsigned NOT NULL, latitude varchar(20) NOT NULL,'
                    . ' longitude varchar(20) NOT NULL, PRIMARY KEY (id_adh))',
            Adapter::QUERY_MODE_EXECUTE
        );
        foreach ([$member->id => ['48.856614', '2.352222'], 999999 => ['45.764043', '4.835659']] as $id => $coords) {
            $insert = $this->zdb->insert(self::TABLE);
            $insert->values(['id_adh' => $id, 'latitude' => $coords[0], 'longitude' => $coords[1]]);
            $this->zdb->execute($insert);
        }

        $updater = $this->runUpgrade();

        $rows = $this->zdb->execute($this->zdb->select(self::TABLE))->toArray();
        $this->assertCount(1, $rows);
        $this->assertSame($member->id, (int)$rows[0]['id_adh']);
        $this->assertSame(48.856614, (float)$rows[0]['latitude']);
        $this->assertSame(2.352222, (float)$rows[0]['longitude']);

        $this->assertFalse($this->zdb->tableExists(self::TABLE . '_old'));
        $this->assertSame('1', $this->getEnabledPreference());
        $this->assertSame(
            'Members positions from the maps plugin have been taken over, the plugin can be removed.',
            $updater->getReport()[0]['message']
        );

        //table is the core one again: positions follow members removal
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where([\Galette\Entity\Adherent::PK => $member->id]);
        $this->zdb->execute($delete);
        $this->assertSame(0, $this->zdb->execute($this->zdb->select(self::TABLE))->count());
    }

    /**
     * Test positions kept aside by an interrupted upgrade are taken over when it is run again
     */
    public function testRetryAfterInterruptedUpgrade(): void
    {
        $member = $this->getMemberOne();

        //what an upgrade stopped after the table creation leaves: plugin rows
        //aside, and a new empty table
        $this->zdb->drop(self::TABLE . '_old', maymiss: true);
        $this->zdb->db->query(
            'CREATE TABLE ' . PREFIX_DB . self::TABLE . '_old (id_adh integer NOT NULL,'
                . ' latitude decimal(10,8) NOT NULL, longitude decimal(11,8) NOT NULL)',
            Adapter::QUERY_MODE_EXECUTE
        );
        $insert = $this->zdb->insert(self::TABLE . '_old');
        $insert->values(['id_adh' => $member->id, 'latitude' => '48.856614', 'longitude' => '2.352222']);
        $this->zdb->execute($insert);
        $this->assertSame(0, $this->zdb->execute($this->zdb->select(self::TABLE))->count());

        $this->runUpgrade();

        $rows = $this->zdb->execute($this->zdb->select(self::TABLE))->toArray();
        $this->assertCount(1, $rows);
        $this->assertSame($member->id, (int)$rows[0]['id_adh']);
        $this->assertSame(48.856614, (float)$rows[0]['latitude']);
        $this->assertFalse($this->zdb->tableExists(self::TABLE . '_old'));
        $this->assertSame('1', $this->getEnabledPreference());
    }

    /**
     * Test positions are taken over before roles are created, which may fail
     */
    public function testTakeOverBeforeRoles(): void
    {
        $member = $this->getMemberOne();
        $insert = $this->zdb->insert(self::TABLE);
        $insert->values(['id_adh' => $member->id, 'latitude' => '48.856614', 'longitude' => '2.352222']);
        $this->zdb->execute($insert);

        $updater = new \Galette\Updates\UpgradeTo140();
        (new \ReflectionProperty($updater, 'zdb'))->setValue($updater, $this->zdb);
        $this->assertTrue((new \ReflectionMethod($updater, 'preUpdate'))->invoke($updater));
        $this->createCoreTable();

        //roles cannot be stored: their table is missing
        $roles = PREFIX_DB . \Galette\Access\Roles::TABLE;
        $this->zdb->db->query(
            'ALTER TABLE ' . $roles . ' RENAME TO ' . $roles . '_moved',
            Adapter::QUERY_MODE_EXECUTE
        );
        try {
            (new \ReflectionMethod($updater, 'postUpdate'))->invoke($updater);
            $this->fail('Roles creation was expected to fail');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e);
        } finally {
            $this->zdb->db->query(
                'ALTER TABLE ' . $roles . '_moved RENAME TO ' . $roles,
                Adapter::QUERY_MODE_EXECUTE
            );
        }
        //logged by Db, then by Roles
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Query error: DELETE FROM');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Unable to initialize system roles');

        $rows = $this->zdb->execute($this->zdb->select(self::TABLE))->toArray();
        $this->assertCount(1, $rows);
        $this->assertSame($member->id, (int)$rows[0]['id_adh']);
        $this->assertFalse($this->zdb->tableExists(self::TABLE . '_old'));
    }

    /**
     * Test the table is created when the plugin was not installed, maps staying disabled
     */
    public function testWithoutPlugin(): void
    {
        $this->zdb->drop(self::TABLE);

        $updater = $this->runUpgrade();

        $this->assertTrue($this->zdb->tableExists(self::TABLE));
        $this->assertNull($this->getEnabledPreference());
        $this->assertSame([], $updater->getReport());
    }
}
