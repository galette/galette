<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Logo handling - for printing
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id: print_logo.class.php 546 2009-03-05 06:09:00Z trashy $
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-09-20
 */

namespace Galette\Core;

/**
 * This class stores a logo for printing that could be different
 * from the default one.
 * If no print logo is found, we take the default Logo instead.
 *
 * @category  Core
 * @name      PrintLogo
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-09-20
*/
class PrintLogo extends Logo
{
    protected $id = 'custom_print_logo';
    //database wants a member id (integer), not a string.
    //Will be used to query the correct id
    protected $db_id = 999999;

    /**
    * Gets the default picture to show, anyways
    *
    * @see Logo::getDefaultPicture()
    *
    * @return void
    */
    protected function getDefaultPicture()
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
