<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace GaletteTests\Util;

use PHPUnit\Framework\TestCase;
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

    /**
     * Test truncateOnWords method
     *
     * @return void
     */
    public function testTruncateOnWords(): void
    {
        $text = 'This text will be truncated on entire words, using Galette\'s-util methods that manages a bit of text.';
        $truncated = \Galette\Util\Text::truncateOnWords(text: $text);
        $this->assertSame('This text will be truncated on entire words, using Galette\'s-util…', $truncated);

        // Test with a shorter length
        $truncated = \Galette\Util\Text::truncateOnWords(text: $text, max_words: 5);
        $this->assertSame('This text will be truncated…', $truncated);

        //Test with HTML string
        $text = '<p>This text will be truncated on <strong>entire</strong> words, using <em>Galette\'s-util</em> methods that manages a bit of text.</p>';
        $truncated = \Galette\Util\Text::truncateOnWords(text: $text);
        $this->assertSame('This text will be truncated on entire words, using Galette\'s-util…', $truncated);
        $truncated = \Galette\Util\Text::truncateOnWords(text: $text, keep_html: true); //will produce invalid HTML
        $this->assertSame('<p>This text will be truncated on <strong>entire</strong> words, using <em>Galette\'s-util</em>…', $truncated);


        $truncated = \Galette\Util\Text::truncateOnWords(text: $text, max_words: 5);
        $this->assertSame('This text will be truncated…', $truncated);
        $truncated = \Galette\Util\Text::truncateOnWords(text: $text, max_words: 5, keep_html: true); //will produce invalid HTML
        $this->assertSame('<p>This text will be truncated…', $truncated);
    }


    /**
     * Data provider for testTextConversion
     *
     * @return array<string, string>
     */
    public static function textConversionProvider(): array
    {
        return [
            [
                'input' => 'Simple text',
                'expected' => 'Simple text'
            ],
            [
                'input' => '<p>Simple paragraph</p>',
                'expected' => 'Simple paragraph'
            ],
            [
                'input' => '<div>Line 1<br>Line 2</div>',
                'expected' => "Line 1\nLine 2"
            ],
            [
                'input' => '<div><p>A first paragraph, with a <a href="https://galette.eu" title="Link title" class="superlink">link</a></p><p>And another paragraph.</p></div>',
                'expected' => "A first paragraph, with a [link](https://galette.eu)\n\nAnd another paragraph."
            ],
            [
                'input' => '<div>Any text <img src="/logo" alt="Alternative text"/></div>',
                'expected' => "Any text [Alternative text]"
            ],
            [
                'input' => '<div>Any text <img src="http://galette.eu/logo" alt="Alternative text"/></div>',
                'expected' => "Any text [Alternative text]"
            ],
            [
                'input' => '<div>Any text <img src="hTtPs://galette.eu/logo" alt="Alternative text"/></div>',
                'expected' => "Any text [Alternative text]"
            ],
            [
                'input' => '<div>Any text <img src="htps://galette.eu/logo" alt="Alternative text"/></div>',
                'expected' => "Any text [Alternative text]"
            ],
            [
                'input' => 'An image <img src="data:image/png;base64,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" alt="base64"/> encoded',
                'expected' => "An image [base64] encoded"
            ],
            [
                'input' => '<div>OK, let\'s see how <em>formatted</em> text is <i>handled</i><br/><strong>Correctly?</strong> Or maybe <b>not</b>.</div>',
                'expected' => "OK, let's see how formatted text is handled\nCorrectly? Or maybe not." //not supported with current library
            ],
            [
                'input' => "Text formatted\nwith new lines.\n\nAnd paragraphs.",
                'expected' => "Text formatted with new lines. And paragraphs." //not supported with current library
            ],
            [
                'input' => 'A link <a>with no href</a>',
                'expected' => 'A link with no href'
            ]
        ];
    }

    /**
     * Test text conversion to proper format
     *
     * @param string $input    Input text
     * @param string $expected Expected result
     *
     * @return void
     */
    #[DataProvider('textConversionProvider')]
    public function testTextConversion(string $input, string $expected): void
    {
        $this->assertSame(
            $expected,
            \Galette\Util\Text::convertHtmlToText($input)
        );
    }
}
