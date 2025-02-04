<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Repository;

use Galette\Core\Db;
use Throwable;
use Galette\Entity\Title;
use Analog\Analog;

/**
 * Titles repository management
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Titles
{
    public const TABLE = 'titles';
    public const PK = 'id_title';

    public const MR = 1;
    public const MRS = 2;
    public const MISS = 3;

    /** @var array<array<string,mixed>> */
    private static array $defaults = array(
        array(
            'id_title'      => 1,
            'short_label'   => 'Mr.',
            'long_label'    => null
        ),
        array(
            'id_title'      => 2,
            'short_label'   => 'Mrs.',
            'long_label'    => null
        )
    );

    private Db $zdb;

    /**
     * Default constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(Db $zdb)
    {
        $this->zdb = $zdb;
    }

    /**
     * Get the list of all titles
     *
     * @return Title[]
     */
    public function getList(): array
    {
        $select = $this->zdb->select(self::TABLE);
        $select->order(self::PK);

        $results = $this->zdb->execute($select);

        $pols = array();
        foreach ($results as $r) {
            $pk = self::PK;
            $pols[$r->$pk] = new Title($r);
        }
        return $pols;
    }


    /**
     * Set default titles at install time
     *
     * @return boolean
     * @throws Throwable
     */
    public function installInit(): bool
    {
        try {
            //first, we drop all values
            $delete = $this->zdb->delete(self::TABLE);
            $this->zdb->execute($delete);

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values(
                array(
                    'id_title'      => ':id',
                    'short_label'   => ':short',
                    'long_label'    => ':long'
                )
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            $this->zdb->handleSequence(
                self::TABLE,
                self::PK,
                count(self::$defaults)
            );

            foreach (self::$defaults as $d) {
                $short = _T($d['short_label']);
                $long = null;
                if ($d['long_label'] !== null) {
                    $long = _T($d['long_label']);
                }
                $stmt->execute(
                    array(
                        'id'    => $d['id_title'],
                        'short' => $short,
                        'long'  => $long
                    )
                );
            }

            Analog::log(
                'Default titles were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize default titles. ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }
}
