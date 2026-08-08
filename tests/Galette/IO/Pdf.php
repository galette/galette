<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\IO;

use Galette\Tests\BaseGaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PDF tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Pdf extends BaseGaletteTestCase
{
    /**
     * HTML normalization provider
     *
     * @return array<string, array<int, string>>
     */
    public static function normalizeHtmlProvider(): array
    {
        return [
            'indented after line break' => [
                "Name<br/>\n            Address<br/>\n            Zipcode Town",
                'Name<br/>Address<br/>Zipcode Town'
            ],
            'indented after cell opening' => [
                "<table><tr><td>\n            Name<br/>Address\n        </td></tr></table>",
                "<table><tr><td>Name<br/>Address\n        </td></tr></table>"
            ],
            'various block tags' => [
                "<div>\n  a</div><p>\n  b</p><ul>\n  <li>\n  c</li>\n</ul><h3>\n  d</h3>",
                "<div>a</div><p>b</p><ul><li>c</li>\n</ul><h3>d</h3>"
            ],
            'inline tags untouched' => [
                "<strong>\n  Name</strong> <em> x</em>",
                "<strong>\n  Name</strong> <em> x</em>"
            ],
            'preformatted content preserved' => [
                "<div>\n  x</div><pre>\n  keep  me\n</pre><p>\n  y</p>",
                "<div>x</div><pre>\n  keep  me\n</pre><p>y</p>"
            ],
            'nothing to do' => [
                'Name<br/>Address',
                'Name<br/>Address'
            ],
        ];
    }

    /**
     * Test insignificant whitespace removal
     *
     * @param string $html     HTML to normalize
     * @param string $expected Expected result
     */
    #[DataProvider('normalizeHtmlProvider')]
    public function testNormalizeHtml(string $html, string $expected): void
    {
        $this->assertSame($expected, \Galette\IO\Pdf::normalizeHtml($html));
    }
}
