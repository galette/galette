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
 * Dynamic texts test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Text extends GaletteTestCase
{
    private \Galette\DynamicFields\Text $text;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->text = new \Galette\DynamicFields\Text($this->zdb);
    }

    /**
     * Test constructor
     */
    public function testConstructor(): void
    {
        $o = new \Galette\DynamicFields\Text($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     */
    public function testGetTypeName(): void
    {
        $this->assertSame(_T('free text'), $this->text->getTypeName());
    }

    /**
     * Test if basic properties are ok
     */
    public function testBaseProperties(): void
    {
        $muliple = $this->text->isMultiValued();
        $this->assertFalse($muliple);

        $required = $this->text->isRequired();
        $this->assertFalse($required);

        $name = $this->text->getName();
        $this->assertSame('', $name);

        $has_fixed_values = $this->text->hasFixedValues();
        $this->assertFalse($has_fixed_values);

        $has_data = $this->text->hasData();
        $this->assertTrue($has_data);

        $has_w = $this->text->hasWidth();
        $this->assertTrue($has_w);

        $has_h = $this->text->hasHeight();
        $this->assertTrue($has_h);

        $has_s = $this->text->hasSize();
        $this->assertFalse($has_s);

        $perms = $this->text->getPermission();
        $this->assertNull($perms);

        $width = $this->text->getWidth();
        $this->assertNull($width);

        $height = $this->text->getHeight();
        $this->assertNull($height);

        $repeat = $this->text->getRepeat();
        $this->assertSame(1, $repeat);

        $repeat = $this->text->isRepeatable();
        $this->assertTrue($repeat);

        $size = $this->text->getSize();
        $this->assertNull($size);

        $values = $this->text->getValues();
        $this->assertFalse($values);

        $this->assertTrue($this->text->hasPermissions());
    }

    /**
     * Test displayed value
     */
    public function testDisplayValue(): void
    {
        $this->assertSame(
            'anything',
            $this->text->getDisplayValue('anything')
        );

        $this->assertSame(
            'https://galette.eu',
            $this->text->getDisplayValue('https://galette.eu')
        );

        $this->assertSame(
            "Line 1<br />\nLine 2",
            $this->text->getDisplayValue("Line 1\nLine 2")
        );
    }
}
