<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\DynamicFields;

use Galette\Tests\GaletteTestCase;
use Galette\DynamicFields\DynamicField;
use Galette\DynamicFields\Choice;

use function Safe\json_decode;

/**
 * JSON storage test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class JsonStorage extends GaletteTestCase
{
    /**
     * Test JSON storage for choice fields
     */
    public function testChoiceJsonStorage(): void
    {
        $field_data = [
            'form_name'         => 'adh',
            'field_name'        => 'JSON storage test',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'fixed_values'      => "One\nTwo\nThree"
        ];

        $choice = new Choice($this->zdb);
        $this->assertTrue($choice->store($field_data), implode(', ', $choice->getErrors()));
        $id = $choice->getId();

        // 1. Verify JSON column in database
        $select = $this->zdb->select(DynamicField::TABLE);
        $select->where([DynamicField::PK => $id]);
        $result = $this->zdb->execute($select)->current();

        $this->assertNotEmpty($result->field_specifications);
        $spec = json_decode($result->field_specifications, true);
        $this->assertSame(
            [
                [
                    'id' => 0,
                    'value' => 'One'
                ],
                [
                    'id' => 1,
                    'value' => 'Two'
                ],
                [
                    'id' => 2,
                    'value' => 'Three'
                ]
            ],
            $spec['choices']
        );

        // 2. Verify loading works
        $choice2 = new Choice($this->zdb, $id);
        $this->assertSame(['One', 'Two', 'Three'], $choice2->getValues());
    }
}
