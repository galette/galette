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

namespace Galette\DynamicFields\test\units;

use Galette\GaletteTestCase;

/**
 * Dynamic single line test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Line extends GaletteTestCase
{
    private \Galette\DynamicFields\Line $line;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->line = new \Galette\DynamicFields\Line($this->zdb);
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\DynamicFields\DynamicField::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $o = new \Galette\DynamicFields\Line($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     *
     * @return void
     */
    public function testGetTypeName(): void
    {
        $this->assertSame(_T('single line'), $this->line->getTypeName());
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties(): void
    {
        $muliple = $this->line->isMultiValued();
        $this->assertTrue($muliple);

        $required = $this->line->isRequired();
        $this->assertFalse($required);

        $name = $this->line->getName();
        $this->assertSame('', $name);

        $has_fixed_values = $this->line->hasFixedValues();
        $this->assertFalse($has_fixed_values);

        $has_data = $this->line->hasData();
        $this->assertTrue($has_data);

        $has_w = $this->line->hasWidth();
        $this->assertTrue($has_w);

        $has_h = $this->line->hasHeight();
        $this->assertFalse($has_h);

        $has_s = $this->line->hasSize();
        $this->assertTrue($has_s);

        $perms = $this->line->getPermission();
        $this->assertNull($perms);

        $width = $this->line->getWidth();
        $this->assertNull($width);

        $height = $this->line->getHeight();
        $this->assertNull($height);

        $repeat = $this->line->getRepeat();
        $this->assertNull($repeat);

        $repeat = $this->line->isRepeatable();
        $this->assertFalse($repeat);

        $size = $this->line->getSize();
        $this->assertNull($size);

        $values = $this->line->getValues();
        $this->assertFalse($values);

        $this->assertTrue($this->line->hasPermissions());
    }

    /**
     * Test from database
     *
     * @return void
     */
    public function testInDb(): void
    {
        //add dynamic fields on contributions
        $field_data = [
            'form_name'         => 'contrib',
            'field_name'        => 'Dynamic line',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::LINE,
            'field_required'    => 0,
            'field_size'        => 255,
            'field_width'       => 50,
            'field_height'      => 10
        ];

        $tdf = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $tdf->store($field_data);
        $error_detected = $tdf->getErrors();
        $warning_detected = $tdf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $tdf->getErrors() + $tdf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $tdf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $tdf->getWarnings()));

        $id = $tdf->getId();
        $this->assertIsInt($id);

        //load from DB.
        $tdf = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type'], $id);
    }
}
