<?php

/**
 * Copyright © 2003-2026 The Galette Team
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
 */

declare(strict_types=1);

namespace Galette\Tests\DynamicFields;

use Galette\Tests\GaletteTestCase;
use Galette\DynamicFields\ChoiceSpecifications;

/**
 * Specifications test
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Specifications extends GaletteTestCase
{
    /**
     * Test FieldSpecifications generic behavior
     */
    public function testFieldSpecificationsGeneric(): void
    {
        $spec = new ChoiceSpecifications();
        $this->assertNull($spec->foo);

        $spec->foo = 'bar';
        $this->assertSame('bar', $spec->foo);
        $this->assertTrue(isset($spec->foo));

        $json = json_encode($spec);
        $this->assertStringContainsString('"foo":"bar"', $json);

        $spec2 = (new ChoiceSpecifications())->fromJson($json);
        $this->assertSame('bar', $spec2->foo);
    }

    /**
     * Test ChoiceSpecifications specialized behavior
     */
    public function testChoiceSpecifications(): void
    {
        $spec = new ChoiceSpecifications();
        $this->assertSame([], $spec->getChoices());

        $choices = [10 => 'One', 11 => 'Two'];
        $spec->setChoices($choices);
        $expected = [
            0 => 'One',
            1 => 'Two'
        ];
        $this->assertSame($expected, $spec->getChoices());

        $json = json_encode($spec);
        $this->assertSame('{"choices":[{"id":0,"value":"One"},{"id":1,"value":"Two"}]}', $json);

        $spec2 = (new ChoiceSpecifications())->fromJson($json);
        $this->assertSame('Two', $spec2->getChoices()[1]);
        $this->assertIsInt(array_keys($spec2->getChoices())[0]);
        $this->assertInstanceOf(ChoiceSpecifications::class, $spec2);
    }
}
