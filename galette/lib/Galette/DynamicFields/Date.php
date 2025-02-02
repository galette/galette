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

namespace Galette\DynamicFields;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\DynamicFieldsHandle;

/**
 * Date field type
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Date extends DynamicField
{
    /**
     * Default constructor
     *
     * @param Db   $zdb Database instance
     * @param ?int $id  Optional field id to load data
     */
    public function __construct(Db $zdb, ?int $id = null)
    {
        parent::__construct($zdb, $id);
        $this->has_data = true;
    }

    /**
     * Get field type
     *
     * @return integer
     */
    public function getType(): int
    {
        return self::DATE;
    }

    /**
     * Prior to Galette 1.2.0, dynamic date fields were stored in their localized format
     * This method *tries* to fix that.
     *
     * @param Db $zdb Database instance
     *
     * @return bool
     */
    public static function resetLocalizedFormats(Db $zdb): bool
    {
        $possible_formats = [
            'd/m/Y',
            'd-m-Y',
            'd.m.Y',
            'Y/m/d',
            //'m/d/Y'
        ];

        //get all date dynamic fields
        $select = $zdb->select(DynamicFieldsHandle::TABLE, 'fields');
        $select->join(
            array('def' => PREFIX_DB . DynamicField::TABLE),
            'fields.' . DynamicField::PK . '=def.' . DynamicField::PK,
            []
        );
        $select->where(['def.field_type' => self::DATE]);
        $results = $zdb->execute($select);
        $debug_dates = [];
        $updates = [];
        foreach ($results as $result) {
            $date = $result->field_val;
            $debug_dates[$result->{DynamicFieldsHandle::PK}] = [
                'id' => $result->{DynamicFieldsHandle::PK},
                'original' => $date
            ];
            $d = \DateTime::createFromFormat('Y-m-d', $date);
            if ($d !== false) {
                $derrors = \DateTime::getLastErrors();
                if (!empty($derrors['warning_count'])) {
                    continue;
                }
                continue;
            }

            foreach ($possible_formats as $format) {
                $d = \DateTime::createFromFormat($format, $date);
                if ($d !== false) {
                    $derrors = \DateTime::getLastErrors();
                    if (empty($derrors['warning_count'])) {
                        $updates[$result->{DynamicFieldsHandle::PK}] = $d->format('Y-m-d');
                        $debug_dates[$result->{DynamicFieldsHandle::PK}]['replacement'] = $d->format('Y-m-d');
                        break;
                    }
                }
            }
        }

        if (count($updates)) {
            $zdb->connection->beginTransaction();
            $update = $zdb->update(DynamicFieldsHandle::TABLE);
            $update->set(
                array(
                    'field_val'  => ':field_val'
                )
            )->where->equalTo(DynamicFieldsHandle::PK, ':' . DynamicFieldsHandle::PK);
            $stmt = $zdb->sql->prepareStatementForSqlObject($update);
            foreach ($updates as $k => $v) {
                $stmt->execute(
                    array(
                        'field_val'  => $v,
                        DynamicFieldsHandle::PK => $k
                    )
                );
            }
            throw new \RuntimeException(
                print_r($debug_dates, true)
            );
            //$zdb->connection->commit();
            Analog::log(
                sprintf(
                    'Dynamic dates updated, %1$s row(s) affected on %2$s found.',
                    count($updates),
                    $results->count()
                ),
                Analog::ERROR
            );
        }
        return false;
    }
}
