<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Dynamic choice tests
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
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-11
 */

namespace Galette\DynamicFields\test\units;

use atoum;

/**
 * Dynamic choice test
 *
 * @category  DynamicFields
 * @name      Choice
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-11
 */
class Choice extends atoum
{
    private \Galette\Core\Db $zdb;
    private \Galette\DynamicFields\Choice $choice;

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
        $this->choice = new \Galette\DynamicFields\Choice($this->zdb);
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor()
    {
        $o = new \Galette\DynamicFields\Choice($this->zdb, 10);
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
        $this->variable($this->choice->getTypeName())
            ->isIdenticalTo(_T('choice'));
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties()
    {
        $muliple = $this->choice->isMultiValued();
        $this->boolean($muliple)->isFalse();

        $required = $this->choice->isRequired();
        $this->boolean($required)->isFalse();

        $name = $this->choice->getName();
        $this->variable($name)->isIdenticalTo('');

        $has_fixed_values = $this->choice->hasFixedValues();
        $this->boolean($has_fixed_values)->isTrue();

        $has_data = $this->choice->hasData();
        $this->boolean($has_data)->isTrue();

        $has_w = $this->choice->hasWidth();
        $this->boolean($has_w)->isFalse();

        $has_h = $this->choice->hasHeight();
        $this->boolean($has_h)->isFalse();

        $has_s = $this->choice->hasSize();
        $this->boolean($has_s)->isFalse();

        $perms = $this->choice->getPerm();
        $this->variable($perms)->isNull();

        $width = $this->choice->getWidth();
        $this->variable($width)->isNull();

        $height = $this->choice->getHeight();
        $this->variable($height)->isNull();

        $repeat = $this->choice->getRepeat();
        $this->variable($repeat)->isNull();

        $repeat = $this->choice->isRepeatable();
        $this->boolean($repeat)->isFalse();

        $size = $this->choice->getSize();
        $this->variable($size)->isNull();

        $values = $this->choice->getValues();
        $this->boolean($values)->isFalse();

        $this->boolean($this->choice->hasPermissions())->isTrue();
    }
}
