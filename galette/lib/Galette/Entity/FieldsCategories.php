<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Fields categories handling
 *
 * PHP version 5
 *
 * Copyright © 2009-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-28
 */

namespace Galette\Entity;

use Throwable;
use Analog\Analog;
use Galette\Core\Db;

/**
 * Fields categories class for galette
 *
 * @category  Entity
 * @name      FieldsCategories
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-03-28
 */

class FieldsCategories
{
    public const TABLE = 'fields_categories';
    public const PK = 'id_field_category';

    private array $defaults;

    private Db $zdb;

    public const ADH_CATEGORY_IDENTITY = 1;
    public const ADH_CATEGORY_GALETTE = 2;
    public const ADH_CATEGORY_CONTACT = 3;

    /**
     * Default constructor
     *
     * @param Db    $zdb      Database
     * @param array $defaults default values
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
     * @return array
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
                '[' . static::class . '] Cannot get fields categories list | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Store the categories
     *
     * @param Db    $zdb        Database
     * @param array $categories Categories
     *
     * @return boolean
     */
    public static function setCategories(Db $zdb, array $categories): bool
    {
        try {
            $zdb->connection->beginTransaction();

            $update = $zdb->update(self::TABLE);
            $update->set(
                array(
                    'position' => ':position'
                )
            )->where(
                array(
                    self::PK => ':pk'
                )
            );
            $stmt = $zdb->sql->prepareStatementForSqlObject($update);

            foreach ($categories as $k => $v) {
                $params = array(
                    'position'  => $k,
                    'pk'        => $v
                );
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
                array(
                    self::PK        => ':' . self::PK,
                    'table_name'    => ':table_name',
                    'category'      => ':category',
                    'position'      => ':position'
                )
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            foreach ($this->defaults as $d) {
                $stmt->execute(
                    array(
                        self::PK        => $d['id'],
                        'table_name'    => $d['table_name'],
                        'category'      => $d['category'],
                        'position'      => $d['position']
                    )
                );
            }

            Analog::log(
                'Default fields configurations were successfully stored.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize default fields configuration.' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }
}
