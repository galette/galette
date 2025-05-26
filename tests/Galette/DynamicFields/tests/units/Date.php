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
 * Dynamic date test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Date extends TestCase
{
    private \Galette\Core\Db $zdb;
    private \Galette\DynamicFields\Date $date;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->date = new \Galette\DynamicFields\Date($this->zdb);
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $o = new \Galette\DynamicFields\Date($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     *
     * @return void
     */
    public function testGetTypeName(): void
    {
        $this->assertSame(_T('date'), $this->date->getTypeName());
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties(): void
    {
        $muliple = $this->date->isMultiValued();
        $this->assertFalse($muliple);

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
     *
     * @return void
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
    }
}
