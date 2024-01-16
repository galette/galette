<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic separator tests
 *
 * PHP version 5
 *
 * Copyright © 2013-2023 The Galette Team
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
 *
 * @category  DynamicFields
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2013-01-14
 */

namespace Galette\DynamicFields\test\units;

use PHPUnit\Framework\TestCase;

/**
 * Dynamic separator test
 *
 * @category  DynamicFields
 * @name      Separator
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     2013-01-14
 */
class Separator extends TestCase
{
    private \Galette\Core\Db $zdb;
    private \Galette\DynamicFields\Separator $separator;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->separator = new \Galette\DynamicFields\Separator($this->zdb);
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor()
    {
        $o = new \Galette\DynamicFields\Separator($this->zdb, 10);
        $this->assertNull($o->getId());
    }

    /**
     * Test get type name
     *
     * @return void
     */
    public function testGetTypeName()
    {
        $this->assertSame(_T('separator'), $this->separator->getTypeName());
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties()
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

        $perms = $this->separator->getPerm();
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
