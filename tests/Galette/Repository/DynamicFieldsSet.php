<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Repository;

use Galette\DynamicFields\DynamicField;
use Galette\Tests\DynamicFieldsHelper;
use Galette\Tests\GaletteTestCase;

/**
 * Dynamic fields set repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DynamicFieldsSet extends GaletteTestCase
{
    use DynamicFieldsHelper;

    protected int $seed = 20261023093512;

    /**
     * Test getValuesFor
     */
    public function testGetValuesFor(): void
    {
        $fieldset = new \Galette\Repository\DynamicFieldsSet($this->zdb, $this->login);
        $this->assertSame([], $fieldset->getValuesFor('adh', [], [1]));
        $this->assertSame([], $fieldset->getValuesFor('adh', [1], []));

        $phones = $this->createDynamicField(DynamicField::LINE, 'Phones', ['field_repeat' => 0]);
        $size = $this->createDynamicField(DynamicField::LINE, 'Size');
        $other = $this->createDynamicField(DynamicField::LINE, 'Other');

        //inserted out of order, to check occurrences are sorted
        $this->storeDynamicValues([
            [1, $phones, 2, '0607080910'],
            [1, $phones, 1, '0102030405'],
            [1, $size, 1, 'M'],
            [2, $size, 1, 'L'],
            [3, $size, 1, 'S'],
            [1, $other, 1, 'not requested'],
        ]);
        //same item id, another form
        $this->storeDynamicValues([[2, $phones, 1, 'contribution']], 'contrib');

        $this->assertSame(
            [
                $phones => [1 => ['0102030405', '0607080910']],
                $size => [1 => ['M'], 2 => ['L']],
            ],
            $fieldset->getValuesFor('adh', [1, 2], [$phones, $size])
        );
        $this->assertSame(
            [$phones => [2 => ['contribution']]],
            $fieldset->getValuesFor('contrib', [1, 2], [$phones, $size])
        );
    }

    /**
     * Test getTextValue
     */
    public function testGetTextValue(): void
    {
        $line = DynamicField::getFieldType($this->zdb, DynamicField::LINE);
        $this->assertSame('a <b>value</b>', \Galette\Repository\DynamicFieldsSet::getTextValue($line, 'a <b>value</b>'));

        $text = DynamicField::getFieldType($this->zdb, DynamicField::TEXT);
        $this->assertSame(
            "First line\nSecond line",
            \Galette\Repository\DynamicFieldsSet::getTextValue($text, "First line\nSecond line")
        );

        $size = $this->createDynamicField(DynamicField::CHOICE, 'Size', ['fixed_values' => "S\nM\nL"]);
        $choice = DynamicField::loadFieldType($this->zdb, $size);
        $this->assertInstanceOf(\Galette\DynamicFields\Choice::class, $choice);
        $this->assertSame('M', \Galette\Repository\DynamicFieldsSet::getTextValue($choice, '1'));
        //imported choices may be stored as text
        $this->assertSame('XL', \Galette\Repository\DynamicFieldsSet::getTextValue($choice, 'XL'));

        $volunteer = DynamicField::getFieldType($this->zdb, DynamicField::BOOLEAN);
        $this->assertSame(_T('Yes'), \Galette\Repository\DynamicFieldsSet::getTextValue($volunteer, '1'));
        $this->assertSame(_T('No'), \Galette\Repository\DynamicFieldsSet::getTextValue($volunteer, '0'));

        $date = DynamicField::getFieldType($this->zdb, DynamicField::DATE);
        $this->assertSame('2026-03-01', \Galette\Repository\DynamicFieldsSet::getTextValue($date, '2026-03-01'));
    }
}
