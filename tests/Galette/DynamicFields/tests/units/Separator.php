<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic separator tests
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-14
 */

namespace Galette\DynamicFields\test\units;

use atoum;

/**
 * Dynamic separator test
 *
 * @category  DynamicFields
 * @name      Separator
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2013-01-14
 */
class Separator extends atoum
{
    private $zdb;
    private $separator;

    /**
     * Set up tests
     *
     * @param string $testMethod Current test method
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
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
        $this->variable($o->getId())
            ->isIdenticalTo(null);
    }

    /**
     * Test get type name
     *
     * @return void
     */
    public function testGetTypeName()
    {
        $this->variable($this->separator->getTypeName())
            ->isIdenticalTo(_T('separator'));
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties()
    {
        $muliple = $this->separator->isMultiValued();
        $this->boolean($muliple)->isFalse();

        $required = $this->separator->isRequired();
        //should'nt that one be false?
        $this->variable($required)->isNull();

        $name = $this->separator->getName();
        $this->variable($name)->isIdenticalTo('');

        $has_fixed_values = $this->separator->hasFixedValues();
        $this->boolean($has_fixed_values)->isFalse();

        $has_data = $this->separator->hasData();
        $this->boolean($has_data)->isFalse();

        $has_w = $this->separator->hasWidth();
        $this->boolean($has_w)->isFalse();

        $has_h = $this->separator->hasHeight();
        $this->boolean($has_h)->isFalse();

        $has_s = $this->separator->hasSize();
        $this->boolean($has_s)->isFalse();

        $perms = $this->separator->getPerm();
        $this->variable($perms)->isNull();

        $width = $this->separator->getWidth();
        $this->variable($width)->isNull();

        $height = $this->separator->getHeight();
        $this->variable($height)->isNull();

        $repeat = $this->separator->getRepeat();
        $this->variable($repeat)->isNull();

        $size = $this->separator->getSize();
        $this->variable($size)->isNull();

        $values = $this->separator->getValues();
        $this->boolean($values)->isFalse();
    }
}
