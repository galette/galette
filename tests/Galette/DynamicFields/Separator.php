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
 * Dynamic separator test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Separator extends GaletteTestCase
{
    private \Galette\DynamicFields\Separator $separator;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->separator = new \Galette\DynamicFields\Separator($this->zdb);
    }

    /**
     * Test constructor
     */
    public function testConstructor(): void
    {
        $o = new \Galette\DynamicFields\Separator($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     */
    public function testGetTypeName(): void
    {
        $this->assertSame(_T('separator'), $this->separator->getTypeName());
    }

    /**
     * Test if basic properties are ok
     */
    public function testBaseProperties(): void
    {
        $muliple = $this->separator->isMultiValued();
        $this->assertFalse($muliple);

        $required = $this->separator->isRequired();
        $this->assertFalse($required);

        $name = $this->separator->getName();
        $this->assertSame('', $name);

        $has_fixed_values = $this->separator->hasFixedValues();
        $this->assertFalse($has_fixed_values);

        $has_data = $this->separator->hasData();
        $this->assertFalse($has_data);

        $has_w = $this->separator->hasWidth();
        $this->assertFalse($has_w);

        $has_h = $this->separator->hasHeight();
        $this->assertFalse($has_h);

        $has_s = $this->separator->hasSize();
        $this->assertFalse($has_s);

        $perms = $this->separator->getPermission();
        $this->assertNull($perms);

        $width = $this->separator->getWidth();
        $this->assertNull($width);

        $height = $this->separator->getHeight();
        $this->assertNull($height);

        $repeat = $this->separator->getRepeat();
        $this->assertNull($repeat);

        $repeat = $this->separator->isRepeatable();
        $this->assertFalse($repeat);

        $size = $this->separator->getSize();
        $this->assertNull($size);

        $values = $this->separator->getValues();
        $this->assertFalse($values);

        $this->assertFalse($this->separator->hasPermissions());
    }
}
