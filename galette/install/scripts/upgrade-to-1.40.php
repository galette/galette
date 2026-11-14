<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updates;

use Galette\Access\Permissions;
use Galette\Access\Roles;
use Galette\Core\L10n;
use Galette\Core\Preferences;
use Galette\Maps\Coordinates;
use Galette\Maps\Positions;
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

    protected ?string $db_version = '1.40';

    /**
     * Main constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setSqlScripts($this->db_version);
    }

    /**
     * Update instructions
     */
    protected function update(): bool
    {
        $this->rehashL10nKeys();
        return true;
    }

    /**
     * Labels translations are stored under a hash of their text: md5 gave way
     * to a non cryptographic one, as fast and not reported as a weak hash
     */
    private function rehashL10nKeys(): void
    {
        $select = $this->zdb->select(L10n::TABLE);
        $select->columns(['text_orig_sum', 'text_locale', 'text_orig']);
        //read at once: rows are changed while going through them
        $rows = $this->zdb->execute($select)->toArray();

        foreach ($rows as $row) {
            $sum = L10n::sum($row['text_orig']);
            if ($sum === $row['text_orig_sum']) {
                continue;
            }
            $update = $this->zdb->update(L10n::TABLE);
            $update->set(['text_orig_sum' => $sum])->where([
                'text_orig_sum' => $row['text_orig_sum'],
                'text_locale'   => $row['text_locale']
            ]);
            $this->zdb->execute($update);
        }
    }

    /**
     * Post stuff, if any.
     * Will be executed at the end.
     */
    protected function postUpdate(): bool
    {
        $this->takeOverMapsPositions();

        //system roles get what rights settings allowed; settings are then removed
        $rights = Roles::LEGACY_RIGHTS;
        $select = $this->zdb->select(Preferences::TABLE);
        $select->where(['nom_pref' => array_keys($rights)]);
        foreach ($this->zdb->execute($select) as $row) {
            $rights[$row->nom_pref] = (bool)$row->val_pref;
        }

        $roles = new Roles($this->zdb, new Permissions());
        $roles->installInit($rights);

        $delete = $this->zdb->delete(Preferences::TABLE);
        $delete->where(['nom_pref' => array_keys($rights)]);
        $this->zdb->execute($delete);

        $this->addReportEntry(_T('System roles have been created.'), self::REPORT_SUCCESS);

        return true;
    }

    /**
     * Take over positions the maps plugin stored
     *
     * The plugin table is removed only once its rows are taken over: an
     * interrupted upgrade takes them over again.
     */
    private function takeOverMapsPositions(): void
    {
        if (!$this->zdb->tableExists(self::MAPS_TABLE)) {
            return;
        }

        $delete = $this->zdb->delete(Positions::TABLE);
        $delete->where([Positions::TYPE => Coordinates::TYPE]);
        $this->zdb->execute($delete);

        //positions of members that no longer exist are not taken over
        $this->zdb->db->query(
            sprintf(
                'INSERT INTO %1$s (%2$s, %3$s, latitude, longitude)'
                . ' SELECT \'%4$s\', o.id_adh, o.latitude, o.longitude FROM %5$s o'
                . ' INNER JOIN %6$s a ON a.id_adh = o.id_adh',
                PREFIX_DB . Positions::TABLE,
                Positions::TYPE,
                Positions::ID,
                Coordinates::TYPE,
                PREFIX_DB . self::MAPS_TABLE,
                PREFIX_DB . 'adherents'
            ),
            Adapter::QUERY_MODE_EXECUTE
        );
        $this->zdb->drop(self::MAPS_TABLE);

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
    }
}
