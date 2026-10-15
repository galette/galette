<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updates;

use Galette\Core\Preferences;
use Galette\Updater\AbstractUpdater;
use Laminas\Db\Adapter\Adapter;

/**
 * Galette 1.4.0 upgrade script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo140 extends AbstractUpdater
{
    /** Members positions, created by the maps plugin before it became part of Galette */
    private const string MAPS_TABLE = 'maps_coordinates';
    private const string MAPS_BACKUP = 'maps_coordinates_old';

    protected ?string $db_version = '1.40';

    /** Whether positions stored by the maps plugin have to be taken over */
    private bool $maps_existing = false;

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSqlScripts($this->db_version);
    }

    /**
     * Pre stuff, if any.
     * Will be executed first.
     */
    protected function preUpdate(): bool
    {
        if (!$this->zdb->tableExists(self::MAPS_TABLE)) {
            return true;
        }

        //Plugin versions left the table with various foreign keys names and
        //column types: keep its rows aside, the SQL script creates it anew.
        //A plain copy has no constraint, whose names would collide on PostgreSQL.
        $this->maps_existing = true;
        $this->zdb->drop(self::MAPS_BACKUP, maymiss: true);
        $this->zdb->db->query(
            sprintf(
                'CREATE TABLE %1$s AS SELECT id_adh, latitude, longitude FROM %2$s',
                PREFIX_DB . self::MAPS_BACKUP,
                PREFIX_DB . self::MAPS_TABLE
            ),
            Adapter::QUERY_MODE_EXECUTE
        );
        $this->zdb->drop(self::MAPS_TABLE);

        return true;
    }

    /**
     * Update instructions
     */
    protected function update(): bool
    {
        // Nothing to do here, all is done in postUpdate
        return true;
    }

    /**
     * Post stuff, if any.
     * Will be executed at the end.
     */
    protected function postUpdate(): bool
    {
        if (!$this->maps_existing) {
            return true;
        }

        //positions of members that no longer exist are not taken over
        $this->zdb->db->query(
            sprintf(
                'INSERT INTO %1$s (id_adh, latitude, longitude)'
                . ' SELECT o.id_adh, o.latitude, o.longitude FROM %2$s o'
                . ' INNER JOIN %3$s a ON a.id_adh = o.id_adh',
                PREFIX_DB . self::MAPS_TABLE,
                PREFIX_DB . self::MAPS_BACKUP,
                PREFIX_DB . 'adherents'
            ),
            Adapter::QUERY_MODE_EXECUTE
        );
        $this->zdb->drop(self::MAPS_BACKUP);

        //maps were in use: keep them displayed
        $delete = $this->zdb->delete(Preferences::TABLE);
        $delete->where(['nom_pref' => 'pref_maps_enabled']);
        $this->zdb->execute($delete);
        $insert = $this->zdb->insert(Preferences::TABLE);
        $insert->values([
            'nom_pref' => 'pref_maps_enabled',
            'val_pref' => '1'
        ]);
        $this->zdb->execute($insert);

        $this->addReportEntry(
            _T('Members positions from the maps plugin have been taken over, the plugin can be removed.'),
            self::REPORT_SUCCESS
        );

        return true;
    }
}
