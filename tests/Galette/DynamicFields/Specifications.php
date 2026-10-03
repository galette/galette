<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\DynamicFields;

use Galette\Tests\GaletteTestCase;
use Galette\DynamicFields\ChoiceSpecifications;

use function Safe\json_encode;

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
        $this->assertNull($spec->foo); // @phpstan-ignore property.notFound (class handle that)

        $spec->foo = 'bar';
        $this->assertSame('bar', $spec->foo);
        $this->assertTrue(isset($spec->foo));

        $json = json_encode($spec);
        $this->assertStringContainsString('"foo":"bar"', $json);

        $spec2 = (new ChoiceSpecifications())->fromJson($json);
        $this->assertSame('bar', $spec2->foo); // @phpstan-ignore property.notFound (class handle that)
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
    }
}
