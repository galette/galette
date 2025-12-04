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

namespace Galette\Core;

use function Safe\imagecreatefromwebp;
use function Safe\imagepng;

/**
 * This class stores a logo for printing that could be different
 * from the default one.
 * If no print logo is found, we take the default Logo instead.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
*/
class PrintLogo extends Logo
{
    protected string|int $id = 'custom_print_logo';
    // Database wants a member id (integer), not a string.
    // Will be used to query the correct id
    protected int $db_id = 999999;

    /**
     * Gets the default picture to show, anyway
     *
     * @see Logo::getDefaultPicture()
     *
     * @return void
     */
    protected function getDefaultPicture(): void
    {
        //if we are here, we want to serve default logo
        $pic = new Logo();
        $this->file_path = $pic->getPath();
        $this->format = $pic->getFormat();
        $this->mime = $pic->getMime();
        //anyway, we have no custom print logo
        $this->custom = false;
    }

    /**
     * Returns current file full path
     *
     * @return string full file path
     */
    public function getPath(): string
    {
        if ($this->getFormat() !== 'webp') {
            return $this->file_path;
        }

        //TCPDF does not support background transparency for WEBP images, create a PNG version
        $this->format = 'png';
        $this->mime = 'image/png';
        $converted_logo_path = sprintf('%s/%s.%s', GALETTE_CACHE_DIR, 'galette_printlogo_converted', 'png');
        if (!file_exists($converted_logo_path)) {
            $print_logo = imagecreatefromwebp($this->file_path);
            imagepng($print_logo, $converted_logo_path);
        }
        return $converted_logo_path;
    }
}
