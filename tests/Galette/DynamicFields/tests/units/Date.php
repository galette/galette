<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic date tests
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
 * Dynamic date test
 *
 * @category  DynamicFields
 * @name      Date
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-11
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
    public function testConstructor()
    {
        $o = new \Galette\DynamicFields\Date($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     *
     * @return void
     */
    public function testGetTypeName()
    {
        $this->assertSame(_T('date'), $this->date->getTypeName());
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties()
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

        $perms = $this->date->getPerm();
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
}
