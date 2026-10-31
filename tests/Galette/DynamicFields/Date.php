<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\DynamicFields;

use Galette\DynamicFields\DynamicField;
use Galette\Entity\DynamicFieldsHandle;
use Galette\Tests\DynamicFieldsHelper;
use Galette\Tests\GaletteTestCase;

/**
 * Dynamic date test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Date extends GaletteTestCase
{
    use DynamicFieldsHelper;

    private \Galette\DynamicFields\Date $date;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->date = new \Galette\DynamicFields\Date($this->zdb);
    }

    /**
     * Test constructor
     */
    public function testConstructor(): void
    {
        $o = new \Galette\DynamicFields\Date($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     */
    public function testGetTypeName(): void
    {
        $this->assertSame(_T('date'), $this->date->getTypeName());
    }

    /**
     * Test if basic properties are ok
     */
    public function testBaseProperties(): void
    {
        $muliple = $this->date->isMultiValued();
        $this->assertTrue($muliple);

        $required = $this->date->isRequired();
        $this->assertFalse($required);

        $name = $this->date->getName();
        $this->assertSame('', $name);

        $has_fixed_values = $this->date->hasFixedValues();
        $this->assertFalse($has_fixed_values);

        $has_data = $this->date->hasData();
        $this->assertTrue($has_data);

        $has_w = $this->date->hasWidth();
        $this->assertFalse($has_w);

        $has_h = $this->date->hasHeight();
        $this->assertFalse($has_h);

        $has_s = $this->date->hasSize();
        $this->assertFalse($has_s);

        $perms = $this->date->getPermission();
        $this->assertNull($perms);

        $width = $this->date->getWidth();
        $this->assertNull($width);

        $height = $this->date->getHeight();
        $this->assertNull($height);

        $repeat = $this->date->getRepeat();
        $this->assertNull($repeat);

        $repeat = $this->date->isRepeatable();
        $this->assertFalse($repeat);

        $size = $this->date->getSize();
        $this->assertNull($size);

        $values = $this->date->getValues();
        $this->assertFalse($values);

        $this->assertTrue($this->date->hasPermissions());
    }

    /**
     * Test displayed value
     */
    public function testDisplayValue(): void
    {
        $this->assertSame(
            '2025-05-26',
            $this->date->getDisplayValue('2025-05-26')
        );
        $this->assertSame(
            '26/05/2025',
            $this->date->getDisplayValue('26/05/2025')
        );
        $this->assertSame(
            'notadate',
            $this->date->getDisplayValue('notadate')
        );
        $this->assertSame(
            '',
            $this->date->getDisplayValue('')
        );
        $this->assertSame(
            '',
            $this->date->getDisplayValue(value: null)
        );
    }

    /**
     * Get stored values of a dynamic field
     *
     * @param int $field Field id
     *
     * @return array<int, string> Index => value
     */
    private function getStoredValues(int $field): array
    {
        $select = $this->zdb->select(DynamicFieldsHandle::TABLE);
        $select->where([DynamicField::PK => $field])->order('val_index');
        $values = [];
        foreach ($this->zdb->execute($select) as $row) {
            $values[(int)$row->val_index] = $row->field_val;
        }
        return $values;
    }

    /**
     * Dates stored in their localized format before 1.2.0 are fixed
     */
    public function testResetLocalizedFormats(): void
    {
        //nothing to fix
        $this->assertTrue(\Galette\DynamicFields\Date::resetLocalizedFormats($this->zdb));

        $field = $this->createDynamicField(
            DynamicField::DATE,
            'Localized date',
            ['field_repeat' => 6]
        );
        //a text field holding something that looks like a date is left alone
        $text = $this->createDynamicField(DynamicField::LINE, 'Not a date');

        $this->storeDynamicValues([
            [1, $field, 1, '2020-12-26'],
            [1, $field, 2, '26/12/2020'],
            [1, $field, 3, '26.12.2020'],
            [1, $field, 4, '2020/12/26'],
            //26 is not a month: only the US format matches
            [1, $field, 5, '12.26.2020'],
            [1, $field, 6, 'not a date'],
            [1, $text, 1, '26/12/2020'],
        ]);

        $this->assertTrue(\Galette\DynamicFields\Date::resetLocalizedFormats($this->zdb));

        $this->assertSame(
            [
                1 => '2020-12-26',
                2 => '2020-12-26',
                3 => '2020-12-26',
                4 => '2020-12-26',
                5 => '2020-12-26',
                6 => 'not a date',
            ],
            $this->getStoredValues($field)
        );
        $this->assertSame([1 => '26/12/2020'], $this->getStoredValues($text));
    }
}
