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

namespace Galette\Core;

use Analog\Analog;
use Laminas\I18n\Translator\Translator as ZTranslator;

/**
 * Zend translator override
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class Translator extends ZTranslator
{
    /**
     * Do a translation exist for string
     *
     * @param string  $message    String to check for
     * @param string  $textDomain Translation domain, defaults to "default"
     * @param ?string $locale     Locale, defaults to null
     *
     * @return boolean
     */
    public function translationExists(string $message, string $textDomain = 'default', string $locale = null): bool
    {
        $locale = ($locale ?: $this->getLocale());

        if (!isset($this->messages[$textDomain][$locale])) {
            $this->loadMessages($textDomain, $locale);
        }

        return isset($this->messages[$textDomain][$locale][$message]);
    }
}
