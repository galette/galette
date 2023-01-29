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
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2021-11-11
 */

namespace Galette\DynamicFields\test\units;

use atoum;

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
class Text extends atoum
{
    private \Galette\Core\Db $zdb;
    private \Galette\DynamicFields\Text $text;

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
        $this->variable($this->text->getTypeName())
            ->isIdenticalTo(_T('free text'));
    }

    /**
     * Test if basic properties are ok
     *
     * @return void
     */
    public function testBaseProperties()
    {
        $muliple = $this->text->isMultiValued();
        $this->boolean($muliple)->isFalse();

        $required = $this->text->isRequired();
        $this->boolean($required)->isFalse();

        $name = $this->text->getName();
        $this->variable($name)->isIdenticalTo('');

        $has_fixed_values = $this->text->hasFixedValues();
        $this->boolean($has_fixed_values)->isFalse();

        $has_data = $this->text->hasData();
        $this->boolean($has_data)->isTrue();

        $has_w = $this->text->hasWidth();
        $this->boolean($has_w)->isTrue();

        $has_h = $this->text->hasHeight();
        $this->boolean($has_h)->isTrue();

        $has_s = $this->text->hasSize();
        $this->boolean($has_s)->isFalse();

        $perms = $this->text->getPerm();
        $this->variable($perms)->isNull();

        $width = $this->text->getWidth();
        $this->variable($width)->isNull();

        $height = $this->text->getHeight();
        $this->variable($height)->isNull();

        $repeat = $this->text->getRepeat();
        $this->integer($repeat)->isIdenticalTo(1);

        $repeat = $this->text->isRepeatable();
        $this->boolean($repeat)->isTrue();

        $size = $this->text->getSize();
        $this->variable($size)->isNull();

        $values = $this->text->getValues();
        $this->boolean($values)->isFalse();

        $this->boolean($this->text->hasPermissions())->isTrue();
    }
}
