<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
    public const string TABLE = 'titles';
    public const string PK = 'id_title';

    public const int MR = 1;
    public const int MRS = 2;
    public const int MISS = 3;

    /** @var array<array<string,mixed>> */
    private static array $defaults = [
        [
            'id_title'      => 1,
            'short_label'   => 'Mr.',
            'long_label'    => null
        ],
        [
            'id_title'      => 2,
            'short_label'   => 'Mrs.',
            'long_label'    => null
        ]
    ];

    /**
     * Default constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(private readonly Db $zdb)
    {
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

        $pols = [];
        foreach ($results as $r) {
            $pk = self::PK;
            $pols[$r->$pk] = new Title($r);
        }
        return $pols;
    }


    /**
     * Set default titles at install time
     *
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
                [
                    'id_title'      => ':id',
                    'short_label'   => ':short',
                    'long_label'    => ':long'
                ]
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
                    [
                        'id'    => $d['id_title'],
                        'short' => $short,
                        'long'  => $long
                    ]
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
