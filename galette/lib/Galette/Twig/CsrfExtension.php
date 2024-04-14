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

namespace Galette\Twig;

use Slim\Csrf\Guard;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig CSRF extension
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */


class CsrfExtension extends AbstractExtension implements GlobalsInterface
{
    protected Guard $csrf;

    /**
     * Constructor
     * @param Guard $csrf CSRF instance
     */
    public function __construct(Guard $csrf)
    {
        $this->csrf = $csrf;
    }

    /**
     * Get globals
     *
     * @return array<string,null|string>
     */
    public function getGlobals(): array
    {
        // CSRF token name and value
        $nameKey = $this->csrf->getTokenNameKey();
        $valueKey = $this->csrf->getTokenValueKey();
        $name = $this->csrf->getTokenName();
        $value = $this->csrf->getTokenValue();

        return [
            'csrf_name_key' => $nameKey,
            'csrf_value_key' => $valueKey,
            'csrf_name' => $name,
            'csrf_value' => $value
        ];
    }
}
