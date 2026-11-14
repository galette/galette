<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Maps;

use Galette\Core\Db;

/**
 * Geographic positions of any kind of object
 *
 * A position belongs to an object, given by its type and its identifier:
 * members positions are stored with the "member" type, plugins store their
 * own objects positions with types of their own, such as "events_event".
 * Nothing removes a position when its object is removed: its owner does.
 *
 * No access is checked here, callers do.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Positions
{
    public const string TABLE = 'positions';
    public const string TYPE = 'object_type';
    public const string ID = 'object_id';

    /**
     * Constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(private readonly Db $zdb)
    {
    }

    /**
     * Get an object position
     *
     * @param string $type Object type
     * @param int    $id   Object identifier
     *
     * @return ?array{latitude: string, longitude: string} null when object has no position
     */
    public function get(string $type, int $id): ?array
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns(['latitude', 'longitude'])->where([self::TYPE => $type, self::ID => $id]);
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
     * Get identifiers of the objects of a type that have a position
     *
     * @param string $type Object type
     *
     * @return array<int, true> Objects identifiers as keys
     */
    public function getIds(string $type): array
    {
        $select = $this->zdb->select(self::TABLE);
        $select->columns([self::ID])->where([self::TYPE => $type]);
        $ids = [];
        foreach ($this->zdb->execute($select) as $row) {
            $ids[(int)$row[self::ID]] = true;
        }
        return $ids;
    }

    /**
     * Set an object position
     *
     * @param string $type      Object type
     * @param int    $id        Object identifier
     * @param float  $latitude  Latitude
     * @param float  $longitude Longitude
     */
    public function set(string $type, int $id, float $latitude, float $longitude): void
    {
        $values = [
            'latitude'  => $latitude,
            'longitude' => $longitude
        ];

        if ($this->get($type, $id) === null) {
            $insert = $this->zdb->insert(self::TABLE);
            $insert->values([self::TYPE => $type, self::ID => $id] + $values);
            $this->zdb->execute($insert);
        } else {
            //no row is affected when the position does not change: not an error
            $update = $this->zdb->update(self::TABLE);
            $update->set($values)->where([self::TYPE => $type, self::ID => $id]);
            $this->zdb->execute($update);
        }
    }

    /**
     * Remove positions of objects of a type; removing nothing is not an error
     *
     * @param string    $type Object type
     * @param int[]|int $ids  Objects identifiers
     */
    public function remove(string $type, array|int $ids): void
    {
        $ids = (array)$ids;
        if ($ids === []) {
            return;
        }
        $delete = $this->zdb->delete(self::TABLE);
        $delete->where([self::TYPE => $type]);
        $delete->where->in(self::ID, $ids);
        $this->zdb->execute($delete);
    }
}
