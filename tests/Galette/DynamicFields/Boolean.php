<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\DynamicFields;

use Galette\Tests\GaletteTestCase;

/**
 * Dynamic booleans test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Boolean extends GaletteTestCase
{
    private \Galette\DynamicFields\Boolean $bool;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->bool = new \Galette\DynamicFields\Boolean($this->zdb);
    }

    /**
     * Test constructor
     */
    public function testConstructor(): void
    {
        $o = new \Galette\DynamicFields\Boolean($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     */
    public function testGetTypeName(): void
    {
        $this->assertSame(_T('boolean'), $this->bool->getTypeName());
    }

    /**
     * Test if basic properties are ok
     */
    public function testBaseProperties(): void
    {
        $muliple = $this->bool->isMultiValued();
        $this->assertFalse($muliple);

        $required = $this->bool->isRequired();
        $this->assertFalse($required);

        $name = $this->bool->getName();
        $this->assertSame('', $name);

        $has_fixed_values = $this->bool->hasFixedValues();
        $this->assertFalse($has_fixed_values);

        $has_data = $this->bool->hasData();
        $this->assertTrue($has_data);

        $has_w = $this->bool->hasWidth();
        $this->assertFalse($has_w);

        $has_h = $this->bool->hasHeight();
        $this->assertFalse($has_h);

        $has_s = $this->bool->hasSize();
        $this->assertFalse($has_s);

        $perms = $this->bool->getPermission();
        $this->assertNull($perms);

        $width = $this->bool->getWidth();
        $this->assertNull($width);

        $height = $this->bool->getHeight();
        $this->assertNull($height);

        $repeat = $this->bool->getRepeat();
        $this->assertNull($repeat);

        $repeat = $this->bool->isRepeatable();
        $this->assertFalse($repeat);

        $size = $this->bool->getSize();
        $this->assertNull($size);

        $values = $this->bool->getValues();
        $this->assertFalse($values);

        $this->assertTrue($this->bool->hasPermissions());
    }

    /**
     * Test from database
     */
    public function testInDb(): void
    {
        //add dynamic fields on contributions
        $field_data = [
            'form_name'         => 'contrib',
            'field_name'        => 'Dynamic boolean',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::BOOLEAN,
            'field_required'    => 0
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
        \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type'], $id);
    }

    /**
     * Test displayed value
     */
    public function testDisplayValue(): void
    {
        $this->assertSame(
            'Yes',
            $this->bool->getDisplayValue(1)
        );
        $this->assertSame(
            'Yes',
            $this->bool->getDisplayValue('azerty')
        );
        $this->assertSame(
            'No',
            $this->bool->getDisplayValue(0)
        );
        $this->assertSame(
            'No',
            $this->bool->getDisplayValue(null)
        );
    }
}
