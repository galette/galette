<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
    /**
     * Main constructor
     *
     * @param Db    $zdb   Database instance
     * @param Login $login Login instance
     */
    public function __construct(private readonly Db $zdb, private readonly Login $login)
    {
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
            'prefs' => \Galette\Core\Preferences::class
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
