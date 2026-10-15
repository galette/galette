<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Maps;

use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;

/**
 * Members GPS coordinates
 *
 * Errors are not caught here: callers know what the user was trying to do,
 * and log it along with the database message.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Coordinates
{
    public const string TABLE = 'maps_coordinates';
    public const string PK = 'id_adh';

    /**
     * Constructor
     *
     * @param Db    $zdb   Database instance
     * @param Login $login Logged-in user, whose rights filter the list
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Login $login
    ) {
    }

    /**
     * Get member coordinates
     *
     * @param int $id Member id
     *
     * @return ?array{latitude: string, longitude: string} null when member has no coordinates
     */
    public function get(int $id): ?array
    {
        $select = $this->zdb->select($this->getTableName());
        $select->columns(['latitude', 'longitude'])->where([self::PK => $id]);
        $row = $this->zdb->execute($select)->current();

        if ($row === null) {
            return null;
        }

        return [
            'latitude'  => (string)$row['latitude'],
            'longitude' => (string)$row['longitude']
        ];
    }

    /**
     * Get coordinates of the members logged-in user can see on the map
     *
     * Staff and administrators see every active member; others see active,
     * up-to-date members who display their information, and their own position.
     * Positions are snapped to the grid for them, except their own one.
     *
     * @param ?float $step Grid step positions are snapped to, in degrees; null for exact positions
     *
     * @return array<int, array{id_adh: int, lat: string, lng: string, approximate: bool, name: string, nickname: ?string, company?: string}>
     */
    public function listVisible(?float $step = null): array
    {
        $select = $this->zdb->select($this->getTableName(), 'c');
        $select->join(
            [
                'a' => PREFIX_DB . Adherent::TABLE
            ],
            'a.' . self::PK . '=' . 'c.' . self::PK,
            //only what the map displays
            ['nom_adh', 'prenom_adh', 'pseudo_adh', 'societe_adh']
        );
        $where = $select->where;
        $where->equalTo('a.activite_adh', right: true);

        $privileged = $this->login->isAdmin()
            || $this->login->isStaff()
            || $this->login->isSuperAdmin();

        if (!$privileged) {
            //limit query to public up-to-date profiles, and to logged-in member own one
            $visible = $where->nest();
            $public = $visible->nest();
            $public->nest()
                ->greaterThanOrEqualTo('a.date_echeance', date('Y-m-d'))
                ->or->equalTo('a.bool_exempt_adh', right: true)
                ->unnest();
            $public->and->equalTo('a.bool_display_info', right: true);
            $public->unnest();
            if ($this->login->isLogged()) {
                $visible->or->equalTo('a.' . Adherent::PK, $this->login->id);
            }
            $visible->unnest();
        }

        $res = [];
        foreach ($this->zdb->execute($select) as $r) {
            $id_adh = (int)$r[self::PK];
            $approximate = $step !== null && !$privileged && $id_adh !== (int)$this->login->id;
            $m = [
                'id_adh'    => $id_adh,
                'lat'       => $approximate ? Precision::snap($r['latitude'], $step) : (string)$r['latitude'],
                'lng'       => $approximate ? Precision::snap($r['longitude'], $step) : (string)$r['longitude'],
                'approximate' => $approximate,
                'name'      => Adherent::getNameWithCase($r['nom_adh'], $r['prenom_adh']),
                'nickname'  => $r['pseudo_adh']
            ];
            if (trim($r['societe_adh'] ?? '') !== '') {
                $m['company'] = $r['societe_adh'];
            }
            $res[] = $m;
        }

        return $res;
    }

    /**
     * Set member coordinates
     *
     * @param int   $id        Member id
     * @param float $latitude  Latitude
     * @param float $longitude Longitude
     */
    public function set(int $id, float $latitude, float $longitude): void
    {
        $values = [
            'latitude'  => $latitude,
            'longitude' => $longitude
        ];

        if ($this->get($id) === null) {
            $insert = $this->zdb->insert($this->getTableName());
            $insert->values([self::PK => $id] + $values);
            $this->zdb->execute($insert);
        } else {
            //no row is affected when the position does not change: not an error
            $update = $this->zdb->update($this->getTableName());
            $update->set($values)->where([self::PK => $id]);
            $this->zdb->execute($update);
        }
    }

    /**
     * Remove member coordinates; removing nothing is not an error
     *
     * @param int $id Member id
     */
    public function remove(int $id): void
    {
        $delete = $this->zdb->delete($this->getTableName());
        $delete->where([self::PK => $id]);
        $this->zdb->execute($delete);
    }

    /**
     * Get table's name
     */
    protected function getTableName(): string
    {
        return self::TABLE;
    }
}
