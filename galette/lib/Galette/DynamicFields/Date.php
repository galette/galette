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
use DateTime;
use Galette\Core\Db;
use Galette\Entity\DynamicFieldsHandle;
use Throwable;

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
            'Y.m.d',
            'm/d/Y',
            'm.d.Y'
        ];

        //get all date dynamic fields
        $select = $zdb->select(DynamicFieldsHandle::TABLE, 'fields');
        $select->join(
            ['def' => PREFIX_DB . DynamicField::TABLE],
            'fields.' . DynamicField::PK . '=def.' . DynamicField::PK,
            []
        );
        $select->where(['def.field_type' => self::DATE]);
        $results = $zdb->execute($select);
        $debug_dates = [];
        $updates = [];
        foreach ($results as $result) {
            $date = $result->field_val;
            $unique_key = sprintf(
                '%1$s-%2$s-%3$s-%4$s',
                $result->{DynamicFieldsHandle::PK},
                $result->{DynamicField::PK},
                $result->field_form,
                $result->val_index
            );
            $debug_dates[$unique_key] = [
                'id' => $unique_key,
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
                        $updates[$unique_key] = [
                            'item' => $result,
                            'new_date' => $d->format('Y-m-d')
                        ];
                        $debug_dates[$unique_key]['replacement'] = $d->format('Y-m-d');
                        break;
                    }
                }
            }
        }

        if (!count($updates)) {
            //no dates found
            return true;
        }

        $zdb->connection->beginTransaction();
        $update = $zdb->update(DynamicFieldsHandle::TABLE);
        $update->set(
            [
                'field_val'  => ':field_val'
            ]
        )->where
            ->equalTo(DynamicFieldsHandle::PK, ':' . DynamicFieldsHandle::PK)
            ->equalTo(DynamicField::PK, ':' . DynamicField::PK)
            ->equalTo('field_form', ':field_form')
            ->equalTo('val_index', ':val_index');
        $stmt = $zdb->sql->prepareStatementForSqlObject($update);
        foreach ($updates as $update) {
            $stmt->execute(
                [
                    'field_val'  => $update['new_date'],
                    DynamicFieldsHandle::PK => $update['item']->{DynamicFieldsHandle::PK},
                    DynamicField::PK => $update['item']->{DynamicField::PK},
                    'field_form' => $update['item']->field_form,
                    'val_index' => $update['item']->val_index
                ]
            );
        }
        $zdb->connection->commit();
        Analog::log(
            sprintf(
                "Dynamic dates updated, %1\$s row(s) affected on %2\$s found.\n%3\$s",
                count($updates),
                $results->count(),
                print_r($debug_dates, true)
            ),
            Analog::INFO
        );

        return true;
    }

    /**
     * Get value to display for a field
     *
     * @param mixed $value Raw value to get displayed
     *
     * @return string
     */
    public function getDisplayValue(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        try {
            $date = new DateTime($value);
            return $date->format(__('Y-m-d'));
        } catch (Throwable $e) {
            //oops, we've got a bad date :/
            Analog::log(
                'Bad date (' . $value . ') | '
                . $e->getMessage(),
                Analog::INFO
            );
            return $value;
        }
    }
}
