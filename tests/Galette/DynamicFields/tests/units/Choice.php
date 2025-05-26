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

use PHPUnit\Framework\TestCase;

/**
 * Dynamic choice test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Choice extends TestCase
{
    private \Galette\Core\Db $zdb;
    private \Galette\DynamicFields\Choice $choice;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->choice = new \Galette\DynamicFields\Choice($this->zdb);
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $o = new \Galette\DynamicFields\Choice($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     *
     * @return void
     */
    public function testGetTypeName(): void
    {
        $this->assertSame(_T('choice'), $this->choice->getTypeName());
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties(): void
    {
        $muliple = $this->choice->isMultiValued();
        $this->assertFalse($muliple);

        $required = $this->choice->isRequired();
        $this->assertFalse($required);

        $name = $this->choice->getName();
        $this->assertSame('', $name);

        $has_fixed_values = $this->choice->hasFixedValues();
        $this->assertTrue($has_fixed_values);

        $has_data = $this->choice->hasData();
        $this->assertTrue($has_data);

        $has_w = $this->choice->hasWidth();
        $this->assertFalse($has_w);

        $has_h = $this->choice->hasHeight();
        $this->assertFalse($has_h);

        $has_s = $this->choice->hasSize();
        $this->assertFalse($has_s);

        $perms = $this->choice->getPermission();
        $this->assertNull($perms);

        $width = $this->choice->getWidth();
        $this->assertNull($width);

        $height = $this->choice->getHeight();
        $this->assertNull($height);

        $repeat = $this->choice->getRepeat();
        $this->assertNull($repeat);

        $repeat = $this->choice->isRepeatable();
        $this->assertFalse($repeat);

        $size = $this->choice->getSize();
        $this->assertNull($size);

        $values = $this->choice->getValues();
        $this->assertFalse($values);

        $this->assertTrue($this->choice->hasPermissions());
    }

    /**
     * Test displayed value
     *
     * @return void
     */
    public function testDisplayValue(): void
    {
        $checked = $this->choice->check([
            'form_name' => 'adh',
            'field_name' => 'test_choice',
            'field_perm' => \Galette\Entity\FieldsConfig::USER_WRITE,
            'fixed_values' => "One\nTwo\nThree"
        ]);
        $this->assertTrue($checked, implode(', ', $this->choice->getErrors()));
        $this->assertSame(
            'One',
            $this->choice->getDisplayValue(0)
        );
        $this->assertSame(
            'Two',
            $this->choice->getDisplayValue(1)
        );
        $this->assertSame(
            'Three',
            $this->choice->getDisplayValue(2)
        );
        $this->assertSame(
            '',
            $this->choice->getDisplayValue(4)
        );
        $this->assertSame(
            '',
            $this->choice->getDisplayValue(null)
        );
    }
}
