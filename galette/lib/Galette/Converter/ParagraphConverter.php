<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Converter;

use League\HTMLToMarkdown\ElementInterface;

/**
 * Paragraph converter
 *
 * Same as parent, but does not escape Markdown special characters; see
 * {@link TextConverter}.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ParagraphConverter extends \League\HTMLToMarkdown\Converter\ParagraphConverter
{
    /**
     * Converts P element to markdown.
     *
     * @param ElementInterface $element Element to convert
     */
    public function convert(ElementInterface $element): string
    {
        $value = $element->getValue();
        return trim($value) !== '' ? rtrim($value) . "\n\n" : '';
    }
}
