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

use ArrayObject;
use Galette\Core\Db;
use Galette\Core\Authentication;
use Galette\Core\Login;
use Galette\DynamicFields\DynamicField;
use Galette\Entity\FieldsConfig;

/**
 * Dynamic field descriptors set
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class DynamicFieldsSet
{
    private Db $zdb;
    private Login $login;

    /**
     * Main constructor
     *
     * @param Db    $zdb   Database instance
     * @param Login $login Login instance
     */
    public function __construct(Db $zdb, Login $login)
    {
        $this->zdb = $zdb;
        $this->login = $login;
    }

    /**
     * Get form names and associated classes
     *
     * @return array<string, string>
     */
    public static function getClasses(): array
    {
        return [
            'adh' => \Galette\Entity\Adherent::class,
            'contrib' => \Galette\Entity\Contribution::class,
            'trans' => \Galette\Entity\Transaction::class,
        ];
    }

    /**
     * Get fields list for one form
     *
     * @param string $form_name Form name
     *
     * @return DynamicField[]
     */
    public function getList(string $form_name): array
    {
        $select = $this->zdb->select(DynamicField::TABLE);
        $where = ['field_form' => $form_name];

        $select
            ->where($where)
            ->order('field_index');

        $results = $this->zdb->execute($select);
        $access_level = $this->login->getAccessLevel();

        $fields = [];
        if ($results->count() > 0) {
            foreach ($results as $r) {
                /** @var ArrayObject<string, int|string> $r */
                $perm = $r['field_perm'];
                if (
                    ($perm == FieldsConfig::MANAGER
                        && $access_level < Authentication::ACCESS_MANAGER)
                    || ($perm == FieldsConfig::STAFF
                         && $access_level < Authentication::ACCESS_STAFF)
                    || ($perm == FieldsConfig::ADMIN
                        && $access_level < Authentication::ACCESS_ADMIN)
                ) {
                    continue;
                }
                $df = DynamicField::getFieldType($this->zdb, (int)$r['field_type']);
                $df->loadFromRS($r);
                $fields[$r[DynamicField::PK]] = $df;
            }
        }
        return $fields;
    }
}
