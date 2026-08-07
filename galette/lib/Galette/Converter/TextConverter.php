<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Converter;

use League\HTMLToMarkdown\ElementInterface;

use function Safe\preg_replace;

/**
 * Text converter
 *
 * Same as parent, but does not escape Markdown special characters: produced
 * contents is used as plain text (mails alternative body, and so on), where
 * a "my\_name@example.com" like escaping is just noise.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TextConverter extends \League\HTMLToMarkdown\Converter\TextConverter
{
    /**
     * Converts text to markdown.
     *
     * @param ElementInterface $element Element to convert
     */
    public function convert(ElementInterface $element): string
    {
        $markdown = $element->getValue();

        //remove leftover \n at the beginning of the line
        $markdown = ltrim($markdown, "\n");

        //replace sequences of invisible characters with spaces
        $markdown = preg_replace('~\s+~u', ' ', $markdown);

        if ($markdown === ' ') {
            $next = $element->getNext();
            if (!$next || $next->isBlock()) {
                $markdown = '';
            }
        }

        return $markdown;
    }
}
