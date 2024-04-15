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

namespace Galette\Core;

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
    //database wants a member id (integer), not a string.
    //Will be used to query the correct id
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
        //anyways, we have no custom print logo
        $this->custom = false;
    }
}
