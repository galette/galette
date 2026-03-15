<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Exception;

use LogicException;
use Throwable;

/**
 * Missing plugin exception
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MissingPluginException extends LogicException
{
    public function __construct(string $id,int $code = 0, ?Throwable $previous = null) {
        parent::__construct(
            sprintf(
                'Module "%s" does not exist!',
                $id
            ),
            $code,
            $previous
        );
    }
}
