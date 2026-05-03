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
 * Dynamic choice test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Choice extends GaletteTestCase
{
    private \Galette\DynamicFields\Choice $choice;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->choice = new \Galette\DynamicFields\Choice($this->zdb);
    }

    /**
     * Test constructor
     */
    public function testConstructor(): void
    {
        $o = new \Galette\DynamicFields\Choice($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     */
    public function testGetTypeName(): void
    {
        $this->assertSame(_T('choice'), $this->choice->getTypeName());
    }

    /**
     * Test if basic properties are ok
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
