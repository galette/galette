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
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo140 extends GaletteTestCase
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
        require_once GALETTE_BASE_PATH . '/install/scripts/upgrade-to-1.40.php';
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
            $this->assertTrue(
                $this->getInstall()->executeSql(
                    $this->zdb,
                    file_get_contents($this->getSqlScript())
                )
            );
            $this->deleteEnabledPreference();
            $this->cleanMembers();
        }
        parent::tearDown();
    }

    /**
     * Get the 1.40 SQL script of current database engine
     */
    private function getSqlScript(): string
    {
        return GALETTE_BASE_PATH . '/install/scripts/sql/upgrade-to-1.40-' . $this->zdb->type_db . '.sql';
    }

    /**
     * Get an installer to run SQL scripts
     */
    private function getInstall(): Install
    {
        $install = new Install();
        $install->setTablesPrefix(PREFIX_DB);
        $install->reinitReport();
        return $install;
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
     * Run the upgrade script
     */
    private function runUpgrade(): \Galette\Updates\UpgradeTo140
    {
        $updater = new \Galette\Updates\UpgradeTo140();
        $updater->run($this->zdb, $this->getInstall());
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
