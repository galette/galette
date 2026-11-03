<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests;

use Galette\DynamicFields\DynamicField;
use Galette\Entity\DynamicFieldsHandle;
use Galette\Entity\FieldsConfig;

/**
 * Create dynamic fields and their values in tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait DynamicFieldsHelper
{
    /**
     * Create a members dynamic field
     *
     * @param int                 $type  Field type
     * @param string              $name  Field name
     * @param array<string,mixed> $extra Extra field data
     *
     * @return int Field id
     */
    private function createDynamicField(int $type, string $name, array $extra = []): int
    {
        $field_data = $extra + [
            'form_name'         => 'adh',
            'field_name'        => $name,
            'field_perm'        => FieldsConfig::USER_WRITE,
            'field_type'        => $type,
            'field_required'    => 0,
            'field_repeat'      => 1
        ];

        $df = DynamicField::getFieldType($this->zdb, $type);
        $this->assertTrue($df->store($field_data), implode(' ', $df->getErrors()));
        return (int)$df->getId();
    }

    /**
     * Store raw dynamic fields values
     *
     * @param array<int,array{0:int,1:int,2:int,3:string}> $values item id, field id, index and value
     * @param string                                       $form   Form name
     */
    private function storeDynamicValues(array $values, string $form = 'adh'): void
    {
        foreach ($values as [$item, $field, $index, $value]) {
            $insert = $this->zdb->insert(DynamicFieldsHandle::TABLE);
            $insert->values([
                'item_id'       => $item,
                'field_id'      => $field,
                'field_form'    => $form,
                'val_index'     => $index,
                'field_val'     => $value
            ]);
            $this->zdb->execute($insert);
        }
    }
}
