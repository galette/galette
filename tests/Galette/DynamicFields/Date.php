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
 * Dynamic date test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Date extends GaletteTestCase
{
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
            $this->date->getDisplayValue(null)
        );
    }
}
