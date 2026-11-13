<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Maps;

use Galette\Access\MembersScope;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Features\AccessControlled;
use Laminas\Db\Sql\Where;

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
    use AccessControlled;

    public const string TABLE = 'maps_coordinates';
    public const string PK = 'id_adh';

    /**
     * Constructor
     *
     * @param Db    $zdb   Database instance
     * @param Login $login Logged-in user, whose permissions filter the list
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
     * Get ids of members who have coordinates
     *
     * @return array<int, true> Members ids as keys
     */
    public function getLocatedIds(): array
    {
        $select = $this->zdb->select($this->getTableName());
        $select->columns([self::PK]);
        $ids = [];
        foreach ($this->zdb->execute($select) as $row) {
            $ids[(int)$row[self::PK]] = true;
        }
        return $ids;
    }

    /**
     * Get coordinates of the members logged-in user can see on the map
     *
     * Members granted member:read on everyone see every active member. Others
     * see active, up-to-date members who display their information, and those
     * they can read: themselves, their children, members of the groups
     * member:read is granted on. Positions of members they cannot read are
     * snapped to the grid.
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
            //only what the map displays, and what access is checked on
            ['nom_adh', 'prenom_adh', 'pseudo_adh', 'societe_adh', 'parent_id']
        );
        $where = $select->where;
        $where->equalTo('a.activite_adh', right: true);

        $scope = $this->login->isLogged() ? $this->getGroupScope('member:read', $this->login) : [];
        $readable = $scope === null ? [] : $this->restrictToVisible($where, $scope);

        $res = [];
        foreach ($this->zdb->execute($select) as $r) {
            $id_adh = (int)$r[self::PK];
            $exact = $scope === null
                || isset($readable[$id_adh])
                || $this->isOwnOrChild($id_adh, (int)$r['parent_id']);
            $approximate = $step !== null && !$exact;
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
     * Restrict list to public up-to-date profiles, and to the ones logged-in user can read
     *
     * @param Where      $where  Where clause of the list query
     * @param array<int> $groups Groups member:read is granted on
     *
     * @return array<int, true> Members of these groups, by identifier
     */
    private function restrictToVisible(Where $where, array $groups): array
    {
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
            $visible->or->equalTo('a.parent_id', $this->login->id);
        }

        $readable = [];
        if (count($groups) > 0) {
            $groups_members = (new MembersScope($this->zdb, $this->login, $groups))->getGroupsMembersSelect();
            $visible->or->in('a.' . Adherent::PK, $groups_members);
            foreach ($this->zdb->execute($groups_members) as $row) {
                $readable[(int)$row[Adherent::PK]] = true;
            }
        }
        $visible->unnest();

        return $readable;
    }

    /**
     * Is member the logged-in one, or one of their children?
     *
     * @param int $id_adh    Member ID
     * @param int $parent_id Member parent ID, 0 if none
     */
    private function isOwnOrChild(int $id_adh, int $parent_id): bool
    {
        return $this->login->isLogged()
            && ($id_adh === (int)$this->login->id || $parent_id === (int)$this->login->id);
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
