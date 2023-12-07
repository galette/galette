<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic texts tests
 *
 * PHP version 5
 *
 * Copyright © 2021-2023 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  DynamicFields
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-11
 */

namespace Galette\DynamicFields\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Dynamic texts test
 *
 * @category  DynamicFields
 * @name      Text
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-11
 */
class Text extends TestCase
{
    private \Galette\Core\Db $zdb;
    private \Galette\DynamicFields\Text $text;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->text = new \Galette\DynamicFields\Text($this->zdb);
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor()
    {
        $o = new \Galette\DynamicFields\Text($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     *
     * @return void
     */
    public function testGetTypeName()
    {
        $this->assertSame(_T('free text'), $this->text->getTypeName());
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties()
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

        $perms = $this->text->getPerm();
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
}
