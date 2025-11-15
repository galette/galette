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
     *
     * @return string
     */
    #[\Override]
    public function convert(ElementInterface $element): string
    {
        $src   = $element->getAttribute('src');
        //keep @src only if it's an URL, else discard it
        if (!preg_match('|^https?://|', strtolower($src))) {
            $src = '';
        }

        $alt   = $element->getAttribute('alt');
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
