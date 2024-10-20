<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\Util\test\units;

use PHPUnit\Framework\TestCase;
use Galette\Core\Preferences;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Text tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Text extends TestCase
{
    /**
     * Texts to "slugify" provider
     *
     * @return array<int, array<string, string>>
     */
    public static function slugifyProvider(): array
    {
        return [
            [
                'string'   => 'My - string èé  Ê À ß',
                'expected' => 'my-string-ee-e-a-ss'
            ], [
                'string'   => 'Έρευνα ικανοποίησης - Αιτήματα',
                'expected' => 'ereuna-ikanopoieses-aitemata'
            ], [
                'string'   => 'a-valid-one',
                'expected' => 'a-valid-one',
            ]
        ];
    }

    /**
     * Test slugify method
     *
     * @param string $string   String to slugify
     * @param string $expected Expected result
     *
     * @return void
     */
    #[DataProvider('slugifyProvider')]
    public function testSlugify(string $string, string $expected): void
    {
        $this->assertSame($expected, \Galette\Util\Text::slugify($string));
    }

    /**
     * Test failing slugify
     *
     * @return void
     */
    public function testFailSlugify(): void
    {
        $this->expectException(\RuntimeException::class);
        \Galette\Util\Text::slugify('----');
    }

    /**
     * Test getRandomString method
     *
     * @return void
     */
    public function testGetRandomString(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]{10}$/', \Galette\Util\Text::getRandomString(10));
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]{60}$/', \Galette\Util\Text::getRandomString(60));
    }
}
