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

namespace Galette\IO;

use Analog\Analog;
use Galette\IO\FileTrait;

/**
 * Files
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class File implements FileInterface
{
    use FileTrait;

    /**
     * Default constructor
     *
     * @param string                $dest       File destination directory
     * @param ?array<int,string>    $extensions Array of permitted extensions
     * @param ?array<string,string> $mimes      Array of permitted mime types
     * @param ?int                  $maxlength  Maximum length for each file
     */
    public function __construct(
        string $dest,
        ?array $extensions = null,
        ?array $mimes = null,
        ?int $maxlength = null
    ) {
        $this->init(
            $dest,
            $extensions,
            $mimes,
            $maxlength
        );
    }
}
