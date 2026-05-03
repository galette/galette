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
 * Dynamic file test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class File extends GaletteTestCase
{
    private \Galette\DynamicFields\File $file;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->file = new \Galette\DynamicFields\File($this->zdb);
    }

    /**
     * Test constructor
     */
    public function testConstructor(): void
    {
        $o = new \Galette\DynamicFields\File($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     */
    public function testGetTypeName(): void
    {
        $this->assertSame(_T('file'), $this->file->getTypeName());
    }

    /**
     * Test if basic properties are ok
     */
    public function testBaseProperties(): void
    {
        $muliple = $this->file->isMultiValued();
        $this->assertFalse($muliple);

        $required = $this->file->isRequired();
        $this->assertFalse($required);

        $name = $this->file->getName();
        $this->assertSame('', $name);

        $has_fixed_values = $this->file->hasFixedValues();
        $this->assertFalse($has_fixed_values);

        $has_data = $this->file->hasData();
        $this->assertTrue($has_data);

        $has_w = $this->file->hasWidth();
        $this->assertFalse($has_w);

        $has_h = $this->file->hasHeight();
        $this->assertFalse($has_h);

        $has_s = $this->file->hasSize();
        $this->assertTrue($has_s);

        $perms = $this->file->getPermission();
        $this->assertNull($perms);

        $width = $this->file->getWidth();
        $this->assertNull($width);

        $height = $this->file->getHeight();
        $this->assertNull($height);

        $repeat = $this->file->getRepeat();
        $this->assertNull($repeat);

        $repeat = $this->file->isRepeatable();
        $this->assertFalse($repeat);

        $size = $this->file->getSize();
        $this->assertNull($size);

        $values = $this->file->getValues();
        $this->assertFalse($values);

        $this->assertTrue($this->file->hasPermissions());
    }

    /**
     * Test displayed value
     */
    public function testDisplayValue(): void
    {
        $this->assertSame(
            'anything',
            $this->file->getDisplayValue('anything')
        );
    }
}
