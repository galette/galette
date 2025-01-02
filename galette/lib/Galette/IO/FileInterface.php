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

/**
 * File interface
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

interface FileInterface
{
    public const INVALID_FILENAME = -1;
    public const INVALID_EXTENSION = -2;
    public const FILE_TOO_BIG = -3;
    public const IMAGE_TOO_SMALL = -4;
    public const MIME_NOT_ALLOWED = -5;
    public const NEW_FILE_EXISTS = -6;
    public const INVALID_FILE = -7;
    public const CANT_WRITE = -8;
    public const MAX_FILE_SIZE = 2048;
    public const MIN_CROP_SIZE = 267;
}
