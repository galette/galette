<?php

/**
 * Copyright © 2003-2026 The Galette Team
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
