<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Util;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Text tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Text extends GaletteTestCase
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
     */
    #[DataProvider('slugifyProvider')]
    public function testSlugify(string $string, string $expected): void
    {
        $this->assertSame($expected, \Galette\Util\Text::slugify($string));
    }

    /**
     * Test failing slugify
     */
    public function testFailSlugify(): void
    {
        $this->expectException(\RuntimeException::class);
        \Galette\Util\Text::slugify('----');
    }

    /**
     * Test getRandomString method
     */
    public function testGetRandomString(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]{10}$/', \Galette\Util\Text::getRandomString(10));
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]{60}$/', \Galette\Util\Text::getRandomString(60));
    }

    /**
     * Test truncateOnWords method
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
}
