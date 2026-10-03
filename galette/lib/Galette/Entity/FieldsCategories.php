<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
    public const string TABLE = 'fields_categories';
    public const string PK = 'id_field_category';

    public const int ADH_CATEGORY_IDENTITY = 1;
    public const int ADH_CATEGORY_GALETTE = 2;
    public const int ADH_CATEGORY_CONTACT = 3;

    /**
     * Default constructor
     *
     * @param Db                  $zdb      Database
     * @param array<string,mixed> $defaults default values
     */
    public function __construct(private readonly Db $zdb, private readonly array $defaults)
    {
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
     */
    public static function setCategories(Db $zdb, array $categories): bool
    {
        try {
            $zdb->beginTransaction();

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
            $zdb->commit();
            return true;
        } catch (Throwable $e) {
            $zdb->rollback();
            throw $e;
        }
    }

    /**
     * Set default fields categories at install time
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
