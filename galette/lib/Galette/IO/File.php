<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Files
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 *
 * @category  IO
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7.7dev - 2013-09-06
 */

namespace Galette\IO;

use Analog\Analog;
use Galette\IO\FileTrait;

/**
 * Files
 *
 * @category  IO
 * @name      Csv
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     Available since 0.7.7dev - 2013-09-06
 */

class File implements FileInterface
{
    use FileTrait;

    /**
     * Default constructor
     *
     * @param string $dest       File destination directory
     * @param array  $extensions Array of permitted extensions
     * @param array  $mimes      Array of permitted mime types
     * @param int    $maxlenght  Maximum lenght for each file
     */
    public function __construct(
        $dest,
        $extensions = null,
        $mimes = null,
        $maxlenght = null
    ) {
        $this->init(
            $dest,
            $extensions,
            $mimes,
            $maxlenght
        );
    }
}
