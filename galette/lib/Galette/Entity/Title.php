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

namespace Galette\Entity;

use Galette\Core\Db;
use Galette\Entity\Base\EntityFromDb;
use Galette\Entity\Base\Translate;
use ArrayObject;

class Title extends EntityFromDb
{
    public const TABLE = 'titles';
    public const PK = 'id_title';

    public const MR = 1;
    public const MRS = 2;
    public const MISS = 3;

    public function __construct(Db $zdb, ArrayObject|int $args = null)
    {
        parent::__construct(
            $zdb,
            [
                'table' => self::TABLE,
                'id' => self::PK,
                'short' => 'short_label',
                'long' => 'long_label',
            ],
            [
                //toString() will return long property
                'toString' => 'long',

                //if long value is null get short value
                'long:override' => function ($value) {
                    return ($value == null || trim($value) === '') ? $this->short : $value; },
                'long:warningnoempty' => true,

                //Virtual properties
                'tshort:from' => 'short',  //OR function() { return $this->short; }, 
                'tlong:from' => 'long',

                'tshort:override' => function ($value) {
                    return Translate::getFromLang($value); },
                'tlong:override' => function ($value) {
                    return Translate::getFromLang($value); },
            ],
            $args
        );
    }

    /**
     * Remove current title
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function remove(): bool
    {
        $id = (int) $this->id;
        if ($id === self::MR || $id === self::MRS) {
            throw new \RuntimeException(_T("You cannot delete Mr. or Mrs. titles!"));
        }
        return parent::remove();
    }
}



