<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic booleans tests
 *
 * PHP version 5
 *
 * Copyright © 2013-2023 The Galette Team
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
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-10-18
 */

namespace Galette\DynamicFields\test\units;

use atoum;

/**
 * Dynamic booleans test
 *
 * @category  DynamicFields
 * @name      Boolean
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-10-18
 */
class Boolean extends atoum
{
    private \Galette\Core\Db $zdb;
    private \Galette\DynamicFields\Boolean $bool;

    /**
     * Set up tests
     *
     * @param string $method Current test method
     *
     * @return void
     */
    public function beforeTestMethod($method)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->bool = new \Galette\DynamicFields\Boolean($this->zdb);
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor()
    {
        $o = new \Galette\DynamicFields\Boolean($this->zdb, 10);
        $this->variable($o->getId())
            ->isNull();
    }

    /**
     * Test get type name
     *
     * @return void
     */
    public function testGetTypeName()
    {
        $this->variable($this->bool->getTypeName())
            ->isIdenticalTo(_T('boolean'));
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties()
    {
        $muliple = $this->bool->isMultiValued();
        $this->boolean($muliple)->isFalse();

        $required = $this->bool->isRequired();
        $this->boolean($required)->isFalse();

        $name = $this->bool->getName();
        $this->variable($name)->isIdenticalTo('');

        $has_fixed_values = $this->bool->hasFixedValues();
        $this->boolean($has_fixed_values)->isFalse();

        $has_data = $this->bool->hasData();
        $this->boolean($has_data)->isTrue();

        $has_w = $this->bool->hasWidth();
        $this->boolean($has_w)->isFalse();

        $has_h = $this->bool->hasHeight();
        $this->boolean($has_h)->isFalse();

        $has_s = $this->bool->hasSize();
        $this->boolean($has_s)->isFalse();

        $perms = $this->bool->getPerm();
        $this->variable($perms)->isNull();

        $width = $this->bool->getWidth();
        $this->variable($width)->isNull();

        $height = $this->bool->getHeight();
        $this->variable($height)->isNull();

        $repeat = $this->bool->getRepeat();
        $this->variable($repeat)->isNull();

        $repeat = $this->bool->isRepeatable();
        $this->boolean($repeat)->isFalse();

        $size = $this->bool->getSize();
        $this->variable($size)->isNull();

        $values = $this->bool->getValues();
        $this->boolean($values)->isFalse();

        $this->boolean($this->bool->hasPermissions())->isTrue();
    }
}
