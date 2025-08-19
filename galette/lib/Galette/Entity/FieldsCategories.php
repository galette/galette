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

namespace Galette\Entity;

use ArrayObject;
use Throwable;
use Analog\Analog;
use Galette\Core\Db;

/**
 * Fields categories class for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class FieldsCategories
{
    public const TABLE = 'fields_categories';
    public const PK = 'id_field_category';

    /** @var array<string,mixed> */
    private array $defaults;

    private Db $zdb;

    public const ADH_CATEGORY_IDENTITY = 1;
    public const ADH_CATEGORY_GALETTE = 2;
    public const ADH_CATEGORY_CONTACT = 3;

    /**
     * Default constructor
     *
     * @param Db                  $zdb      Database
     * @param array<string,mixed> $defaults default values
     */
    public function __construct(Db $zdb, array $defaults)
    {
        $this->zdb = $zdb;
        $this->defaults = $defaults;
    }

    /**
     * Get list of categories
     *
     * @param Db $zdb Database
     *
     * @return array<ArrayObject<string, int|string>>
     */
    public static function getList(Db $zdb): array
    {
        try {
            $select = $zdb->select(self::TABLE);
            $select->order('position');

            $categories = [];
            $results = $zdb->execute($select);
            foreach ($results as $result) {
                $categories[] = $result;
            }
            return $categories;
        } catch (Throwable $e) {
            Analog::log(
                '[' . static::class . '] Cannot get fields categories list | '
                . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Store the categories
     *
     * @param Db                $zdb        Database
     * @param array<int,string> $categories Categories
     *
     * @return boolean
     */
    public static function setCategories(Db $zdb, array $categories): bool
    {
        try {
            $zdb->connection->beginTransaction();

            $update = $zdb->update(self::TABLE);
            $update->set(
                [
                    'position' => ':position'
                ]
            )->where(
                [
                    self::PK => ':pk'
                ]
            );
            $stmt = $zdb->sql->prepareStatementForSqlObject($update);

            foreach ($categories as $k => $v) {
                $params = [
                    'position'  => $k,
                    'pk'        => $v
                ];
                $stmt->execute($params);
            }
            $zdb->connection->commit();
            return true;
        } catch (Throwable $e) {
            $zdb->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Set default fields categories at install time
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
                [
                    self::PK        => ':' . self::PK,
                    'table_name'    => ':table_name',
                    'category'      => ':category',
                    'position'      => ':position'
                ]
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            foreach ($this->defaults as $d) {
                $stmt->execute(
                    [
                        self::PK        => $d['id'],
                        'table_name'    => $d['table_name'],
                        'category'      => $d['category'],
                        'position'      => $d['position']
                    ]
                );
            }

            Analog::log(
                'Default fields configurations were successfully stored.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize default fields configuration.'
                . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }
}
