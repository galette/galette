<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Updates;

use Galette\Core\Preferences;
use Galette\Maps\Coordinates;
use Galette\Maps\Positions;
use Galette\Tests\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;

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
    //the plugin table is created and dropped, which implicitly commits transactions on MySQL
    protected bool $db_transactions = false;

    /** Members positions table of the maps plugin */
    private const string PLUGIN_TABLE = 'maps_coordinates';

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->seed = 20261015;
        include_once GALETTE_BASE_PATH . '/install/scripts/upgrade-to-1.40.php';
        $this->zdb->execute($this->zdb->delete(Positions::TABLE));
        $this->deleteEnabledPreference();
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        if (isset($this->zdb)) {
            //even if test has failed
            $this->zdb->drop(self::PLUGIN_TABLE, maymiss: true);
            $this->zdb->execute($this->zdb->delete(Positions::TABLE));
            $this->deleteEnabledPreference();
            $this->cleanMembers();
        }
        parent::tearDown();
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
     * Create a plugin table, as old plugin versions did: other column types, and no foreign key to keep orphans
     *
     * @param array<int, array{string, string}> $positions Latitude and longitude, by member id
     */
    private function createPluginTable(array $positions): void
    {
        $table = PREFIX_DB . self::PLUGIN_TABLE;
        $this->zdb->db->query(
            $this->zdb->isPostgres()
                ? 'CREATE TABLE ' . $table . ' (id_adh integer NOT NULL, latitude decimal(10,8) NOT NULL,'
                    . ' longitude decimal(11,8) NOT NULL, PRIMARY KEY (id_adh))'
                : 'CREATE TABLE ' . $table . ' (id_adh int(10) unsigned NOT NULL, latitude varchar(20) NOT NULL,'
                    . ' longitude varchar(20) NOT NULL, PRIMARY KEY (id_adh))',
            Adapter::QUERY_MODE_EXECUTE
        );
        foreach ($positions as $id => $coords) {
            $insert = $this->zdb->insert(self::PLUGIN_TABLE);
            $insert->values(['id_adh' => $id, 'latitude' => $coords[0], 'longitude' => $coords[1]]);
            $this->zdb->execute($insert);
        }
    }

    /**
     * Get stored positions
     *
     * @return array<int, array<string, mixed>>
     */
    private function getPositions(): array
    {
        return $this->zdb->execute($this->zdb->select(Positions::TABLE))->toArray();
    }

    /**
     * Run maps step of the upgrade script
     */
    private function runUpgrade(): \Galette\Updates\UpgradeTo140
    {
        $updater = new \Galette\Updates\UpgradeTo140();
        (new \ReflectionProperty($updater, 'zdb'))->setValue($updater, $this->zdb);
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
        $this->createPluginTable([$member->id => ['48.856614', '2.352222'], 999999 => ['45.764043', '4.835659']]);

        $updater = $this->runUpgrade();

        //positions of members that no longer exist are left behind
        $rows = $this->getPositions();
        $this->assertCount(1, $rows);
        $this->assertSame(Coordinates::TYPE, $rows[0][Positions::TYPE]);
        $this->assertSame($member->id, (int)$rows[0][Positions::ID]);
        $this->assertSame(48.856614, (float)$rows[0]['latitude']);
        $this->assertSame(2.352222, (float)$rows[0]['longitude']);

        $this->assertFalse($this->zdb->tableExists(self::PLUGIN_TABLE));
        $this->assertSame('1', $this->getEnabledPreference());
        $this->assertSame(
            'Members positions from the maps plugin have been taken over, the plugin can be removed.',
            $updater->getReport()[0]['message']
        );
    }

    /**
     * Test positions are taken over again when an interrupted upgrade is run again
     */
    public function testRetryAfterInterruptedUpgrade(): void
    {
        $member = $this->getMemberOne();
        $this->createPluginTable([$member->id => ['48.856614', '2.352222']]);
        //what an upgrade stopped before removing the plugin table leaves
        (new Positions($this->zdb))->set(
            type: Coordinates::TYPE,
            id: $member->id,
            latitude: 48.856614,
            longitude: 2.352222
        );
        //positions of other objects are not concerned
        (new Positions($this->zdb))->set(
            type: 'plugin_object',
            id: $member->id,
            latitude: 1.0,
            longitude: 2.0
        );

        $this->runUpgrade();

        $rows = $this->getPositions();
        $this->assertCount(2, $rows);
        $this->assertSame(['48.856614', '2.352222'], array_values((new Positions($this->zdb))->get(Coordinates::TYPE, $member->id)));
        $this->assertNotNull((new Positions($this->zdb))->get('plugin_object', $member->id));
        $this->assertFalse($this->zdb->tableExists(self::PLUGIN_TABLE));
        $this->assertSame('1', $this->getEnabledPreference());
    }

    /**
     * Test positions are taken over before roles are created, which may fail
     */
    public function testTakeOverBeforeRoles(): void
    {
        $member = $this->getMemberOne();
        $this->createPluginTable([$member->id => ['48.856614', '2.352222']]);

        $updater = new \Galette\Updates\UpgradeTo140();
        (new \ReflectionProperty($updater, 'zdb'))->setValue($updater, $this->zdb);

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

        $this->assertNotNull((new Positions($this->zdb))->get(Coordinates::TYPE, $member->id));
        $this->assertFalse($this->zdb->tableExists(self::PLUGIN_TABLE));
    }

    /**
     * Test nothing happens when the plugin was not installed, maps staying disabled
     */
    public function testWithoutPlugin(): void
    {
        $updater = $this->runUpgrade();

        $this->assertSame([], $this->getPositions());
        $this->assertNull($this->getEnabledPreference());
        $this->assertSame([], $updater->getReport());
    }
}
