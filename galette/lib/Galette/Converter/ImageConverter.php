<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Converter;

use League\HTMLToMarkdown\ElementInterface;

use function Safe\preg_match;

/**
 * IMG converter
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ImageConverter extends \League\HTMLToMarkdown\Converter\ImageConverter
{
    /**
     * Converts IMG element to markdown.
     *
     * @param ElementInterface $element Element to convert
     */
    public function convert(ElementInterface $element): string
    {
        $src = $element->getAttribute('src');
        //keep @src only if it's an URL, else discard it
        if (!preg_match('|^https?://|', strtolower($src))) {
            $src = '';
        }

        $alt = $element->getAttribute('alt');
        $title = $element->getAttribute('title');

        $img = ($alt != '' ? "![$alt]" : '');
        if ($src !== '' || $title !== '') {
            $img .= sprintf(
                '(%1$s%2$s%3$s)',
                $src,
                ($src !== '' && $title != '' ? ' ' : ''),
                ($title !== '' ? '"' . $title . '"' : '')
            );
        }

        return $img;
    }
}
