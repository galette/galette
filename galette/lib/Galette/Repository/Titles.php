<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

class Titles extends Repository
{
    use RepositoryTrait;

    public const TABLE = 'titles';
    public const PK = 'id_title';

    public const MR = 1;
    public const MRS = 2;
    public const MISS = 3;

    /**
     * Get defaults values
     *
     * @return array<string, mixed>
     */
    protected function loadDefaults(): array
    {
        return [
            array(
                self::PK => 1,
                'short_label' => 'Mr.',
                'long_label' => null
            ),
            array(
                self::PK => 2,
                'short_label' => 'Mrs.',
                'long_label' => null
            )
        ];
    }


    /**
     * Set default titles at install time
     *
     * @return boolean
     * @throws Throwable
     */
    public function XXXXXXXXXXXXXXXXXinstallInit(): bool
    {
        try {
            //first, we drop all values
            $delete = $this->zdb->delete(self::TABLE);
            $this->zdb->execute($delete);

            $insert = $this->zdb->insert(self::TABLE);
            $insert->values(
                array(
                    'id_title' => ':id',
                    'short_label' => ':short',
                    'long_label' => ':long'
                )
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            $this->zdb->handleSequence(
                self::TABLE,
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
                        'id' => $d['id_title'],
                        'short' => $short,
                        'long' => $long
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
