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

namespace Galette\Features;

/**
 * Translatable objects trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait Translatable
{
    protected ?string $old_name = null;
    protected ?string $name = null;

    /**
     * Get field name
     *
     * @param boolean $translated Get translated or raw name
     *
     * @return string
     */
    public function getName(bool $translated = true): string
    {
        if (empty($this->name)) {
            return '';
        } elseif ($translated === true) {
            return _T(strip_tags($this->name));
        } else {
            return strip_tags($this->name);
        }
    }
}
